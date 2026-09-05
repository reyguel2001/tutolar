<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    protected $table = 'cursos';
    protected $fillable = ['centro_id', 'denominacion', 'anio_academico'];

    public function centro(): BelongsTo { return $this->belongsTo(Centro::class); }
    public function grupos(): HasMany   { return $this->hasMany(Grupo::class); }

    /** Las materias del plan de estudios de este curso. */
    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'asignatura_curso');
    }
}
