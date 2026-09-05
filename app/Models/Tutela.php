<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo entre una familia y un alumno.
 *
 * `tutelas` era la única tabla con datos propios del esquema que no tenía
 * modelo: se manipulaba con `DB::table('tutelas')` desde el controlador. Como
 * es la tabla que decide quién puede ver a qué menor, merece un sitio con
 * nombre donde vivan sus reglas.
 *
 * Sigue sin usarse como pivot de Eloquent —las relaciones `alumnos()` y
 * `tutores()` continúan siendo `belongsToMany` con `withPivot`— porque cambiar
 * eso obligaría a tocar todas las consultas. Esto es el sitio canónico del
 * dominio, no un reemplazo.
 */
class Tutela extends Model
{
    protected $table = 'tutelas';

    public $timestamps = false;

    protected $fillable = ['tutor_legal_id', 'alumno_id', 'parentesco', 'vinculado_en', 'activa'];

    protected $casts = [
        'activa'       => 'boolean',
        'vinculado_en' => 'datetime',
    ];

    /**
     * Valores admitidos en la columna `parentesco`, que es un ENUM.
     *
     * Antes era un `string(30)` libre y convivían «MADRE», «Madre» y «madre»
     * sin que nada lo impidiera. La lista está aquí y no repetida en cada
     * vista: si algún día hay que añadir «ACOGEDOR», se toca en dos sitios —el
     * ENUM de la migración y esta constante— y no en cinco.
     *
     * @var array<int, string>
     */
    public const PARENTESCOS = ['MADRE', 'PADRE', 'TUTOR', 'ABUELO', 'ABUELA', 'OTRO'];

    /** El que se asume cuando el formulario no lo dice. */
    public const POR_DEFECTO = 'TUTOR';

    /**
     * Cuántas familias pueden estar vinculadas a la vez a un mismo alumno.
     *
     * Dos: el modelo de la aplicación es el de dos progenitores o tutores
     * legales. No es una limitación técnica sino una decisión de producto, y
     * está aquí y no repetida en cada controlador para que cambiarla sea tocar
     * un número. Cuentan solo las tutelas **activas**: una retirada conserva la
     * traza de quién pudo ver al menor, pero deja el hueco libre.
     */
    public const MAX_POR_ALUMNO = 2;

    /** Las familias que ahora mismo ocupan sitio en este alumno. */
    public static function activasDe(int $alumnoId): int
    {
        return static::where('alumno_id', $alumnoId)->where('activa', true)->count();
    }

    /** ¿Cabe otra familia en este alumno? */
    public static function hayHueco(int $alumnoId): bool
    {
        return static::activasDe($alumnoId) < self::MAX_POR_ALUMNO;
    }

    public function tutorLegal(): BelongsTo { return $this->belongsTo(TutorLegal::class); }

    public function alumno(): BelongsTo { return $this->belongsTo(Alumno::class); }

    /** «MADRE» → «Madre». Lo usan las dos vistas que enseñan el parentesco. */
    public static function etiqueta(?string $parentesco): string
    {
        return $parentesco === null ? '—' : ucfirst(mb_strtolower($parentesco));
    }
}
