<?php

namespace App\Http\Controllers;

use App\Models\Centro;
use App\Models\SolicitudVinculacion;
use App\Models\Tutela;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pedir un código de vinculación sin tenerlo.
 *
 * Responde a la pregunta obvia de la pantalla de registro: «¿y si no tengo
 * código?». Antes la respuesta era «pídeselo al centro», que fuera de la
 * aplicación significa llamar por teléfono en horario de secretaría. Esto deja
 * que la familia arranque el trámite a las once de la noche y que el centro lo
 * resuelva cuando abra.
 *
 * LO QUE ESTA PANTALLA NO HACE, Y ES LO IMPORTANTE:
 *
 *   · **No comprueba si el alumno existe.** Sería un comprobador de matrículas
 *     abierto: «¿está Lucía Ramos en este instituto?» respondido a cualquiera.
 *     Lo que se escribe se guarda como texto y lo contrasta una persona.
 *   · **No da acceso a nada.** No crea cuenta, no crea tutela, no genera código.
 *     Solo pone una fila en la bandeja del centro.
 *   · **No dice si acertaste.** El mensaje de después es idéntico se parezca lo
 *     escrito a un alumno real o no. Si dijera «alumno encontrado», el
 *     formulario sería otra vez un comprobador, solo que más lento.
 */
class SolicitudVinculacionController extends Controller
{
    public function create(): View
    {
        return view('auth.solicitud', [
            // Los centros son dato público: están en el mapa y en el BOE. Este
            // sí puede ser un desplegable de verdad.
            'centros' => Centro::where('activo', true)->orderBy('denominacion')->get(),
        ]);
    }

    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'centro_id'        => ['required', 'integer', Rule::exists('centros', 'id')->where('activo', true)],
            'alumno_nombre'    => ['required', 'string', 'max:100'],
            'alumno_apellidos' => ['required', 'string', 'max:150'],
            'alumno_curso'     => ['nullable', 'string', 'max:80'],
            'nombre'           => ['required', 'string', 'max:100'],
            'apellidos'        => ['required', 'string', 'max:150'],
            'email'            => ['required', 'email', 'max:180'],
            'telefono'         => ['required', 'string', 'max:20'],
            'parentesco'       => ['required', Rule::in(Tutela::PARENTESCOS)],
            'mensaje'          => ['nullable', 'string', 'max:500'],
        ], [], [
            'centro_id'        => 'centro',
            'alumno_nombre'    => 'nombre del alumno',
            'alumno_apellidos' => 'apellidos del alumno',
            'email'            => 'correo',
            'telefono'         => 'teléfono',
        ]);

        SolicitudVinculacion::create($datos + [
            'estado' => SolicitudVinculacion::PENDIENTE,
            'ip'     => $peticion->ip(),
        ]);

        // El mismo mensaje pase lo que pase. Ni confirma ni desmiente que ese
        // alumno exista, que es justo lo que no debe salir de aquí.
        return redirect()
            ->route('solicitud.create')
            ->with('exito', 'Solicitud enviada. Si los datos coinciden con los del centro, '
                . 'te harán llegar el código de vinculación por el medio de contacto que tengan de ti. '
                . 'No hace falta que vuelvas a enviarla.');
    }
}
