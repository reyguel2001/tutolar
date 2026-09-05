<?php

namespace App\Http\Controllers\Profesor;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\Evaluable;
use App\Models\Imparticion;
use App\Models\Notificacion;
use App\Models\NotificacionDestinatario;
use App\Models\Profesor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Avisos del profesorado a las familias.
 *
 * Cuatro tipos, y no son cuatro variantes del mismo mensaje: dos **crean** algo
 * y dos **cierran** algo.
 *
 *   EXAMEN_PROGRAMADO · TAREA_ASIGNADA
 *       El profesor anuncia algo que va a ocurrir. El aviso crea el `evaluable`
 *       —hasta ahora solo los creaba el seeder— y lo deja en estado NOTIFICADO.
 *
 *   RESULTADOS_EXAMEN · RESULTADOS_TAREA
 *       El profesor avisa de que ya hay nota de algo ya realizado. El evaluable
 *       pasa a PENDIENTE_REGISTRO, que es lo que hace que aparezca en el
 *       formulario de la familia.
 *
 * Con esto se cierra el ciclo del producto: el profesor avisa, la familia
 * registra, el motor recalcula el nivel.
 *
 * El destinatario NO es «el grupo». Es cada familia de cada alumno del grupo
 * que además esté matriculado en esa asignatura: un aviso de Matemáticas no
 * tiene por qué llegarle a quien no la cursa.
 */
class NotificacionController extends Controller
{
    private const POR_PAGINA = 20;

    private function profesor(): Profesor
    {
        $profesor = auth()->user()->profesor;
        abort_unless($profesor, 403, 'Esta cuenta no tiene perfil docente.');

        return $profesor;
    }

    /** Las imparticiones del profesor. Toda la pantalla parte de aquí. */
    private function imparticiones()
    {
        return Imparticion::with(['asignatura', 'grupo.curso'])
            ->where('profesor_id', $this->profesor()->id)
            ->get()
            ->sortBy(fn ($i) => [$i->grupo?->curso?->denominacion ?? '', $i->grupo?->denominacion ?? '', $i->asignatura?->denominacion ?? '']);
    }

    public function index(): View
    {
        $profesor = $this->profesor();

        $notificaciones = Notificacion::query()
            ->where('emisor_id', auth()->id())
            ->with(['grupo.curso', 'evaluable.imparticion.asignatura'])
            ->withCount([
                'destinatarios',
                'destinatarios as leidas_count'      => fn ($q) => $q->whereNotNull('leida_en'),
                'destinatarios as confirmadas_count' => fn ($q) => $q->whereNotNull('confirmada_en'),
            ])
            ->orderByDesc('emitida_en')
            ->paginate(self::POR_PAGINA);

        return view('profesor.notificaciones.index', [
            'profesor'       => $profesor,
            'notificaciones' => $notificaciones,
        ]);
    }

    public function create(): View
    {
        return view('profesor.notificaciones.form', [
            'catalogo'   => $this->catalogo(),
            'evaluables' => $this->evaluablesPorImparticion(),
        ]);
    }

