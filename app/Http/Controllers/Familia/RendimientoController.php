<?php

namespace App\Http\Controllers\Familia;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Familia\Concerns\EligeAlumno;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rendimiento en detalle: exámenes por un lado, ejercicios por otro.
 *
 * Tiene pantalla propia y no una tarjeta más en el inicio porque responde a
 * otra pregunta. El inicio contesta «¿cómo va?» —un índice, un nivel, las
 * asignaturas en rojo— y se mira de pasada. Esto contesta «¿por qué?», que es
 * la conversación que una familia tiene una vez al trimestre y en la que quiere
 * detenerse: si el problema son los exámenes o es no entregar los ejercicios,
 * la respuesta y la conversación con el tutor son distintas.
 */
class RendimientoController extends Controller
{
    use EligeAlumno;

    public function index(Request $peticion): View
    {
        $tutor  = $this->tutor();
        $hijos  = $this->hijos($tutor);
        $alumno = $this->alumnoElegido($peticion, $hijos);

        return view('familia.rendimiento', [
            'tutor'       => $tutor,
            'hijos'       => $hijos,
            'alumno'      => $alumno,
            'rendimiento' => $alumno->rendimiento(),
        ]);
    }
}
