<?php

namespace App\Http\Controllers\Familia;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Familia\Concerns\EligeAlumno;
use App\Models\Alumno;
use App\Models\Evaluable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InicioController extends Controller
{
    // La elección del hijo —y la comprobación de que es suyo— vive en el trait,
    // compartida con la pantalla de rendimiento.
    use EligeAlumno;

    public function index(Request $request): View
    {
        $tutor  = $this->tutor();
        $hijos  = $this->hijos($tutor);
        $alumno = $this->alumnoElegido($request, $hijos);

        return view('familia.inicio', [
            'tutor'       => $tutor,
            'hijos'       => $hijos,
            'alumno'      => $alumno,
            'rendimiento' => $alumno->rendimiento(),
            'pendientes'  => $this->evaluablesPendientes($alumno),
        ]);
    }

    /**
     * Exámenes y tareas de los que esta familia todavía no ha apuntado la nota.
     *
     * Tres filtros, y ninguno es opcional:
     *   · del grupo del alumno,
     *   · de una asignatura en la que está matriculado,
     *   · y sin resultado suyo todavía.
     *
     * El formulario solo puede ofrecer lo que salga de aquí; aun así,
     * ResultadoController vuelve a comprobarlo todo antes de guardar, porque un
     * `<select>` es una sugerencia del servidor, no una garantía.
     *
     * @return Collection<int, Evaluable>
     */
    private function evaluablesPendientes(Alumno $alumno): Collection
    {
        $asignaturas = $alumno->asignaturas->pluck('id');

        if ($asignaturas->isEmpty()) {
            return Evaluable::query()->whereRaw('1 = 0')->get();
        }

        return Evaluable::query()
            ->whereHas('imparticion', fn ($q) => $q
                ->where('grupo_id', $alumno->grupo_id)
                ->whereIn('asignatura_id', $asignaturas))
            ->whereDoesntHave('resultados', fn ($q) => $q->where('alumno_id', $alumno->id))
            ->where('estado', '!=', 'ANULADO')
            ->with('imparticion.asignatura')
            ->orderByDesc('fecha_prevista')
            ->get();
    }
}
