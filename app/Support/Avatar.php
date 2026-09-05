<?php

namespace App\Support;

/**
 * Avatares.
 *
 * Nadie está obligado a subir una foto, y aun así toda persona de la aplicación
 * tiene cara. Sin foto se dibuja una silueta: **adulto** para dirección,
 * profesorado y familias; **niño** para el alumnado. La distinción no es
 * decorativa —en una pantalla donde conviven la madre y el hijo, la silueta
 * dice de un vistazo quién es quién sin tener que leer el nombre.
 *
 * El color no es aleatorio: sale del nombre. La misma persona sale siempre del
 * mismo color, en cualquier pantalla y entre sesiones, que es lo que convierte
 * un adorno en una ayuda para reconocer.
 */
final class Avatar
{
    public const ADULTO = 'adulto';
    public const NINO   = 'nino';

    /**
     * Paleta de identidad.
     *
     * La regla es una sola: **ningún avatar puede leerse como una alarma**. Por
     * eso los cinco viven en la mitad fría del círculo, a ΔE 22 o más de los
     * tonos cálidos que la aplicación tiene reservados —el rojo de rendimiento
     * bajo, el ocre de rendimiento medio y el ámbar de acción— y del verde de
     * rendimiento alto.
     *
     * No hace falta separarlos del gris de «sin datos»: nadie interpreta un
     * avatar azulado como un estado. Sí hace falta que se separen del marino de
     * la barra lateral, porque ahí es donde se pinta el avatar de quien ha
     * entrado, y que se distingan entre ellos: ΔE 15.6 como mínimo, con
     * contraste de 4.7:1 o más para la silueta blanca encima.
     *
     * Cambiaron con la paleta nueva. Son cinco y no seis a propósito: el sexto
     * que cabía en el hueco obligaba a bajar la separación a ΔE 7, y dos
     * avatares casi iguales no ayudan a reconocer a nadie, que es lo único
     * para lo que existe este color.
     *
     * @var array<int, string>
     */
    public const COLORES = [
        '#2F5AC8',   // azul
        '#7A3BC4',   // púrpura
        '#C01A6E',   // frambuesa
        '#1E7E92',   // teal
        '#5F5F7F',   // gris violáceo
    ];

    /** Tamaños con nombre, para no repetir números sueltos en las vistas. */
    public const TAMANOS = ['xs' => 26, 'sm' => 32, 'md' => 44, 'lg' => 54, 'xl' => 88];

    /**
     * El color de esta persona, deducido de su nombre.
     *
     * `crc32` y no `rand`: tiene que salir lo mismo en cada carga de página y
     * en cada máquina. Se normaliza a minúsculas y sin espacios de sobra para
     * que un cambio de mayúsculas no le cambie la cara a nadie.
     */
    public static function color(string $semilla): string
    {
        $normalizada = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $semilla) ?? $semilla));

        return self::COLORES[crc32($normalizada) % count(self::COLORES)];
    }

    /** «Marta Vega Blanco» → «MV». Dos letras: más se vuelve ilegible a 26px. */
    public static function iniciales(string $nombre, string $apellidos = ''): string
    {
        $primera = mb_substr(trim($nombre), 0, 1);
        $segunda = $apellidos !== ''
            ? mb_substr(trim($apellidos), 0, 1)
            : mb_substr(explode(' ', trim($nombre) . ' ')[1] ?? '', 0, 1);

        return mb_strtoupper($primera . $segunda);
    }

    /** Píxeles a partir del nombre del tamaño; un número suelto también vale. */
    public static function pixeles(int|string $tamano): int
    {
        return is_int($tamano) ? $tamano : (self::TAMANOS[$tamano] ?? self::TAMANOS['sm']);
    }
}
