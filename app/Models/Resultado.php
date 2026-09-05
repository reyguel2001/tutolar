<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resultado extends Model
{
    protected $table = 'resultados';

    protected $fillable = [
        'evaluable_id', 'alumno_id', 'puntuacion_obtenida',
        'origen', 'estado', 'comentario', 'registrado_por',
    ];

    protected $casts = ['puntuacion_obtenida' => 'float'];

    public function evaluable(): BelongsTo { return $this->belongsTo(Evaluable::class); }
    public function alumno(): BelongsTo    { return $this->belongsTo(Alumno::class); }
    public function autor(): BelongsTo     { return $this->belongsTo(User::class, 'registrado_por'); }

    public function estaVerificado(): bool { return $this->origen === 'VERIFICADO'; }
}
