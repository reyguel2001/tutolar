<?php

namespace App\Http\Controllers\Profesor;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Imparticion;
use App\Support\Nivel;
use Illuminate\View\View;

class GrupoController extends Controller
{
    public function index(): View
    {
        $profesor = auth()->user()->profesor;
        abort_unless($profesor, 403, 'Esta cuenta no tiene perfil docente.');

        $imparticiones = Imparticion::with(['asignatura', 'grupo.curso'])
            ->where('profesor_id', $profesor->id)
            ->get();

        $tarjetas = $imparticiones->map(function (Imparticion $imp) {
            $alumnos = Alumno::with(['asignaturas', 'tutores', 'resultados.evaluable.imparticion.asignatura'])
                ->where('grupo_id', $imp->grupo_id)
                ->where('activo', true)
                ->get();

            $reparto = [Nivel::BAJO => 0, Nivel::MEDIO => 0, Nivel::ALTO => 0, Nivel::SIN_DATOS => 0];
            $vinculados = 0;

            foreach ($alumnos as $alumno) {
                // El profesor ve el rendimiento en SU asignatura, no el global.
                $r = $alumno->rendimiento();
                $fila = collect($r['asignaturas'])
                    ->first(fn ($a) => $a['asignatura']->id === $imp->asignatura_id);

                $reparto[$fila['ira']['nivel'] ?? Nivel::SIN_DATOS]++;

                if ($alumno->tutores->isNotEmpty()) {
                    $vinculados++;
                }
            }

            return [
                'imparticion' => $imp,
                'alumnos'     => $alumnos->count(),
                'vinculados'  => $vinculados,
                'reparto'     => $reparto,
            ];
        });

        return view('profesor.grupos', ['tarjetas' => $tarjetas, 'profesor' => $profesor]);
    }
}
