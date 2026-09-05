<?php

namespace App\Http\Controllers\Centro;

use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Grupo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Matrículas: qué alumno cursa qué asignatura.
 *
 * La tabla `matriculas` es una N:M pelada, con clave primaria compuesta y sin
 * modelo propio. Se trabaja a través de la relación `Alumno::asignaturas()`.
 *
 * Importa más de lo que parece: una asignatura matriculada sin resultados sale
 * en gris en la ficha del alumno —información— mientras que una asignatura no
 * matriculada sencillamente no existe para él. Matricular de más ensucia el
 * panel del centro de grises que nadie va a resolver.
 */
class MatriculaController extends BaseCentroController
{
    private const POR_PAGINA = 40;

    public function index(Request $peticion): View
    {
        $centroId = $this->centroId();

        $filtros = [
            'curso'      => $peticion->query('curso'),
            'asignatura' => $peticion->query('asignatura'),
            'q'          => trim((string) $peticion->query('q')),
        ];

        $alumnos = Alumno::where('centro_id', $centroId)
            ->where('activo', true)
            ->with(['grupo.curso', 'asignaturas'])
            // Por curso y no por grupo: en septiembre se matricula «3º ESO»
            // entero, y bajar a la letra del grupo obliga a repetir la consulta
            // una vez por cada letra.
            ->when($filtros['curso'], fn ($c, $v) => $c->whereHas('grupo', fn ($g) => $g->where('curso_id', $v)))
            ->when($filtros['q'] !== '', fn ($c) => $c->where(function ($b) use ($filtros) {
                $b->where('nombre', 'like', "%{$filtros['q']}%")
                  ->orWhere('apellidos', 'like', "%{$filtros['q']}%");
            }))
            ->when($filtros['asignatura'], fn ($c, $v) => $c->whereHas(
                'asignaturas', fn ($a) => $a->where('asignaturas.id', $v)
            ))
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        // Con `cursos` cargado, la vista puede preguntar a cada asignatura si
        // entra en el curso del alumno sin lanzar una consulta por fila.
        $asignaturas = Asignatura::where('centro_id', $centroId)
            ->with('cursos')->orderBy('denominacion')->get();

        $grupos = Grupo::whereHas('curso', fn ($c) => $c->where('centro_id', $centroId))
            ->with('curso')->get()->sortBy(fn ($g) => $g->nombre_completo);

        return view('centro.matriculas.index', [
            'alumnos'     => $alumnos,
            'filtros'     => $filtros,
            'asignaturas' => $asignaturas,
            // Los cursos filtran el listado; los grupos alimentan el alta
            // masiva, que se hace grupo a grupo.
            'cursos'      => Curso::where('centro_id', $centroId)->orderBy('denominacion')->get(),
            'grupos'      => $grupos,

            // Los dos mapas que ya usaba la pantalla de grupos de asignatura.
            // Aquí faltaban: el alta masiva ofrecía todas las materias para
            // todos los grupos, así que se podía elegir Física y Química para
            // 1º ESO y comerse un error rojo que era evitable.
            'cursoPorGrupo' => $grupos->mapWithKeys(fn ($g) => [$g->id => $g->curso_id])->all(),
            'planEstudios'  => $asignaturas->mapWithKeys(fn ($a) => [$a->id => $a->cursos->pluck('id')->all()])->all(),
        ]);
    }