    public function store(Request $peticion): RedirectResponse
    {
        $profesor = $this->profesor();

        $datos = $peticion->validate([
            'tipo'           => ['required', Rule::in(array_keys(Notificacion::EMITIBLES))],
            'imparticion_id' => ['required', 'integer'],
            'mensaje'        => ['nullable', 'string', 'max:1000'],

            // Solo para los tipos que crean el evaluable.
            'titulo'            => ['nullable', 'string', 'max:200'],
            'fecha_prevista'    => ['nullable', 'date'],
            'puntuacion_maxima' => ['nullable', 'numeric', 'min:1', 'max:999'],

            // Solo para los tipos que lo eligen.
            'evaluable_id' => ['nullable', 'integer'],
        ], [], [
            'imparticion_id'    => 'materia',
            'titulo'            => 'título',
            'fecha_prevista'    => 'fecha',
            'puntuacion_maxima' => 'puntuación máxima',
            'evaluable_id'      => 'examen o tarea',
        ]);

        // Aislamiento: la impartición tiene que ser de este profesor. Sin esto,
        // un id manipulado permitiría avisar a las familias de otro docente.
        $imparticion = Imparticion::with(['asignatura', 'grupo.curso'])
            ->where('profesor_id', $profesor->id)
            ->find($datos['imparticion_id']);

        abort_unless($imparticion, 403, 'Esa materia no es tuya.');

        $crea = str_starts_with(Notificacion::EMITIBLES[$datos['tipo']]['evaluable'], 'crear:');
        $tipoEvaluable = explode(':', Notificacion::EMITIBLES[$datos['tipo']]['evaluable'])[1];

        $resultado = DB::transaction(function () use ($peticion, $datos, $imparticion, $profesor, $crea, $tipoEvaluable) {
            $evaluable = $crea
                ? $this->crearEvaluable($datos, $imparticion, $profesor, $tipoEvaluable)
                : $this->elegirEvaluable($datos, $imparticion, $tipoEvaluable);

            $notificacion = Notificacion::create([
                'tipo'         => $datos['tipo'],
                'emisor_id'    => $peticion->user()->id,
                'evaluable_id' => $evaluable->id,
                'grupo_id'     => $imparticion->grupo_id,
                'titulo'       => $this->titular($datos['tipo'], $imparticion, $evaluable),
                'mensaje'      => $datos['mensaje'] ?? null,
                'emitida_en'   => now(),
            ]);

            $reparto = $this->repartir($notificacion, $imparticion);

            Auditoria::registrar(
                entidad: 'notificacion',
                entidadId: $notificacion->id,
                accion: Auditoria::CREAR,
                autorId: $peticion->user()->id,
                nuevo: [
                    'tipo'          => $notificacion->tipo,
                    'grupo_id'      => $imparticion->grupo_id,
                    'asignatura_id' => $imparticion->asignatura_id,
                    'evaluable_id'  => $evaluable->id,
                    'destinatarios' => $reparto['familias'],
                ],
                ip: $peticion->ip(),
            );

            return $reparto;
        });

        $mensaje = "Aviso enviado a {$resultado['familias']} "
            . ($resultado['familias'] === 1 ? 'familia' : 'familias')
            . " de {$imparticion->grupo?->nombre_completo}.";

        if ($resultado['sin_familia'] > 0) {
            $mensaje .= " {$resultado['sin_familia']} "
                . ($resultado['sin_familia'] === 1 ? 'alumno no tiene familia vinculada' : 'alumnos no tienen familia vinculada')
                . ', así que a esos no les llega.';
        }

        return redirect()
            ->route('profesor.notificaciones.index')
            ->with($resultado['sin_familia'] > 0 ? 'aviso' : 'exito', $mensaje);
    }

    // --------------------------------------------------------------- piezas

