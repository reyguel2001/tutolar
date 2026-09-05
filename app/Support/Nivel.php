<?php

namespace App\Support;

/**
 * Nivel de rendimiento de TUTOLAR.
 *
 * Antes esto se llamaba «semáforo» y el nombre de cada estado era su color:
 * ROJO, NARANJA, VERDE, GRIS. Dejó de valer cuando el ámbar de la paleta pasó
 * a ser el color de acción de la plataforma: un estado llamado NARANJA que se
 * pinta de ocre, y que además no puede parecerse al botón de al lado, es una
 * mentira que envejece mal. Ahora el estado se llama por lo que dice —BAJO,
 * MEDIO, ALTO, SIN_DATOS— y el color es solo una de las tres señales que lo
 * transmiten.
 *
 * Las otras dos son los **segmentos llenos** y la **palabra**. Van siempre
 * juntas. Quítale el color a la pantalla —daltonismo, impresión en blanco y
 * negro, pantalla mala— y el nivel se sigue leyendo:
 *
 *     BAJO  ▮▯▯      MEDIO ▮▮▯      ALTO ▮▮▮      SIN DATOS ▯▯▯
 *
 * Este es el único sitio donde vive la traducción índice → nivel → color.
 * Ninguna vista decide un color por su cuenta: si lo hiciera, tarde o temprano
 * aparecería un rojo donde el sistema de diseño exige gris.
 */
final class Nivel
{
    public const BAJO      = 'BAJO';
    public const MEDIO     = 'MEDIO';
    public const ALTO      = 'ALTO';
    public const SIN_DATOS = 'SIN_DATOS';

    /** Mínimo de resultados para emitir juicio de rendimiento (principio 1). */
    public const MIN_RESULTADOS = 2;

    public const ETIQUETAS = [
        self::BAJO      => 'Rendimiento bajo',
        self::MEDIO     => 'Rendimiento medio',
        self::ALTO      => 'Rendimiento alto',
        self::SIN_DATOS => 'Datos insuficientes',
    ];

    public const ETIQUETAS_CORTAS = [
        self::BAJO      => 'BAJO',
        self::MEDIO     => 'MEDIO',
        self::ALTO      => 'ALTO',
        self::SIN_DATOS => 'SIN DATOS',
    ];

    /** Clase CSS del chip y de los rellenos, definida en tutolar.css. */
    public const CLASES = [
        self::BAJO      => 'tl-bajo',
        self::MEDIO     => 'tl-medio',
        self::ALTO      => 'tl-alto',
        self::SIN_DATOS => 'tl-sin',
    ];

    /**
     * Segmentos llenos de tres. Es la señal que no depende del color, y por
     * eso «sin datos» son cero: no es un nivel bajo, es la ausencia de nivel.
     */
    public const SEGMENTOS = [
        self::BAJO      => 1,
        self::MEDIO     => 2,
        self::ALTO      => 3,
        self::SIN_DATOS => 0,
    ];

    /**
     * Traduce un valor 0–100 al nivel correspondiente.
     *
     * @param float|null $valor       null significa «no calculable», no cero.
     * @param float      $umbralBajo  Por debajo de este valor, BAJO.
     * @param float      $umbralAlto  A partir de este valor, ALTO.
     */
    public static function de(?float $valor, float $umbralBajo = 50, float $umbralAlto = 70): string
    {
        if ($valor === null) {
            return self::SIN_DATOS;
        }
        if ($valor < $umbralBajo) {
            return self::BAJO;
        }
        if ($valor < $umbralAlto) {
            return self::MEDIO;
        }

        return self::ALTO;
    }

    public static function etiqueta(string $nivel): string
    {
        return self::ETIQUETAS[$nivel] ?? self::ETIQUETAS[self::SIN_DATOS];
    }

    public static function etiquetaCorta(string $nivel): string
    {
        return self::ETIQUETAS_CORTAS[$nivel] ?? self::ETIQUETAS_CORTAS[self::SIN_DATOS];
    }

    public static function clase(string $nivel): string
    {
        return self::CLASES[$nivel] ?? self::CLASES[self::SIN_DATOS];
    }

    public static function segmentos(string $nivel): int
    {
        return self::SEGMENTOS[$nivel] ?? 0;
    }

    /**
     * Sufijo del token CSS: `bajo`, `medio`, `alto`, `sin`.
     *
     * Existe para que ninguna vista construya el nombre de una variable CSS a
     * base de strtolower() sobre la constante. Lo hacía, y por eso SIN_DATOS
     * pedía una variable `--tl-sin_datos` que no existe y el arco del dial se
     * quedaba transparente.
     */
    public static function token(string $nivel): string
    {
        return substr(self::clase($nivel), 3);
    }

    /**
     * Cómo se muestra la cifra. Sin datos nunca enseña un cero: cero y
     * «no sabemos» son cosas distintas y confundirlas alarma a una familia.
     */
    public static function cifra(?float $valor): string
    {
        return $valor === null ? '—' : (string) round($valor);
    }
}
