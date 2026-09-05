<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Centro extends Model
{
    protected $table = 'centros';

    protected $fillable = [
        'denominacion', 'cif', 'direccion', 'email', 'telefono',
        'umbral_bajo', 'umbral_alto', 'peso_examenes', 'peso_tareas',
        'escala_maxima', 'activo',
    ];

    protected $casts = [
        'umbral_bajo'   => 'float',
        'umbral_alto'  => 'float',
        'peso_examenes' => 'float',
        'peso_tareas'   => 'float',
        'escala_maxima' => 'float',
        'activo'        => 'boolean',
    ];

    public function cursos(): HasMany       { return $this->hasMany(Curso::class); }
    public function asignaturas(): HasMany  { return $this->hasMany(Asignatura::class); }
    public function alumnos(): HasMany      { return $this->hasMany(Alumno::class); }
    public function profesores(): HasMany   { return $this->hasMany(Profesor::class); }
}
