<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «Soy familia de este alumno, dadme un código.»
 *
 * Lo que hay aquí es una **declaración sin verificar**: lo que alguien escribió
 * en un formulario público. No prueba nada y no da acceso a nada. Lo único que
 * hace es poner una fila en la bandeja del centro para que una persona —que sí
 * sabe quién es quién— decida.
 */
class SolicitudVinculacion extends Model
{
    protected $table = 'solicitudes_vinculacion';

    protected $fillable = [
        'centro_id', 'alumno_nombre', 'alumno_apellidos', 'alumno_curso',
        'nombre', 'apellidos', 'email', 'telefono', 'parentesco', 'mensaje',
        'estado', 'resuelta_por', 'resuelta_en', 'codigo_emitido', 'ip',
    ];

    protected $casts = ['resuelta_en' => 'datetime'];

    public const PENDIENTE = 'PENDIENTE';
    public const APROBADA  = 'APROBADA';
    public const RECHAZADA = 'RECHAZADA';

    public function centro(): BelongsTo { return $this->belongsTo(Centro::class); }

    public function resolutor(): BelongsTo { return $this->belongsTo(User::class, 'resuelta_por'); }

    public function scopePendientes(Builder $c): Builder
    {
        return $c->where('estado', self::PENDIENTE);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellidos}";
    }

    public function getAlumnoDeclaradoAttribute(): string
    {
        return trim("{$this->alumno_nombre} {$this->alumno_apellidos}");
    }

    /**
     * Los alumnos del centro que se parecen a lo que declaró quien solicita.
     *
     * Es una **ayuda para la persona que decide**, no una decisión automática.
     * Busca por apellidos y por nombre por separado, porque la familia escribe
     * «Lucia Ramos» donde el centro tiene «Lucía Ramos Ortega», y una búsqueda
     * por la cadena entera no encontraría nada. Quien aprueba ve los candidatos
     * y elige; si no aparece el que espera, tiene el buscador de alumnos.
     *
     * @return Collection<int, Alumno>
     */
    public function candidatos(): Collection
    {
        return Alumno::where('centro_id', $this->centro_id)
            ->where('activo', true)
            ->where(function (Builder $q) {
                $q->where('apellidos', 'like', '%' . $this->alumno_apellidos . '%')
                  ->orWhere('nombre', 'like', '%' . $this->alumno_nombre . '%');
            })
            ->with('grupo.curso')
            ->orderBy('apellidos')
            ->limit(8)
            ->get();
    }
}
