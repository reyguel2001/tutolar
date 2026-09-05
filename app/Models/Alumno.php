<?php

namespace App\Models;

use App\Models\Concerns\TieneAvatar;
use App\Services\RendimientoService;
use App\Support\Avatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alumno extends Model
{
    use TieneAvatar;

    /** El alumnado son menores: su silueta es la de un niño, no la de un adulto. */
    protected string $tipoAvatar    = Avatar::NINO;
    protected string $columnaAvatar = 'foto_url';

    protected $table = 'alumnos';

    protected $fillable = [
        'centro_id', 'grupo_id', 'nombre', 'apellidos',
        'fecha_nacimiento', 'foto_url', 'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo'           => 'boolean',
    ];

    public function centro(): BelongsTo  { return $this->belongsTo(Centro::class); }
    public function grupo(): BelongsTo   { return $this->belongsTo(Grupo::class); }
    public function resultados(): HasMany { return $this->hasMany(Resultado::class); }

    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'matriculas');
    }

    public function tutores(): BelongsToMany
    {
        return $this->belongsToMany(TutorLegal::class, 'tutelas')
            ->withPivot(['parentesco', 'activa'])
            ->wherePivot('activa', true);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellidos}";
    }

    public function getInicialesAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->nombre, 0, 1) . mb_substr($this->apellidos, 0, 1));
    }

    public function getEdadAttribute(): int
    {
        return $this->fecha_nacimiento->age;
    }

    /**
     * Calcula el rendimiento completo del alumno.
     *
     * Toda la lógica vive en RendimientoService; aquí solo se recogen los datos
     * y se les da forma. Si un resultado tiene versión verificada y declarada,
     * prevalece la verificada (RN-01).
     *
     * @return array{
     *   global: array{valor: float|null, color: string, etiqueta: string},
     *   asignaturas: array<int, array<string, mixed>>,
     *   por_tipo: array<string, array{media: float|null, n: int}>,
     *   total_resultados: int
     * }
     */
    public function rendimiento(?RendimientoService $motor = null): array
    {
        $centro = $this->centro;
        $motor ??= new RendimientoService(
            (float) $centro->peso_examenes,
            (float) $centro->peso_tareas,
            (float) $centro->umbral_bajo,
            (float) $centro->umbral_alto,
        );

        $resultados = $this->resultados()
            ->with('evaluable.imparticion.asignatura', 'evaluable.imparticion.profesor')
            ->get();

        // RN-01: si existe valor verificado y declarado para el mismo evaluable,
        // prevalece el verificado.
        $porEvaluable = [];
        foreach ($resultados as $r) {
            $clave = $r->evaluable_id;
            if (! isset($porEvaluable[$clave]) || $r->estaVerificado()) {
                $porEvaluable[$clave] = $r;
            }
        }

        $porAsignatura = [];
        foreach ($porEvaluable as $r) {
            $evaluable = $r->evaluable;
            if (! $evaluable || ! $evaluable->imparticion) {
                continue;
            }
            $asignatura = $evaluable->imparticion->asignatura;

            $porAsignatura[$asignatura->id]['asignatura'] = $asignatura;
            $porAsignatura[$asignatura->id]['profesor']   = $evaluable->imparticion->profesor;
            $porAsignatura[$asignatura->id]['resultados'][] = [
                'nota' => $motor->normalizar((float) $r->puntuacion_obtenida, (float) $evaluable->puntuacion_maxima),
                'dias' => (int) $r->created_at->diffInDays(now()),
                'tipo' => $evaluable->tipo,
            ];
        }

        // Las asignaturas matriculadas sin ningún resultado también aparecen:
        // en gris, que es información, no ausencia de ella.
        foreach ($this->asignaturas as $asignatura) {
            if (! isset($porAsignatura[$asignatura->id])) {
                $porAsignatura[$asignatura->id] = [
                    'asignatura' => $asignatura,
                    'profesor'   => null,
                    'resultados' => [],
                ];
            }
        }

        $filas = [];
        $paraGlobal = [];
        $todos = [];       // el conjunto completo, para el desglose por tipo

        foreach ($porAsignatura as $datos) {
            $ira = $motor->calcularIra($datos['resultados'] ?? []);

            $todos = array_merge($todos, $datos['resultados'] ?? []);

            $filas[] = [
                'asignatura' => $datos['asignatura'],
                'profesor'   => $datos['profesor'],
                'ira'        => $ira,
                // Exámenes y ejercicios por separado, con cuántas notas
                // sostienen cada media. Es lo que dibuja el gráfico de la
                // pantalla de familia.
                'por_tipo'   => $motor->desglosePorTipo($datos['resultados'] ?? []),
            ];

            $paraGlobal[] = [
                'ira'   => $ira['valor'],
                'horas' => (int) $datos['asignatura']->horas_semanales,
            ];
        }

        // De peor a mejor; las grises al final, porque no son un problema
        // conocido sino un dato que falta.
        usort($filas, function ($a, $b) {
            $va = $a['ira']['valor'];
            $vb = $b['ira']['valor'];
            if ($va === null && $vb === null) return 0;
            if ($va === null) return 1;
            if ($vb === null) return -1;

            return $va <=> $vb;
        });

        return [
            'global'           => $motor->calcularIrg($paraGlobal),
            'asignaturas'      => $filas,
            // El desglose del alumno entero: la media de todos sus exámenes y
            // la de todos sus ejercicios, cada una ponderada por recencia.
            'por_tipo'         => $motor->desglosePorTipo($todos),
            'total_resultados' => count($porEvaluable),
        ];
    }
}
