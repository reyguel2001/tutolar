<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aviso emitido por el profesorado a las familias de un grupo.
 *
 * La notificación es una sola fila; el reparto a cada familia vive en
 * `notificacion_destinatarios`. Esa materialización es lo que permite
 * responder «¿quién lo ha confirmado?» con una consulta trivial en vez de
 * recorrer tutelas en cada carga de pantalla.
 */
class Notificacion extends Model
{
    protected $table = 'notificaciones';

    /** Lo que el profesorado puede emitir desde la web. */
    public const EXAMEN_PROGRAMADO = 'EXAMEN_PROGRAMADO';
    public const TAREA_ASIGNADA    = 'TAREA_ASIGNADA';
    public const RESULTADOS_EXAMEN = 'RESULTADOS_EXAMEN';
    public const RESULTADOS_TAREA  = 'RESULTADOS_TAREA';

    /** Los emite el sistema, no una persona. */
    public const ALERTA_RENDIMIENTO = 'ALERTA_RENDIMIENTO';
    public const DISCREPANCIA       = 'DISCREPANCIA';

    /**
     * Los cuatro tipos del formulario del profesor, en el orden en que se
     * presentan. Los dos primeros anuncian algo que va a pasar y crean el
     * evaluable; los dos últimos avisan de que ya hay nota que apuntar.
     *
     * @var array<string, array{etiqueta: string, ayuda: string, icono: string, evaluable: string}>
     */
    public const EMITIBLES = [
        self::EXAMEN_PROGRAMADO => [
            'etiqueta'  => 'Examen programado',
            'ayuda'     => 'Anuncia un examen con su fecha. Se crea el examen en el sistema.',
            'icono'     => '📝',
            'evaluable' => 'crear:EXAMEN',
        ],
        self::TAREA_ASIGNADA => [
            'etiqueta'  => 'Tarea asignada',
            'ayuda'     => 'Anuncia una tarea o trabajo con su fecha de entrega.',
            'icono'     => '📋',
            'evaluable' => 'crear:TAREA',
        ],
        self::RESULTADOS_EXAMEN => [
            'etiqueta'  => 'Notas del examen entregadas',
            'ayuda'     => 'Avisa de que ya hay nota de un examen ya realizado. La familia podrá registrarla.',
            'icono'     => '📊',
            'evaluable' => 'elegir:EXAMEN',
        ],
        self::RESULTADOS_TAREA => [
            'etiqueta'  => 'Ejercicios resueltos y corregidos',
            'ayuda'     => 'Avisa de que los ejercicios ya están corregidos y hay nota que apuntar.',
            'icono'     => '✅',
            'evaluable' => 'elegir:TAREA',
        ],
    ];

    protected $fillable = [
        'tipo', 'emisor_id', 'evaluable_id', 'grupo_id', 'titulo', 'mensaje', 'emitida_en',
    ];

    protected $casts = ['emitida_en' => 'datetime'];

    public function emisor(): BelongsTo    { return $this->belongsTo(User::class, 'emisor_id'); }
    public function evaluable(): BelongsTo { return $this->belongsTo(Evaluable::class); }
    public function grupo(): BelongsTo     { return $this->belongsTo(Grupo::class); }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(NotificacionDestinatario::class, 'notificacion_id');
    }

    public function etiquetaTipo(): string
    {
        return self::EMITIBLES[$this->tipo]['etiqueta'] ?? ucfirst(mb_strtolower(str_replace('_', ' ', $this->tipo)));
    }

    public function icono(): string
    {
        return self::EMITIBLES[$this->tipo]['icono'] ?? '🔔';
    }

    /** ¿Este tipo pide a la familia que registre una nota? */
    public function pideRegistro(): bool
    {
        return in_array($this->tipo, [self::RESULTADOS_EXAMEN, self::RESULTADOS_TAREA], true);
    }
}