    /** @param array<string, mixed> $datos */
    private function crearEvaluable(array $datos, Imparticion $imparticion, Profesor $profesor, string $tipo): Evaluable
    {
        $faltan = [];
        if (blank($datos['titulo'] ?? null))         $faltan['titulo'] = 'Ponle un título al ' . ($tipo === 'EXAMEN' ? 'examen' : 'trabajo') . '.';
        if (blank($datos['fecha_prevista'] ?? null)) $faltan['fecha_prevista'] = 'Indica la fecha.';
        if (blank($datos['puntuacion_maxima'] ?? null)) $faltan['puntuacion_maxima'] = 'Indica sobre cuánto puntúa.';

        if ($faltan !== []) {
            throw ValidationException::withMessages($faltan);
        }

        return Evaluable::create([
            'imparticion_id'    => $imparticion->id,
            'tipo'              => $tipo,
            'titulo'            => $datos['titulo'],
            'descripcion'       => $datos['mensaje'] ?? null,
            'fecha_prevista'    => $datos['fecha_prevista'],
            'puntuacion_maxima' => $datos['puntuacion_maxima'],
            'estado'            => 'NOTIFICADO',
            'creado_por'        => $profesor->id,
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function elegirEvaluable(array $datos, Imparticion $imparticion, string $tipo): Evaluable
    {
        if (blank($datos['evaluable_id'] ?? null)) {
            throw ValidationException::withMessages([
                'evaluable_id' => 'Elige de qué examen o tarea son las notas.',
            ]);
        }

        $evaluable = Evaluable::where('imparticion_id', $imparticion->id)
            ->where('tipo', $tipo)
            ->find($datos['evaluable_id']);

        if (! $evaluable) {
            throw ValidationException::withMessages([
                'evaluable_id' => 'Esa prueba no es de esta materia y este grupo.',
            ]);
        }

        // A partir de aquí la familia puede apuntar la nota: es el estado que
        // hace que el evaluable aparezca en su formulario.
        $evaluable->update(['estado' => 'PENDIENTE_REGISTRO']);

        return $evaluable;
    }

    /**
     * Reparte el aviso: una fila por cada familia de cada alumno del grupo que
     * curse esa asignatura.
     *
     * @return array{familias: int, alumnos: int, sin_familia: int}
     */
    private function repartir(Notificacion $notificacion, Imparticion $imparticion): array
    {
        $alumnos = Alumno::where('grupo_id', $imparticion->grupo_id)
            ->where('activo', true)
            ->whereHas('asignaturas', fn ($a) => $a->where('asignaturas.id', $imparticion->asignatura_id))
            ->with('tutores')
            ->get();

        $filas       = [];
        $sinFamilia  = 0;

        foreach ($alumnos as $alumno) {
            if ($alumno->tutores->isEmpty()) {
                $sinFamilia++;
                continue;
            }

            foreach ($alumno->tutores as $tutor) {
                $filas[] = [
                    'notificacion_id' => $notificacion->id,
                    'tutor_legal_id'  => $tutor->id,
                    'alumno_id'       => $alumno->id,
                    'push_entregado'  => false,
                ];
            }
        }

        if ($filas !== []) {
            NotificacionDestinatario::insert($filas);
        }

        return [
            'familias'    => count($filas),
            'alumnos'     => $alumnos->count(),
            'sin_familia' => $sinFamilia,
        ];
    }

    private function titular(string $tipo, Imparticion $imparticion, Evaluable $evaluable): string
    {
        $materia = $imparticion->asignatura?->denominacion ?? 'la asignatura';

        return match ($tipo) {
            Notificacion::EXAMEN_PROGRAMADO => "Examen de {$materia}: {$evaluable->titulo}",
            Notificacion::TAREA_ASIGNADA    => "Tarea de {$materia}: {$evaluable->titulo}",
            Notificacion::RESULTADOS_EXAMEN => "Ya hay nota del examen de {$materia}: {$evaluable->titulo}",
            Notificacion::RESULTADOS_TAREA  => "Ejercicios de {$materia} corregidos: {$evaluable->titulo}",
            default                         => $evaluable->titulo,
        };
    }

    // --------------------------------------------------------------- catálogos

    /**
     * Curso → grupo → materia, en la forma que consume el formulario.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalogo(): array
    {
        return $this->imparticiones()->map(fn (Imparticion $i) => [
            'imparticion'    => $i->id,
            'curso_id'       => $i->grupo?->curso?->id,
            'curso'          => $i->grupo?->curso?->denominacion ?? 'Sin curso',
            'grupo_id'       => $i->grupo_id,
            'grupo'          => $i->grupo?->denominacion ?? '—',
            'grupo_completo' => $i->grupo?->nombre_completo ?? '—',
            'asignatura_id'  => $i->asignatura_id,
            'asignatura'     => $i->asignatura?->denominacion ?? '—',
        ])->values()->all();
    }

    /**
     * Los evaluables ya existentes de cada impartición, para los avisos de
     * resultados. Se agrupan por impartición y tipo para que el formulario
     * pueda filtrarlos sin volver al servidor.
     *
     * @return array<int, array<string, array<int, array<string, mixed>>>>
     */
    private function evaluablesPorImparticion(): array
    {
        $ids = $this->imparticiones()->pluck('id');

        return Evaluable::whereIn('imparticion_id', $ids)
            ->where('estado', '!=', 'ANULADO')
            ->orderByDesc('fecha_prevista')
            ->get()
            ->groupBy('imparticion_id')
            ->map(fn ($lista) => $lista->groupBy('tipo')->map(
                fn ($porTipo) => $porTipo->map(fn (Evaluable $e) => [
                    'id'     => $e->id,
                    'titulo' => $e->titulo,
                    'fecha'  => $e->fecha_prevista?->format('d/m/Y'),
                    'maxima' => (float) $e->puntuacion_maxima,
                ])->values()->all()
            )->all())
            ->all();
    }
}
