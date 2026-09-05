<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asignatura extends Model
{
    protected $table = 'asignaturas';
    protected $fillable = ['centro_id', 'denominacion', 'horas_semanales'];
    protected $casts = ['horas_semanales' => 'integer'];

    public function centro(): BelongsTo       { return $this->belongsTo(Centro::class); }
    public function imparticiones(): HasMany  { return $this->hasMany(Imparticion::class); }

    /**
     * Los alumnos matriculados en esta materia.
     *
     * Es la inversa de `Alumno::asignaturas()` y faltaba. Sin ella, la pantalla
     * de asignaturas no tenía forma de enseñar cuántas matrículas cuelgan de
     * cada una — y sin embargo `destroy()` bloqueaba el borrado contándolas.
     * Se enteraba uno al pulsar Eliminar.
     */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'matriculas');
    }

    /** En qué cursos entra esta materia según el plan de estudios del centro. */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'asignatura_curso');
    }

    /**
     * Una asignatura sin cursos marcados se considera disponible en todos.
     *
     * Es lo que mantiene el campo opcional: un centro que no ha rellenado su
     * plan de estudios sigue pudiendo asignar docentes y matricular alumnos.
     */
    public function seImparteEn(?int $cursoId): bool
    {
        if ($cursoId === null) {
            return true;
        }

        $suyos = $this->relationLoaded('cursos') ? $this->cursos : $this->cursos()->get();

        return $suyos->isEmpty() || $suyos->contains('id', $cursoId);
    }

    /** Las que se pueden impartir en un curso: las suyas y las que no restringen. */
    public function scopeDeCurso(Builder $consulta, ?int $cursoId): Builder
    {
        if ($cursoId === null) {
            return $consulta;
        }

        return $consulta->where(function (Builder $q) use ($cursoId) {
            $q->whereDoesntHave('cursos')
              ->orWhereHas('cursos', fn ($c) => $c->where('cursos.id', $cursoId));
        });
    }
}
