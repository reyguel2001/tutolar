<?php

namespace App\Http\Controllers\Centro;

use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\Auditoria;
use App\Models\CodigoVinculacion;
use App\Models\Grupo;
use App\Models\Tutela;
use App\Support\Nivel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AlumnoController extends BaseCentroController
{
    /** Cuántas filas por página. La tabla es la pantalla que justifica la web. */
    private const POR_PAGINA = 15;

    private function buscar(string $id): Alumno
    {
        return Alumno::where('centro_id', $this->centroId())->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $centroId = $this->centroId();

        $consulta = Alumno::with(['grupo.curso', 'asignaturas', 'tutores', 'resultados.evaluable.imparticion.asignatura'])
            ->where('centro_id', $centroId);

        // Por defecto solo los activos; las bajas se piden explícitamente.
        $verBajas = $request->query('bajas') === '1';
        if (! $verBajas) {
            $consulta->where('activo', true);
        }

        if ($buscar = trim((string) $request->query('q'))) {
            $consulta->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellidos', 'like', "%{$buscar}%");
            });
        }

        if ($grupoId = $request->query('grupo')) {
            $consulta->where('grupo_id', $grupoId);
        }

        // El rendimiento se calcula en PHP, así que el filtro por estado y la
        // ordenación se aplican después de resolver la colección. Con 412
        // alumnos es perfectamente asumible; a partir de unos miles convendría
        // leer el índice ya calculado de `indices_rendimiento`.
        $alumnos = $consulta->orderBy('apellidos')->get()->map(function (Alumno $alumno) {
            $r = $alumno->rendimiento();

            return [
                'modelo'      => $alumno,
                'irg'         => $r['global'],
                'asignaturas' => $r['asignaturas'],
                'en_bajo'     => collect($r['asignaturas'])
                    ->filter(fn ($a) => $a['ira']['nivel'] === Nivel::BAJO)
                    ->map(fn ($a) => $a['asignatura']->denominacion)
                    ->values()
                    ->all(),
                'vinculada'   => $alumno->tutores->isNotEmpty(),
            ];
        });

        $estado = $request->query('estado');
        if ($estado && in_array($estado, [Nivel::BAJO, Nivel::MEDIO, Nivel::ALTO, Nivel::SIN_DATOS], true)) {
            $alumnos = $alumnos->filter(fn ($a) => $a['irg']['nivel'] === $estado)->values();
        }

        // De peor a mejor, con las fichas sin datos al final.
        $alumnos = $alumnos->sortBy(function ($a) {
            return $a['irg']['valor'] ?? PHP_INT_MAX;
        })->values();

        $pagina = max(1, (int) $request->query('pagina', 1));
        $total  = $alumnos->count();
        $paginados = $alumnos->slice(($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA)->values();

        return view('centro.alumnos', [
            'alumnos'    => $paginados,
            'total'      => $total,
            'pagina'     => $pagina,
            'paginas'    => max(1, (int) ceil($total / self::POR_PAGINA)),
            'porPagina'  => self::POR_PAGINA,
            'verBajas'   => $verBajas,
            'filtros'    => ['q' => $request->query('q'), 'estado' => $estado, 'grupo' => $grupoId],
            'grupos'     => $this->grupos(),
        ]);
    }

    public function show(string $alumno): View
    {
        $modelo = $this->buscar($alumno);

        $modelo->load(['grupo.curso', 'tutores', 'resultados.evaluable.imparticion.asignatura', 'resultados.autor']);

        return view('centro.ficha', [
            'alumno'      => $modelo,
            'rendimiento' => $modelo->rendimiento(),
            // Los códigos que todavía sirven: sin usar y sin caducar.
            'codigos'     => CodigoVinculacion::where('alumno_id', $modelo->id)
                                ->whereNull('usado_en')
                                ->where('caduca_en', '>', now())
                                ->latest('caduca_en')
                                ->get(),
            'movimientos' => $modelo->resultados()
                ->with(['evaluable.imparticion.asignatura', 'autor'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('centro.alumnos-form', [
            'alumno'      => new Alumno(['activo' => true]),
            'grupos'      => $this->grupos(),
            'asignaturas' => $this->asignaturas(),
        ] + $this->planDeEstudios());
    }

    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate($this->reglas());
        $this->comprobarGrupo((int) $datos['grupo_id']);

        $alumno = DB::transaction(function () use ($peticion, $datos) {
            $alumno = Alumno::create([
                'centro_id'        => $this->centroId(),
                'grupo_id'         => $datos['grupo_id'],
                'nombre'           => $datos['nombre'],
                'apellidos'        => $datos['apellidos'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'activo'           => true,
            ]);

            $this->sincronizarAsignaturas($alumno, $datos['asignaturas'] ?? [], (int) $datos['grupo_id']);
            $this->sincronizarFoto($peticion, $alumno);
            $this->auditar($peticion, 'alumno', $alumno->id, Auditoria::CREAR, null, $this->instantanea($alumno));

            return $alumno;
        });

        return redirect()
            ->route('centro.alumno', $alumno)
            ->with('exito', "Alumno «{$alumno->nombre_completo}» dado de alta.");
    }

    public function edit(string $alumno): View
    {
        return view('centro.alumnos-form', [
            'alumno'      => $this->buscar($alumno)->load('asignaturas'),
            'grupos'      => $this->grupos(),
            'asignaturas' => $this->asignaturas(),
        ] + $this->planDeEstudios());
    }

    public function update(Request $peticion, string $alumno): RedirectResponse
    {
        $modelo = $this->buscar($alumno);
        $antes  = $this->instantanea($modelo);

        $datos = $peticion->validate($this->reglas());
        $this->comprobarGrupo((int) $datos['grupo_id']);

        DB::transaction(function () use ($peticion, $modelo, $datos, $antes) {
            $modelo->update([
                'grupo_id'         => $datos['grupo_id'],
                'nombre'           => $datos['nombre'],
                'apellidos'        => $datos['apellidos'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
            ]);

            $this->sincronizarAsignaturas($modelo, $datos['asignaturas'] ?? [], (int) $datos['grupo_id']);
            $this->sincronizarFoto($peticion, $modelo);
            $this->auditar($peticion, 'alumno', $modelo->id, Auditoria::MODIFICAR, $antes, $this->instantanea($modelo));
        });

        return redirect()->route('centro.alumno', $modelo)->with('exito', 'Ficha del alumno actualizada.');
    }

    /**
     * Genera el código con el que una familia se registra y queda vinculada a
     * este alumno.
     *
     * Es la autorización del centro puesta por escrito: quien tiene el código
     * puede crear una cuenta atada a este menor, y solo a este. Por eso se
     * genera desde aquí y no desde el formulario de registro.
     */
    public function generarCodigo(Request $peticion, string $alumno): RedirectResponse
    {
        $modelo = $this->buscar($alumno);

        if (! Tutela::hayHueco($modelo->id)) {
            return back()->with('error',
                "{$modelo->nombre_completo} ya tiene " . Tutela::MAX_POR_ALUMNO
                . ' familias vinculadas. Retira una antes de generar otro código.');
        }

        $codigo = CodigoVinculacion::generarPara($modelo);

        $this->auditar($peticion, 'codigo_vinculacion', $modelo->id, Auditoria::CREAR, null, [
            'alumno_id' => $modelo->id,
            'caduca_en' => $codigo->caduca_en->toDateTimeString(),
        ]);

        return back()->with('exito', "Código generado: {$codigo->bonito()}. "
            . 'Vale ' . CodigoVinculacion::DIAS_VALIDEZ . ' días y para una sola cuenta.');
    }

    /** Baja y alta lógicas. Un alumno que se va del centro no se borra. */
    public function alternarActivo(Request $peticion, string $alumno): RedirectResponse
    {
        $modelo = $this->buscar($alumno);
        $activo = ! $modelo->activo;

        $modelo->update(['activo' => $activo]);

        $this->auditar(
            $peticion, 'alumno', $modelo->id,
            $activo ? Auditoria::MODIFICAR : Auditoria::ANULAR,
            ['activo' => ! $activo], ['activo' => $activo],
        );

        return back()->with('exito', $activo
            ? "{$modelo->nombre_completo} vuelve a estar activo."
            : "{$modelo->nombre_completo} dado de baja. Su histórico se conserva.");
    }

    public function destroy(Request $peticion, string $alumno): RedirectResponse
    {
        $modelo = $this->buscar($alumno);

        // Las tutelas cuentan todas, activas o no: el esquema las protege con
        // `restrictOnDelete` porque son la traza de quién pudo ver a este menor.
        $bloqueo = $this->bloqueoPorDependencias([
            'resultados registrados' => $modelo->resultados()->count(),
            'familias vinculadas (incluidas las ya retiradas)' =>
                DB::table('tutelas')->where('alumno_id', $modelo->id)->count(),
        ]);

        if ($bloqueo) {
            return back()->with('error', $bloqueo . ' Si el alumno deja el centro, dale de baja en vez de eliminarlo.');
        }

        $this->auditar($peticion, 'alumno', $modelo->id, Auditoria::ANULAR, $this->instantanea($modelo), null);
        $modelo->delete();

        return redirect()->route('centro.alumnos')->with('exito', 'Alumno eliminado.');
    }

    // ------------------------------------------------------------- apoyo

    /** @return array<string, array<int, mixed>> */
    private function reglas(): array
    {
        return [
            'nombre'           => ['required', 'string', 'max:100'],
            'apellidos'        => ['required', 'string', 'max:150'],
            'fecha_nacimiento' => ['required', 'date', 'before:today', 'after:1990-01-01'],
            'grupo_id'         => ['required', 'integer'],
            'asignaturas'      => ['nullable', 'array'],
            'asignaturas.*'    => ['integer'],
            'foto'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Guarda, cambia o quita la foto del alumno.
     *
     * El nombre del archivo lo pone la aplicación, nunca el que venga en la
     * subida: así un «virus.php» renombrado a «.jpg» se guarda con extensión de
     * imagen y nombre nuestro, y no hay forma de colar una ruta.
     */
    private function sincronizarFoto(Request $peticion, Alumno $alumno): void
    {
        $anterior = $alumno->foto_url;

        if ($peticion->hasFile('foto')) {
            $nombre = 'a' . $alumno->id . '-' . Str::random(16) . '.' . $peticion->file('foto')->extension();
            $peticion->file('foto')->storeAs('', $nombre, 'avatares');
            $alumno->update(['foto_url' => $nombre]);
        } elseif ($peticion->boolean('quitar_foto')) {
            $alumno->update(['foto_url' => null]);
        } else {
            return;
        }

        // El archivo viejo se borra solo si era nuestro: un `foto_url` heredado
        // con barras o con http es una referencia externa, no un archivo local.
        if ($anterior && ! str_contains($anterior, '/') && ! str_contains($anterior, '\\')) {
            Storage::disk('avatares')->delete($anterior);
        }
    }

    /**
     * Matricula al alumno en las asignaturas marcadas.
     *
     * Dos filtros: que sean del centro —un id colado no puede matricular a
     * nadie en la asignatura de otro instituto— y que entren en el plan de
     * estudios de su curso. Lo segundo se avisa en vez de descartarse en
     * silencio: si alguien marca Física y Química para un alumno de 1º ESO,
     * conviene que sepa por qué no se ha guardado.
     *
     * @param array<int, int|string> $ids
     */
    private function sincronizarAsignaturas(Alumno $alumno, array $ids, int $grupoId): void
    {
        $delCentro = Asignatura::where('centro_id', $this->centroId())
            ->whereIn('id', $ids)
            ->with('cursos')
            ->get();

        $curso = Grupo::with('curso')->find($grupoId)?->curso;

        $fuera = $delCentro->reject(fn (Asignatura $a) => $a->seImparteEn($curso?->id));

        if ($fuera->isNotEmpty()) {
            throw ValidationException::withMessages([
                'asignaturas' => $fuera->pluck('denominacion')->join(', ', ' y ')
                    . ' no ' . ($fuera->count() === 1 ? 'entra' : 'entran')
                    . ' en el plan de estudios de ' . ($curso?->denominacion ?? 'ese curso') . '.',
            ]);
        }

        $alumno->asignaturas()->sync($delCentro->pluck('id'));
    }

    private function comprobarGrupo(int $grupoId): void
    {
        abort_unless(
            Grupo::whereHas('curso', fn ($c) => $c->where('centro_id', $this->centroId()))
                ->whereKey($grupoId)->exists(),
            403,
            'Ese grupo no es de tu centro.',
        );
    }

    private function grupos()
    {
        return Grupo::whereHas('curso', fn ($q) => $q->where('centro_id', $this->centroId()))
            ->with('curso')->get()->sortBy(fn ($g) => $g->nombre_completo);
    }

    private function asignaturas()
    {
        return Asignatura::where('centro_id', $this->centroId())
            ->with('cursos')
            ->orderBy('denominacion')
            ->get();
    }

    /**
     * A qué curso pertenece cada grupo y en qué cursos entra cada asignatura.
     * El formulario lo usa para ocultar las materias que no tocan en cuanto se
     * elige el grupo.
     *
     * @return array<string, array<int, mixed>>
     */
    private function planDeEstudios(): array
    {
        return [
            'cursoPorGrupo' => $this->grupos()->mapWithKeys(fn ($g) => [$g->id => $g->curso_id])->all(),
            'planEstudios'  => $this->asignaturas()
                                ->mapWithKeys(fn ($a) => [$a->id => $a->cursos->pluck('id')->all()])
                                ->all(),
        ];
    }
}
