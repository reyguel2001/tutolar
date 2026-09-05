<?php

namespace App\Http\Controllers\Centro;

use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Notificacion;
use App\Models\Profesor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Grupos-clase.
 *
 * Un grupo cuelga de un curso («3º ESO») y el curso del centro. Como el menú no
 * tiene entrada propia para cursos, se gestionan desde esta misma pantalla: sin
 * al menos un curso no se puede crear ningún grupo, y mandar al usuario a
 * buscar dónde crearlo sería un callejón sin salida.
 */
class GrupoController extends BaseCentroController
{
    private function consulta(): Builder
    {
        return Grupo::query()->whereHas('curso', fn ($c) => $c->where('centro_id', $this->centroId()));
    }

    private function cursos()
    {
        return Curso::where('centro_id', $this->centroId())
            ->withCount('grupos')
            ->orderBy('denominacion')
            ->get();
    }

    private function profesores()
    {
        return Profesor::where('centro_id', $this->centroId())->orderBy('apellidos')->get();
    }

    public function index(Request $peticion): View
    {
        $grupos = $this->consulta()
            ->with(['curso', 'tutorGrupo'])
            ->withCount(['alumnos', 'imparticiones'])
            ->get()
            ->sortBy(fn ($g) => [$g->curso?->denominacion ?? '', $g->denominacion])
            ->groupBy(fn ($g) => $g->curso?->denominacion ?? 'Sin curso');

        return view('centro.grupos.index', [
            'porCurso' => $grupos,
            'cursos'   => $this->cursos(),
        ]);
    }

    public function create(): View
    {
        return view('centro.grupos.form', [
            'grupo'      => new Grupo(),
            'cursos'     => $this->cursos(),
            'profesores' => $this->profesores(),
        ]);
    }

    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate($this->reglas((int) $peticion->input('curso_id')));
        $this->comprobarCurso((int) $datos['curso_id']);
        $this->comprobarTutor($datos['tutor_grupo_id'] ?? null);

        $grupo = Grupo::create($datos);

        $this->auditar($peticion, 'grupo', $grupo->id, Auditoria::CREAR, null, $this->instantanea($grupo));

        return redirect()
            ->route('centro.grupos.index')
            ->with('exito', "Grupo «{$grupo->nombre_completo}» creado.");
    }

    public function edit(string $grupo): View
    {
        return view('centro.grupos.form', [
            'grupo'      => $this->consulta()->findOrFail($grupo),
            'cursos'     => $this->cursos(),
            'profesores' => $this->profesores(),
        ]);
    }

    public function update(Request $peticion, string $grupo): RedirectResponse
    {
        $modelo = $this->consulta()->findOrFail($grupo);
        $antes  = $this->instantanea($modelo);

        $datos = $peticion->validate($this->reglas((int) $peticion->input('curso_id'), $modelo->id));
        $this->comprobarCurso((int) $datos['curso_id']);
        $this->comprobarTutor($datos['tutor_grupo_id'] ?? null);

        $modelo->update($datos);

        $this->auditar($peticion, 'grupo', $modelo->id, Auditoria::MODIFICAR, $antes, $this->instantanea($modelo));

        return redirect()->route('centro.grupos.index')->with('exito', 'Grupo actualizado.');
    }

    public function destroy(Request $peticion, string $grupo): RedirectResponse
    {
        $modelo = $this->consulta()->findOrFail($grupo);

        $bloqueo = $this->bloqueoPorDependencias([
            'alumnos en el grupo'  => $modelo->alumnos()->count(),
            'grupos de asignatura' => $modelo->imparticiones()->count(),
            // Un aviso emitido es un hecho comunicado a familias: el esquema no
            // deja que desaparezca al borrar el grupo al que iba dirigido.
            'avisos enviados al grupo' => Notificacion::where('grupo_id', $modelo->id)->count(),
        ]);

        if ($bloqueo) {
            return back()->with('error', $bloqueo);
        }

        $this->auditar($peticion, 'grupo', $modelo->id, Auditoria::ANULAR, $this->instantanea($modelo), null);
        $modelo->delete();

        return redirect()->route('centro.grupos.index')->with('exito', 'Grupo eliminado.');
    }

    // ------------------------------------------------------------- cursos

    public function guardarCurso(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'denominacion' => [
                'required', 'string', 'max:80',
                Rule::unique('cursos', 'denominacion')->where('centro_id', $this->centroId()),
            ],
            'anio_academico' => ['required', 'string', 'max:9'],
        ]);

        $curso = Curso::create($datos + ['centro_id' => $this->centroId()]);

        $this->auditar($peticion, 'curso', $curso->id, Auditoria::CREAR, null, $this->instantanea($curso));

        return back()->with('exito', "Curso «{$curso->denominacion}» creado.");
    }

    public function eliminarCurso(Request $peticion, string $curso): RedirectResponse
    {
        $modelo = Curso::where('centro_id', $this->centroId())->findOrFail($curso);

        $bloqueo = $this->bloqueoPorDependencias(['grupos en el curso' => $modelo->grupos()->count()]);

        if ($bloqueo) {
            return back()->with('error', $bloqueo);
        }

        $this->auditar($peticion, 'curso', $modelo->id, Auditoria::ANULAR, $this->instantanea($modelo), null);
        $modelo->delete();

        return back()->with('exito', 'Curso eliminado.');
    }

    // ------------------------------------------------------------- apoyo

    /**
     * El esquema tiene un único (curso_id, denominacion): no puede haber dos
     * «3º ESO B». La regla lo dice aquí para que el usuario vea un mensaje en
     * vez de un error de base de datos.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglas(?int $cursoId = null, ?int $ignorar = null): array
    {
        return [
            'curso_id'     => ['required', 'integer'],
            'denominacion' => [
                'required', 'string', 'max:20',
                Rule::unique('grupos', 'denominacion')
                    ->where('curso_id', $cursoId)
                    ->ignore($ignorar),
            ],
            'tutor_grupo_id' => ['nullable', 'integer'],
        ];
    }

    private function comprobarCurso(int $cursoId): void
    {
        abort_unless(
            Curso::where('centro_id', $this->centroId())->whereKey($cursoId)->exists(),
            403,
            'Ese curso no es de tu centro.',
        );
    }

    private function comprobarTutor(int|string|null $profesorId): void
    {
        if (! $profesorId) {
            return;
        }

        abort_unless(
            Profesor::where('centro_id', $this->centroId())->whereKey($profesorId)->exists(),
            403,
            'Ese profesor no es de tu centro.',
        );
    }
}
