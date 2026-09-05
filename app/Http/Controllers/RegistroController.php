<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\CodigoVinculacion;
use App\Models\Tutela;
use App\Models\TutorLegal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Registro de familias.
 *
 * Solo se registran familias. El profesorado y la dirección los da de alta el
 * centro, porque su cuenta implica permisos sobre datos de terceros y eso no se
 * autoconcede.
 *
 * Y una familia tampoco se registra sola del todo: hace falta un **código de
 * vinculación** que el centro genera desde la ficha del alumno. Sin esa pieza,
 * un formulario abierto que dejara elegir al hijo de una lista permitiría a
 * cualquiera con un navegador vincularse a cualquier menor. El código traslada
 * la decisión a quien debe tomarla —el centro— y deja el formulario reducido a
 * lo que es: alguien demostrando que el centro ya le autorizó.
 *
 * El código dice a la vez **quién es el alumno** y **de qué centro es**, así que
 * el formulario nunca pregunta ninguna de las dos cosas. Preguntarlas sería
 * ofrecer un buscador de menores a un desconocido.
 */
class RegistroController extends Controller
{
    public function create(Request $peticion): View
    {
        // Si llega con `?codigo=`, se muestra ya escrito y con el nombre del
        // alumno delante: así la familia ve a quién se va a vincular antes de
        // rellenar nada, y un código mal copiado se detecta al momento.
        $codigo = $peticion->query('codigo')
            ? CodigoVinculacion::valido((string) $peticion->query('codigo'))
            : null;

        return view('auth.registro', [
            'codigoEscrito' => (string) $peticion->query('codigo', ''),
            'codigo'        => $codigo,
        ]);
    }

    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'codigo'     => ['required', 'string', 'max:20'],
            'nombre'     => ['required', 'string', 'max:100'],
            'apellidos'  => ['required', 'string', 'max:150'],
            'telefono'   => ['required', 'string', 'max:20'],
            'parentesco' => ['required', Rule::in(Tutela::PARENTESCOS)],
            'email'      => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'codigo.required'    => 'Hace falta el código que te ha dado el centro.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
            'password.min'       => 'La contraseña necesita ocho caracteres como mínimo.',
            'email.unique'       => 'Ya hay una cuenta con ese correo. Si es tuya, entra en vez de registrarte.',
        ], [
            'nombre' => 'nombre', 'apellidos' => 'apellidos', 'telefono' => 'teléfono',
            'email'  => 'correo', 'password' => 'contraseña', 'codigo' => 'código',
        ]);

        $codigo = CodigoVinculacion::valido($datos['codigo']);

        // Un solo mensaje para «no existe», «ya se usó» y «ha caducado»: decir
        // cuál de las tres es le diría a quien prueba códigos al azar cuándo ha
        // acertado uno.
        if (! $codigo || ! $codigo->alumno) {
            throw ValidationException::withMessages([
                'codigo' => 'Ese código no es válido, ya se ha usado o ha caducado. Pídele otro al centro.',
            ]);
        }

        $alumno = $codigo->alumno;

        if (! Tutela::hayHueco($alumno->id)) {
            throw ValidationException::withMessages([
                'codigo' => 'Ese alumno ya tiene ' . Tutela::MAX_POR_ALUMNO
                    . ' familias vinculadas. Habla con el centro.',
            ]);
        }

        $usuario = DB::transaction(function () use ($datos, $codigo, $alumno, $peticion) {
            $usuario = User::create([
                'name'      => "{$datos['nombre']} {$datos['apellidos']}",
                'email'     => $datos['email'],
                'password'  => $datos['password'],
                'rol'       => User::ROL_TUTOR_LEGAL,
                'centro_id' => $alumno->centro_id,   // el del alumno, no uno elegido
                'telefono'  => $datos['telefono'],
                'activo'    => true,
            ]);

            $tutor = TutorLegal::create([
                'user_id'   => $usuario->id,
                'nombre'    => $datos['nombre'],
                'apellidos' => $datos['apellidos'],
                'telefono'  => $datos['telefono'],
            ]);

            DB::table('tutelas')->insert([
                'tutor_legal_id' => $tutor->id,
                'alumno_id'      => $alumno->id,
                'parentesco'     => $datos['parentesco'],
                'vinculado_en'   => now(),
                'activa'         => true,
            ]);

            // El código se gasta dentro de la misma transacción: si algo falla
            // después, no se queda quemado.
            $codigo->update(['usado_en' => now()]);

            Auditoria::registrar(
                entidad: 'tutela', entidadId: $tutor->id, accion: Auditoria::CREAR,
                autorId: $usuario->id, anterior: null,
                nuevo: [
                    'origen'         => 'registro con código',
                    'tutor_legal_id' => $tutor->id,
                    'alumno_id'      => $alumno->id,
                    'parentesco'     => $datos['parentesco'],
                ],
                ip: $peticion->ip(),
            );

            return $usuario;
        });

        Auth::login($usuario);
        $peticion->session()->regenerate();

        return redirect()
            ->route('familia.inicio')
            ->with('exito', "Cuenta creada. Ya puedes seguir a {$alumno->nombre}.");
    }
}
