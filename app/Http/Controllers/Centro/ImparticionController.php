<?php

namespace App\Http\Controllers\Centro;

use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Imparticion;
use App\Models\Profesor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Grupos de asignatura: quién imparte qué, a qué grupo.
 *
 * En el esquema se llama `imparticiones`. La pareja (asignatura, grupo) es
 * única —no hay dos docentes para lo mismo—, y de aquí cuelga todo lo demás:
 * los exámenes se crean sobre una impartición, y los resultados sobre ellos.
 *
 * OJO AL TRIÁNGULO. Esta tabla y `matriculas` son independientes y las dos
 * hacen falta:
 *
 *   · La impartición dice **quién da la clase**. Sin ella no hay exámenes.
 *   · La matrícula dice **quién la recibe**. Sin ella el alumno no ve nada y su
 *     nivel no se mueve.
 *
 * Crear una sin la otra deja un hueco silencioso: un profesor podía programar
 * exámenes de una asignatura que ningún alumno cursaba. Por eso esta pantalla
 * enseña siempre cuántos alumnos del grupo están matriculados, ofrece
 * matricularlos en el propio formulario del alta y avisa cuando la cifra es
 * cero.
 */
class ImparticionController extends BaseCentroController
{
    private const POR_PAGINA = 25;

    private function consulta(): Builder
    {
        return Imparticion::query()
            ->whereHas('asignatura', fn ($a) => $a->where('centro_id', $this->centroId()));
    }

    public function index(Request $peticion): View
    {
        $filtros = [
            'curso'      => $peticion->query('curso'),
            'asignatura' => $peticion->query('asignatura'),
            'profesor'   => $peticion->query('profesor'),
        ];

        $imparticiones = $this->consulta()
            ->with(['asignatura', 'grupo.curso', 'profesor'])
            ->withCount('evaluables')
            // Se filtra por curso y no por grupo: un centro tiene pocos cursos y
            // muchos grupos, así que «3º ESO» acota de verdad mientras que
            // «3º ESO B» casi siempre deja una o dos filas.
            ->when($filtros['curso'], fn ($c, $v) => $c->whereHas('grupo', fn ($g) => $g->where('curso_id', $v)))
            ->when($filtros['asignatura'], fn ($c, $v) => $c->where('asignatura_id', $v))
            ->when($filtros['profesor'], fn ($c, $v) => $c->where('profesor_id', $v))
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('centro.imparticiones.index', [
            'imparticiones' => $imparticiones,
            'filtros'       => $filtros,
            'cobertura'     => $this->cobertura($imparticiones->getCollection()),
        ] + $this->catalogos());
    }

    public function create(): View
    {
        return view('centro.imparticiones.form', ['imparticion' => new Imparticion()] + $this->catalogos());
    }

    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate($this->reglas($peticion));
        $this->comprobarPertenencia($datos);
        $this->comprobarPlanDeEstudios($datos);

        $matricular = $peticion->boolean('matricular_grupo');
        $nuevas     = 0;

        $imparticion = DB::transaction(function () use ($peticion, $datos, $matricular, &$nuevas) {
            $imparticion = Imparticion::create($datos);

            if ($matricular) {
                $nuevas = $this->matricularAlGrupo($peticion, (int) $datos['grupo_id'], (int) $datos['asignatura_id']);
            }

            $this->auditar($peticion, 'imparticion', $imparticion->id, Auditoria::CREAR, null, $this->instantanea($imparticion));

            return $imparticion;
        });

        [$clave, $mensaje] = $this->mensajeDeAlta($imparticion, $matricular, $nuevas);

