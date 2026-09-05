<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila por cada pareja (familia, alumno) a la que llega un aviso.
 *
 * Va por alumno y no solo por tutor a propósito: si una familia tutela a dos
 * hermanos del mismo grupo, recibe el aviso de cada uno y confirma cada uno.
 * La tabla no tiene timestamps porque las dos marcas que importan —cuándo se
 * leyó y cuándo se confirmó— son columnas suyas con nombre propio.
 */
class NotificacionDestinatario extends Model
{
    protected $table = 'notificacion_destinatarios';

    public $timestamps = false;

    protected $fillable = [
        'notificacion_id', 'tutor_legal_id', 'alumno_id',
        'leida_en', 'confirmada_en', 'push_entregado',
    ];

    protected $casts = [
        'leida_en'       => 'datetime',
        'confirmada_en'  => 'datetime',
        'push_entregado' => 'boolean',
    ];

    public function notificacion(): BelongsTo { return $this->belongsTo(Notificacion::class, 'notificacion_id'); }
    public function tutorLegal(): BelongsTo   { return $this->belongsTo(TutorLegal::class, 'tutor_legal_id'); }
    public function alumno(): BelongsTo       { return $this->belongsTo(Alumno::class); }

    public function estaLeida(): bool     { return $this->leida_en !== null; }
    public function estaConfirmada(): bool { return $this->confirmada_en !== null; }
}
