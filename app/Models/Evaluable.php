<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluable extends Model
{
    protected $table = 'evaluables';

    protected $fillable = [
        'imparticion_id', 'tipo', 'titulo', 'descripcion',
        'fecha_prevista', 'puntuacion_maxima', 'estado', 'creado_por',
    ];

    protected $casts = [
        'fecha_prevista'    => 'date',
        'puntuacion_maxima' => 'float',
    ];

    public function imparticion(): BelongsTo { return $this->belongsTo(Imparticion::class); }
    public function resultados(): HasMany    { return $this->hasMany(Resultado::class); }

    public function esExamen(): bool { return $this->tipo === 'EXAMEN'; }
}
