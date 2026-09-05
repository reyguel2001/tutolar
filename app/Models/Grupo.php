<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    protected $table = 'grupos';
    protected $fillable = ['curso_id', 'denominacion', 'tutor_grupo_id'];

    public function curso(): BelongsTo        { return $this->belongsTo(Curso::class); }
    public function tutorGrupo(): BelongsTo   { return $this->belongsTo(Profesor::class, 'tutor_grupo_id'); }
    public function alumnos(): HasMany        { return $this->hasMany(Alumno::class); }
    public function imparticiones(): HasMany  { return $this->hasMany(Imparticion::class); }

    /** «3º ESO B» */
    public function getNombreCompletoAttribute(): string
    {
        return trim(($this->curso->denominacion ?? '') . ' ' . $this->denominacion);
    }
}