        return redirect()->route('centro.imparticiones.index')->with($clave, $mensaje);
    }

    public function edit(string $imparticion): View
    {
        return view('centro.imparticiones.form', [
            'imparticion' => $this->consulta()->findOrFail($imparticion),
        ] + $this->catalogos());
    }

    public function update(Request $peticion, string $imparticion): RedirectResponse
    {
        $modelo = $this->consulta()->findOrFail($imparticion);
        $antes  = $this->instantanea($modelo);

        $datos = $peticion->validate($this->reglas($peticion, $modelo->id));
        $this->comprobarPertenencia($datos);
        $this->comprobarPlanDeEstudios($datos);

        $modelo->update($datos);

        $this->auditar($peticion, 'imparticion', $modelo->id, Auditoria::MODIFICAR, $antes, $this->instantanea($modelo));

        return redirect()
            ->route('centro.imparticiones.index')
            ->with('exito', 'Grupo de asignatura actualizado.');
    }

    public function destroy(Request $peticion, string $imparticion): RedirectResponse
    {
        $modelo = $this->consulta()->findOrFail($imparticion);

        $bloqueo = $this->bloqueoPorDependencias([
            'exámenes o tareas creados' => $modelo->evaluables()->count(),
        ]);

        if ($bloqueo) {
            return back()->with('error', $bloqueo);
        }

        $this->auditar($peticion, 'imparticion', $modelo->id, Auditoria::ANULAR, $this->instantanea($modelo), null);
        $modelo->delete();

        return redirect()
            ->route('centro.imparticiones.index')
            ->with('exito', 'Grupo de asignatura eliminado.');
    }

    /**
     * Matricula al grupo entero desde el propio listado.
     *
     * Es el atajo para el hueco que esta pantalla ahora hace visible: la clase
     * existe y no la cursa nadie.
     */
    public function matricularGrupo(Request $peticion, string $imparticion): RedirectResponse
    {
        $modelo = $this->consulta()->with(['asignatura', 'grupo.curso'])->findOrFail($imparticion);

        $nuevas = DB::transaction(fn () => $this->matricularAlGrupo(
            $peticion, (int) $modelo->grupo_id, (int) $modelo->asignatura_id,
        ));

        if ($nuevas === 0) {
            return back()->with('aviso', 'Todos los alumnos de ese grupo ya estaban matriculados.');
        }

        return back()->with('exito', "{$nuevas} alumnos de {$modelo->grupo?->nombre_completo} "
            . "matriculados en {$modelo->asignatura?->denominacion}.");
    }

    // ------------------------------------------------------------- apoyo

    /**
     * Cuántos alumnos del grupo cursan la asignatura de cada impartición, y
     * cuántos hay en el grupo.
     *
     * Dos consultas agregadas para toda la página en vez de dos por fila. La
     * clave del mapa es «grupo-asignatura», que es la pareja que define la
     * cobertura de una impartición.
     *
     * @param  Collection<int, Imparticion> $imparticiones
     * @return array{matriculados: array<string, int>, tamano: array<int, int>}
     */
    private function cobertura(Collection $imparticiones): array
    {
        if ($imparticiones->isEmpty()) {
            return ['matriculados' => [], 'tamano' => []];
        }

        $grupos      = $imparticiones->pluck('grupo_id')->unique()->all();
        $asignaturas = $imparticiones->pluck('asignatura_id')->unique()->all();

        $matriculados = DB::table('matriculas')
            ->join('alumnos', 'alumnos.id', '=', 'matriculas.alumno_id')
            ->where('alumnos.activo', true)
            ->whereIn('alumnos.grupo_id', $grupos)
            ->whereIn('matriculas.asignatura_id', $asignaturas)
            ->groupBy('alumnos.grupo_id', 'matriculas.asignatura_id')
            ->selectRaw('alumnos.grupo_id as g, matriculas.asignatura_id as a, count(*) as total')
            ->get()
            ->mapWithKeys(fn ($f) => ["{$f->g}-{$f->a}" => (int) $f->total])
            ->all();

        $tamano = Alumno::whereIn('grupo_id', $grupos)
            ->where('activo', true)
            ->groupBy('grupo_id')
            ->selectRaw('grupo_id, count(*) as total')
            ->pluck('total', 'grupo_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        return ['matriculados' => $matriculados, 'tamano' => $tamano];
    }

    /**
     * Matricula a todos los alumnos activos del grupo que aún no lo estén.
     *
     * No comprueba el plan de estudios porque quien llama ya lo ha hecho: si la
     * impartición existe, esa materia entra en ese curso.
     *
     * @return int cuántas matrículas nuevas se han creado
     */
    private function matricularAlGrupo(Request $peticion, int $grupoId, int $asignaturaId): int
    {
        $pendientes = Alumno::where('grupo_id', $grupoId)
            ->where('centro_id', $this->centroId())
            ->where('activo', true)
            ->whereDoesntHave('asignaturas', fn ($a) => $a->where('asignaturas.id', $asignaturaId))
            ->get();

        foreach ($pendientes as $alumno) {
            $alumno->asignaturas()->attach($asignaturaId);
        }

        if ($pendientes->isNotEmpty()) {
            $this->auditar($peticion, 'matricula', $asignaturaId, Auditoria::CREAR, null, [
                'asignatura_id' => $asignaturaId,
                'grupo_id'      => $grupoId,
                'alta_masiva'   => true,
                'alumnos'       => $pendientes->count(),
            ]);
        }

        return $pendientes->count();
    }

    /**
     * El mensaje de después del alta dice si el triángulo quedó cerrado.
     *
     * @return array{0: string, 1: string}
     */
    private function mensajeDeAlta(Imparticion $imparticion, bool $matricular, int $nuevas): array
    {
        $imparticion->loadMissing(['asignatura', 'grupo.curso']);
        $materia = $imparticion->asignatura?->denominacion;
        $grupo   = $imparticion->grupo?->nombre_completo;

        if ($matricular) {
            return ['exito', $nuevas === 0
                ? "Grupo de asignatura creado. Todos los alumnos de {$grupo} ya cursaban {$materia}."
                : "Grupo de asignatura creado y {$nuevas} alumnos de {$grupo} matriculados en {$materia}."];
        }

        $yaMatriculados = Alumno::where('grupo_id', $imparticion->grupo_id)
            ->where('activo', true)
            ->whereHas('asignaturas', fn ($a) => $a->where('asignaturas.id', $imparticion->asignatura_id))
            ->count();

        if ($yaMatriculados > 0) {
            return ['exito', 'Grupo de asignatura creado.'];
        }

        // El hueco: hay clase, pero no la cursa nadie. Es lo que permitía que un
        // profesor programara exámenes que ningún alumno iba a ver.
        return ['aviso', "Grupo de asignatura creado, pero ningún alumno de {$grupo} cursa {$materia}: "
            . 'no verán los exámenes ni contará para su nivel. Puedes matricularlos desde la '
            . 'columna «Alumnos» del listado.'];
    }

    /** @return array<string, mixed> */
    private function catalogos(): array
    {
        return [
            'asignaturas' => Asignatura::where('centro_id', $this->centroId())->orderBy('denominacion')->get(),
            'profesores'  => Profesor::where('centro_id', $this->centroId())->orderBy('apellidos')->get(),
            // Los cursos alimentan el filtro del listado; los grupos, el formulario.
            'cursos'      => Curso::where('centro_id', $this->centroId())->orderBy('denominacion')->get(),
            'grupos'      => $grupos = Grupo::whereHas('curso', fn ($c) => $c->where('centro_id', $this->centroId()))
                                ->with('curso')->get()
                                ->sortBy(fn ($g) => $g->nombre_completo),

            // Para el formulario: a qué curso pertenece cada grupo y en qué
            // cursos entra cada asignatura. Con eso el navegador puede ofrecer
            // solo las combinaciones que permite el plan de estudios.
            'cursoPorGrupo' => $grupos->mapWithKeys(fn ($g) => [$g->id => $g->curso_id])->all(),
            'planEstudios'  => Asignatura::where('centro_id', $this->centroId())
                                ->with('cursos')->get()
                                ->mapWithKeys(fn ($a) => [$a->id => $a->cursos->pluck('id')->all()])
                                ->all(),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function reglas(Request $peticion, ?int $ignorar = null): array
    {
        return [
            'profesor_id'   => ['required', 'integer'],
            'asignatura_id' => [
                'required', 'integer',
                Rule::unique('imparticiones', 'asignatura_id')
                    ->where('grupo_id', (int) $peticion->input('grupo_id'))
                    ->ignore($ignorar),
            ],
            'grupo_id'      => ['required', 'integer'],
        ];
    }

    /** @param array<string, mixed> $datos */
    private function comprobarPertenencia(array $datos): void
    {
        $centroId = $this->centroId();

        abort_unless(
            Asignatura::where('centro_id', $centroId)->whereKey($datos['asignatura_id'])->exists()
            && Profesor::where('centro_id', $centroId)->whereKey($datos['profesor_id'])->exists()
            && Grupo::whereHas('curso', fn ($c) => $c->where('centro_id', $centroId))->whereKey($datos['grupo_id'])->exists(),
            403,
            'Asignatura, profesor y grupo tienen que ser de tu centro.',
        );
    }

    /**
     * El plan de estudios manda: no se puede poner a alguien a impartir Física
     * y Química en 1º ESO si esa materia no entra en 1º ESO.
     *
     * Es error de validación y no 403 porque no es un intento de colarse: es
     * una combinación que el centro puede querer y solo tiene que decidir si
     * cambia el plan o cambia el grupo.
     *
     * @param array<string, mixed> $datos
     */
    private function comprobarPlanDeEstudios(array $datos): void
    {
        $grupo      = Grupo::with('curso')->find($datos['grupo_id']);
        $asignatura = Asignatura::with('cursos')->find($datos['asignatura_id']);

        if (! $grupo || ! $asignatura || $asignatura->seImparteEn($grupo->curso_id)) {
            return;
        }

        throw ValidationException::withMessages([
            'asignatura_id' => "«{$asignatura->denominacion}» no está en el plan de estudios de "
                . "{$grupo->curso?->denominacion}. Si debería estarlo, márcalo en la ficha de la asignatura.",
        ]);
    }
}
