<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * El código con el que una familia se registra y queda vinculada a su hijo.
 *
 * Es la pieza que hace posible el registro sin abrir un agujero. La alternativa
 * —dejar que cualquiera cree una cuenta y elija a un alumno de una lista— haría
 * que cualquier persona con un navegador pudiera vincularse a cualquier menor y
 * ver sus notas. Aquí el vínculo lo autoriza el centro **antes**: genera el
 * código desde la ficha del alumno y se lo entrega a la familia por el canal que
 * ya usa. Quien no tiene código no se registra.
 *
 * Tres defensas, y las tres hacen falta:
 *   · **Un solo uso.** Al gastarse queda `usado_en` y ya no vale.
 *   · **Caduca.** Un papel olvidado en una mochila deja de servir en 14 días.
 *   · **Imposible de adivinar.** 8 caracteres de un alfabeto sin parejas que se
 *     confundan al dictarlo (nada de 0/O ni 1/I/L), tomados de un generador
 *     criptográfico. Son unas 10^12 combinaciones.
 */
class CodigoVinculacion extends Model
{
    protected $table = 'codigos_vinculacion';
    protected $primaryKey = 'codigo';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['codigo', 'alumno_id', 'caduca_en', 'usado_en'];

    protected $casts = [
        'caduca_en' => 'datetime',
        'usado_en'  => 'datetime',
    ];

    /** Cuántos días vale un código recién generado. */
    public const DIAS_VALIDEZ = 14;

    /**
     * Alfabeto sin caracteres que se confundan al leerlos o dictarlos por
     * teléfono: sin 0 ni O, sin 1 ni I ni L. Un código que la familia teclea
     * mal es una llamada al centro.
     */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** Genera y guarda un código nuevo para este alumno. */
    public static function generarPara(Alumno $alumno): self
    {
        do {
            $codigo = self::sortear();
        } while (self::whereKey($codigo)->exists());

        return self::create([
            'codigo'    => $codigo,
            'alumno_id' => $alumno->id,
            'caduca_en' => now()->addDays(self::DIAS_VALIDEZ),
        ]);
    }

    private static function sortear(): string
    {
        $codigo = '';

        for ($i = 0; $i < 8; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $codigo;
    }

    /**
     * El código válido que corresponde a lo que ha escrito la familia, o null.
     *
     * Normaliza mayúsculas, espacios y guiones antes de buscar: quien lo copia
     * de un papel escribe «kq7d-2xb9» tan a menudo como «KQ7D2XB9», y las dos
     * cosas son el mismo código.
     */
    public static function valido(string $escrito): ?self
    {
        $limpio = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $escrito) ?? '');

        if ($limpio === '') {
            return null;
        }

        return self::with('alumno')
            ->whereKey($limpio)
            ->whereNull('usado_en')
            ->where('caduca_en', '>', now())
            ->first();
    }

    /** Formato para leerlo y dictarlo: «KQ7D-2XB9». */
    public function bonito(): string
    {
        return Str::substr($this->codigo, 0, 4) . '-' . Str::substr($this->codigo, 4);
    }
}
