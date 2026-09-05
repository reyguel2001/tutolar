<?php

namespace App\Http\Controllers\Centro;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cuentas de acceso.
 *
 * Aquí se ve quién puede entrar y con qué rol, y se activan o desactivan
 * cuentas. Lo que NO se hace aquí:
 *
 *   · Cambiar el rol de una cuenta existente. Un usuario tiene exactamente un
 *     rol y su perfil cuelga de él; cambiarlo dejaría un profesor sin ficha de
 *     profesor o un tutor sin tutelas. Si alguien cambia de puesto, se le crea
 *     otra cuenta.
 *   · Crear profesores o tutores. Esas altas van por su pantalla, porque además
 *     de la cuenta hay que crear el perfil.
 *
 * Desde aquí solo se crean cuentas de dirección.
 */
class CuentaController extends BaseCentroController
{
    private const POR_PAGINA = 25;

    /**
     * Una cuenta es del centro si se creó aquí o si tutela a algún alumno de
     * aquí. Lo segundo cubre a las familias del seeder, cuyo `centro_id` puede
     * venir vacío.
     */
    private function consulta(): Builder
    {
        $centroId = $this->centroId();

        return User::query()->where(function (Builder $q) use ($centroId) {
            $q->where('centro_id', $centroId)
              ->orWhereHas('tutorLegal.alumnos', fn ($a) => $a->where('alumnos.centro_id', $centroId));
        });
    }

    public function index(Request $peticion): View
    {
        $filtros = [
            'q'      => trim((string) $peticion->query('q')),
            'rol'    => $peticion->query('rol'),
            'estado' => $peticion->query('estado'),
        ];

        $cuentas = $this->consulta()
            ->when($filtros['q'] !== '', fn ($c) => $c->where(function (Builder $b) use ($filtros) {
                $b->where('name', 'like', "%{$filtros['q']}%")
                  ->orWhere('email', 'like', "%{$filtros['q']}%");
            }))
            ->when($filtros['rol'], fn ($c, $v) => $c->where('rol', $v))
            ->when($filtros['estado'] === 'activas', fn ($c) => $c->where('activo', true))
            ->when($filtros['estado'] === 'inactivas', fn ($c) => $c->where('activo', false))
            ->orderBy('rol')
            ->orderBy('name')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('centro.cuentas.index', [
            'cuentas'  => $cuentas,
            'filtros'  => $filtros,
            'resumen'  => [
                User::ROL_CENTRO      => (clone $this->consulta())->where('rol', User::ROL_CENTRO)->count(),
                User::ROL_PROFESOR    => (clone $this->consulta())->where('rol', User::ROL_PROFESOR)->count(),
                User::ROL_TUTOR_LEGAL => (clone $this->consulta())->where('rol', User::ROL_TUTOR_LEGAL)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('centro.cuentas.form', ['cuenta' => new User()]);
    }

    /** Solo cuentas de dirección: las demás nacen con su perfil. */
    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'telefono' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $cuenta = User::create($datos + [
            'rol'       => User::ROL_CENTRO,
            'centro_id' => $this->centroId(),
            'activo'    => true,
        ]);

        $this->auditar($peticion, 'user', $cuenta->id, Auditoria::CREAR, null, [
            'email' => $cuenta->email, 'rol' => $cuenta->rol,
        ]);

        return redirect()
            ->route('centro.cuentas.index')
            ->with('exito', "Cuenta de dirección creada para {$cuenta->email}.");
    }

    public function edit(string $cuenta): View
    {
        return view('centro.cuentas.form', ['cuenta' => $this->consulta()->findOrFail($cuenta)]);
    }

    public function update(Request $peticion, string $cuenta): RedirectResponse
    {
        $modelo = $this->consulta()->findOrFail($cuenta);
        $antes  = ['name' => $modelo->name, 'email' => $modelo->email, 'activo' => $modelo->activo];

        $datos = $peticion->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($modelo->id)],
            'telefono' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $modelo->fill([
            'name'     => $datos['name'],
            'email'    => $datos['email'],
            'telefono' => $datos['telefono'] ?? null,
        ]);

        if (! empty($datos['password'])) {
            $modelo->password = $datos['password'];
        }

        $modelo->save();

        $this->auditar($peticion, 'user', $modelo->id, Auditoria::MODIFICAR, $antes, [
            'name' => $modelo->name, 'email' => $modelo->email,
            'contrasena_cambiada' => ! empty($datos['password']),
        ]);

        return redirect()->route('centro.cuentas.index')->with('exito', 'Cuenta actualizada.');
    }

    /**
     * Activar o desactivar. No hay borrado de cuentas: una cuenta borrada se
     * lleva por delante su perfil y, con él, la trazabilidad de quién registró
     * cada nota. Desactivar corta el acceso y conserva el histórico.
     */
    public function alternarActivo(Request $peticion, string $cuenta): RedirectResponse
    {
        $modelo = $this->consulta()->findOrFail($cuenta);

        if ($modelo->id === $peticion->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $activaAhora = ! $modelo->activo;
        $modelo->update(['activo' => $activaAhora]);

        $this->auditar(
            $peticion, 'user', $modelo->id,
            $activaAhora ? Auditoria::MODIFICAR : Auditoria::ANULAR,
            ['activo' => ! $activaAhora],
            ['activo' => $activaAhora],
        );

        return back()->with('exito', $activaAhora
            ? "Cuenta de {$modelo->email} reactivada."
            : "Cuenta de {$modelo->email} desactivada. Ya no puede entrar.");
    }
}