    /** Matricula a un alumno concreto en una asignatura. */
    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'alumno_id'     => ['required', 'integer'],
            'asignatura_id' => ['required', 'integer'],
        ]);

        $alumno     = $this->alumno($datos['alumno_id']);
        $asignatura = $this->asignatura($datos['asignatura_id']);

        $alumno->loadMissing('grupo');

        if (! $asignatura->seImparteEn($alumno->grupo?->curso_id)) {
            return back()->with('error',
                "{$asignatura->denominacion} no está en el plan de estudios de "
                . ($alumno->grupo?->curso?->denominacion ?? 'su curso') . '.'
            );
        }

        if ($alumno->asignaturas()->where('asignaturas.id', $asignatura->id)->exists()) {
            return back()->with('aviso', "{$alumno->nombre_completo} ya estaba matriculado en {$asignatura->denominacion}.");
        }

        $alumno->asignaturas()->attach($asignatura->id);

        $this->auditar($peticion, 'matricula', $alumno->id, Auditoria::CREAR, null, [
            'alumno_id' => $alumno->id, 'asignatura_id' => $asignatura->id,
        ]);

        return back()->with('exito', "{$alumno->nombre_completo} matriculado en {$asignatura->denominacion}.");
    }

    /** Matricula de golpe a todo un grupo. Es lo que se usa en septiembre. */
    public function matricularGrupo(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'grupo_id'      => ['required', 'integer'],
            'asignatura_id' => ['required', 'integer'],
        ]);

        $asignatura = $this->asignatura($datos['asignatura_id']);

        $grupo = Grupo::with('curso')
            ->whereHas('curso', fn ($c) => $c->where('centro_id', $this->centroId()))
            ->findOrFail($datos['grupo_id']);

        if (! $asignatura->seImparteEn($grupo->curso_id)) {
            return back()->with('error',
                "{$asignatura->denominacion} no está en el plan de estudios de "
                . ($grupo->curso?->denominacion ?? 'ese curso') . '.'
            );
        }

        // Solo los que faltan, resuelto en la consulta en vez de con un EXISTS
        // por alumno: en un grupo de 30 eran 30 consultas para no hacer nada.
        $alumnos = Alumno::where('grupo_id', $grupo->id)
            ->where('activo', true)
            ->whereDoesntHave('asignaturas', fn ($a) => $a->where('asignaturas.id', $asignatura->id))
            ->get();
        $nuevos = 0;

        DB::transaction(function () use ($peticion, $alumnos, $asignatura, &$nuevos) {
            foreach ($alumnos as $alumno) {
                $alumno->asignaturas()->attach($asignatura->id);
                $nuevos++;
            }

            $this->auditar($peticion, 'matricula', $asignatura->id, Auditoria::CREAR, null, [
                'asignatura_id' => $asignatura->id,
                'alta_masiva'   => true,
                'alumnos'       => $nuevos,
            ]);
        });

        $mensaje = $nuevos === 0
            ? "Todo el grupo {$grupo->nombre_completo} ya estaba matriculado en {$asignatura->denominacion}."
            : "{$nuevos} alumnos de {$grupo->nombre_completo} matriculados en {$asignatura->denominacion}.";

        return back()->with($nuevos === 0 ? 'aviso' : 'exito', $mensaje);
    }

    /** Da de baja una matrícula. */
    public function destroy(Request $peticion, string $alumno, string $asignatura): RedirectResponse
    {
        $modeloAlumno     = $this->alumno($alumno);
        $modeloAsignatura = $this->asignatura($asignatura);

        // Una matrícula con resultados no se quita a la ligera: esas notas se
        // quedarían sin asignatura donde contar.
        $conResultados = $modeloAlumno->resultados()
            ->whereHas('evaluable.imparticion', fn ($q) => $q->where('asignatura_id', $modeloAsignatura->id))
            ->count();

        if ($conResultados > 0) {
            return back()->with('error',
                "No se puede quitar la matrícula: {$modeloAlumno->nombre_completo} tiene {$conResultados} "
                . "resultados en {$modeloAsignatura->denominacion}."
            );
        }

        $modeloAlumno->asignaturas()->detach($modeloAsignatura->id);

        $this->auditar($peticion, 'matricula', $modeloAlumno->id, Auditoria::ANULAR, [
            'alumno_id' => $modeloAlumno->id, 'asignatura_id' => $modeloAsignatura->id,
        ], null);

        return back()->with('exito', "Matrícula de {$modeloAsignatura->denominacion} retirada.");
    }

    // ------------------------------------------------------------- apoyo

    private function alumno(int|string $id): Alumno
    {
        return Alumno::where('centro_id', $this->centroId())->findOrFail($id);
    }

    private function asignatura(int|string $id): Asignatura
    {
        return Asignatura::where('centro_id', $this->centroId())->findOrFail($id);
    }
}
