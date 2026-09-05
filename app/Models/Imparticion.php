<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Imparticion extends Model
{
    protected $table = 'imparticiones';
    protected $fillable = ['profesor_id', 'asignatura_id', 'grupo_id'];

    public function profesor(): BelongsTo    { return $this->belongsTo(Profesor::class); }
    public function asignatura(): BelongsTo  { return $this->belongsTo(Asignatura::class); }
    public function grupo(): BelongsTo       { return $this->belongsTo(Grupo::class); }
    public function evaluables(): HasMany    { return $this->hasMany(Evaluable::class); }
}
