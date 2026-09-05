<?php

namespace App\Http\Controllers\Centro;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Centro;
use App\Models\Curso;
use App\Models\Resultado;
use App\Support\Nivel;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function index(): View
    {
        $centro = Centro::findOrFail(auth()->user()->centro_id);

        $alumnos = Alumno::with(['grupo.curso', 'asignaturas', 'resultados.evaluable.imparticion.asignatura'])
            ->where('centro_id', $centro->id)
            ->where('activo', true)
            ->get();

        // Un solo recorrido: se calcula el rendimiento de cada alumno y se
        // acumulan a la vez el reparto global y el reparto por curso.
        $reparto = [Nivel::BAJO => 0, Nivel::MEDIO => 0, Nivel::ALTO => 0, Nivel::SIN_DATOS => 0];
        $porCurso = [];
        $sinFamilia = 0;

        foreach ($alumnos as $alumno) {
            $r = $alumno->rendimiento();
            $nivel = $r['global']['nivel'];
            $reparto[$nivel]++;

            $curso = $alumno->grupo?->curso;
            $clave = $curso?->id ?? 0;
            $porCurso[$clave]['curso'] = $curso;
            $porCurso[$clave]['reparto'][$nivel] = ($porCurso[$clave]['reparto'][$nivel] ?? 0) + 1;
            $porCurso[$clave]['total'] = ($porCurso[$clave]['total'] ?? 0) + 1;

            if ($alumno->tutores->isEmpty()) {
                $sinFamilia++;
            }
        }

        // Se ordenan los cursos por su denominación, no por id de creación.
        uasort($porCurso, fn ($a, $b) => strcmp($a['curso']->denominacion ?? '', $b['curso']->denominacion ?? ''));

        $totalAlumnos = $alumnos->count();

        return view('centro.panel', [
            'centro'        => $centro,
            'totalAlumnos'  => $totalAlumnos,
            'reparto'       => $reparto,
            'porCurso'      => $porCurso,
            'sinFamilia'    => $sinFamilia,
            'porcVinculada' => $totalAlumnos > 0
                ? round((($totalAlumnos - $sinFamilia) / $totalAlumnos) * 100)
                : 0,
            'actividad'     => $this->actividadReciente($centro),
            'cursos'        => Curso::where('centro_id', $centro->id)->count(),
        ]);
    }

    /**
     * Últimos resultados registrados, con quién los registró.
     * Es lo que convierte el panel en algo que se mira todos los días.
     */
    private function actividadReciente(Centro $centro, int $limite = 8)
    {
        return Resultado::with([
                'alumno.grupo.curso',
                'autor',
                'evaluable.imparticion.asignatura',
            ])
            ->whereHas('alumno', fn ($q) => $q->where('centro_id', $centro->id))
            ->latest()
            ->limit($limite)
            ->get();
    }
}
