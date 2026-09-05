<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndiceRendimiento extends Model
{
    protected $table = 'indices_rendimiento';
    public $timestamps = false;

    protected $fillable = [
        'alumno_id', 'asignatura_id', 'valor', 'nivel',
        'tendencia', 'num_resultados', 'calculado_en',
    ];

    protected $casts = [
        'valor'        => 'float',
        'calculado_en' => 'datetime',
    ];

    public function alumno(): BelongsTo     { return $this->belongsTo(Alumno::class); }
    public function asignatura(): BelongsTo { return $this->belongsTo(Asignatura::class); }

    /** El índice global es el que no tiene asignatura. */
    public function esGlobal(): bool { return $this->asignatura_id === null; }
}
