<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro sólo-añadir.
 *
 * Tratar calificaciones de menores obliga a poder responder siempre quién
 * cambió qué y cuándo. Nada de esta tabla se modifica ni se borra: por eso no
 * tiene `updated_at` ni un método de actualización.
 */
class Auditoria extends Model
{
    protected $table = 'auditorias';

    /** La tabla no tiene created_at/updated_at: la marca de tiempo es `ocurrido_en`. */
    public $timestamps = false;

    public const CREAR     = 'CREAR';
    public const MODIFICAR = 'MODIFICAR';
    public const ANULAR    = 'ANULAR';

    protected $fillable = [
        'entidad', 'entidad_id', 'accion', 'autor_id',
        'valor_anterior', 'valor_nuevo', 'ip', 'ocurrido_en',
    ];

    protected $casts = [
        'valor_anterior' => 'array',
        'valor_nuevo'    => 'array',
        'ocurrido_en'    => 'datetime',
    ];

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    /**
     * Deja constancia de una acción sobre una entidad.
     *
     * @param array<string, mixed>|null $anterior
     * @param array<string, mixed>|null $nuevo
     */
    public static function registrar(
        string $entidad,
        int $entidadId,
        string $accion,
        ?int $autorId,
        ?array $anterior = null,
        ?array $nuevo = null,
        ?string $ip = null,
    ): self {
        return self::create([
            'entidad'        => $entidad,
            'entidad_id'     => $entidadId,
            'accion'         => $accion,
            'autor_id'       => $autorId,
            'valor_anterior' => $anterior,
            'valor_nuevo'    => $nuevo,
            'ip'             => $ip,
            'ocurrido_en'    => now(),
        ]);
    }
}
