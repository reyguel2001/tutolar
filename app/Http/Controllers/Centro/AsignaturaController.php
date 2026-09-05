<?php

namespace App\Http\Controllers\Centro;

use App\Models\Asignatura;
use App\Models\Auditoria;
use App\Models\Curso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo de asignaturas del centro.
 *
 * `horas_semanales` no es decorativo: es el peso con el que la asignatura entra
 * en el Índice de Rendimiento Global. Cambiarlo mueve el nivel de todos los
 * alumnos matriculados, así que la pantalla lo dice en voz alta.
 *
 * Los **cursos** son el plan de estudios: en cuáles entra esta materia. De ahí
 * salen las restricciones de las demás pantallas — no se puede asignar un
 * docente de Física y Química a un grupo de 1º ESO si el plan dice que ahí no
 * se da. Dejarlo vacío significa «en todos», para que el campo sea opcional.
 */
class AsignaturaController extends BaseCentroController
{
    private const POR_PAGINA = 25;

    private function consulta(): Builder
    {
        return Asignatura::query()->where('centro_id', $this->centroId());
    }

    public function index(Request $peticion): View
    {
        $q = trim((string) $peticion->query('q'));

        $asignaturas = $this->consulta()
            ->with('cursos')
            // Las dos dependencias que bloquean el borrado, contadas de una vez.
            // Antes solo se enseñaban las imparticiones, así que el usuario
            // pulsaba Eliminar y se encontraba con «todavía hay 108 matrículas»,
            // un número que la pantalla no le había enseñado nunca.
            ->withCount([
                'imparticiones',
                'alumnos as alumnos_count' => fn ($a) => $a->where('alumnos.activo', true),
            ])
            ->when($q !== '', fn ($c) => $c->where('denominacion', 'like', "%{$q}%"))
            ->orderBy('denominacion')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('centro.asignaturas.index', [
            'asignaturas' => $asignaturas,
            'q'           => $q,
            'hayCursos'   => $this->cursos()->isNotEmpty(),
        ]);
    }

    public function create(): View
    {
        return view('centro.asignaturas.form', [
            'asignatura' => new Asignatura(['horas_semanales' => 3]),
            'cursos'     => $this->cursos(),
        ]);
    }

    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate($this->reglas());

        $asignatura = Asignatura::create([
            'centro_id'       => $this->centroId(),
            'denominacion'    => $datos['denominacion'],
            'horas_semanales' => $datos['horas_semanales'],
        ]);

        $cursos = $this->sincronizarCursos($asignatura, $datos['cursos'] ?? []);

        $this->auditar($peticion, 'asignatura', $asignatura->id, Auditoria::CREAR, null,
            $this->instantanea($asignatura) + ['cursos' => $cursos]);

        return redirect()
            ->route('centro.asignaturas.index')
            ->with('exito', "Asignatura «{$asignatura->denominacion}» creada. {$this->resumenCursos($asignatura)}");
    }

    public function edit(string $asignatura): View
    {
        return view('centro.asignaturas.form', [
            'asignatura' => $this->consulta()->with('cursos')->findOrFail($asignatura),
            'cursos'     => $this->cursos(),
        ]);
    }

    public function update(Request $peticion, string $asignatura): RedirectResponse
    {
        $modelo = $this->consulta()->with('cursos')->findOrFail($asignatura);
        $antes  = $this->instantanea($modelo) + ['cursos' => $modelo->cursos->pluck('id')->all()];

        $datos = $peticion->validate($this->reglas($modelo->id));

        $modelo->update([
            'denominacion'    => $datos['denominacion'],
            'horas_semanales' => $datos['horas_semanales'],
        ]);

        $cursos = $this->sincronizarCursos($modelo, $datos['cursos'] ?? []);

        $this->auditar($peticion, 'asignatura', $modelo->id, Auditoria::MODIFICAR, $antes,
            $this->instantanea($modelo) + ['cursos' => $cursos]);

        $aviso = (int) ($antes['horas_semanales'] ?? 0) !== (int) $modelo->horas_semanales
            ? ' Al cambiar las horas semanales cambia el peso de esta asignatura en el índice global.'
            : '';

        return redirect()
            ->route('centro.asignaturas.index')
            ->with('exito', "Asignatura actualizada.{$aviso} {$this->resumenCursos($modelo->fresh('cursos'))}");
    }

    public function destroy(Request $peticion, string $asignatura): RedirectResponse
    {
        $modelo = $this->consulta()->findOrFail($asignatura);

        $bloqueo = $this->bloqueoPorDependencias([
            'grupos de asignatura' => $modelo->imparticiones()->count(),
            'matrículas'           => DB::table('matriculas')->where('asignatura_id', $modelo->id)->count(),
        ]);

        if ($bloqueo) {
            return back()->with('error', $bloqueo);
        }

        $this->auditar($peticion, 'asignatura', $modelo->id, Auditoria::ANULAR, $this->instantanea($modelo), null);
        $modelo->delete();

        return redirect()
            ->route('centro.asignaturas.index')
            ->with('exito', 'Asignatura eliminada.');
    }

    /** @return array<string, array<int, mixed>> */
    private function reglas(?int $ignorar = null): array
    {
        return [
            'denominacion' => [
                'required', 'string', 'max:120',
                Rule::unique('asignaturas', 'denominacion')
                    ->where('centro_id', $this->centroId())
                    ->ignore($ignorar),
            ],
            'horas_semanales' => ['required', 'integer', 'min:1', 'max:40'],
            'cursos'          => ['nullable', 'array'],
            'cursos.*'        => ['integer'],
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Curso> */
    private function cursos()
    {
        return Curso::where('centro_id', $this->centroId())->orderBy('denominacion')->get();
    }

    /**
     * Guarda el plan de estudios de la asignatura.
     *
     * Solo cursos del propio centro: un id colado en el formulario no puede
     * meter una materia en el plan de estudios de otro instituto.
     *
     * @param  array<int, int|string> $ids
     * @return array<int, int>
     */
    private function sincronizarCursos(Asignatura $asignatura, array $ids): array
    {
        $validos = Curso::where('centro_id', $this->centroId())
            ->whereIn('id', $ids)
            ->pluck('id');

        $asignatura->cursos()->sync($validos);

        return $validos->all();
    }

    private function resumenCursos(Asignatura $asignatura): string
    {
        $cursos = $asignatura->cursos;

        return $cursos->isEmpty()
            ? 'Sin cursos marcados: se podrá impartir en todos.'
            : 'Se imparte en ' . $cursos->pluck('denominacion')->join(', ', ' y ') . '.';
    }
}
