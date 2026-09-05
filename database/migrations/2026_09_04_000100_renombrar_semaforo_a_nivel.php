<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El semáforo se convierte en nivel, también en la base de datos.
 *
 * Cuando el ámbar de la paleta pasó a ser el color de acción de la plataforma,
 * los nombres ROJO / NARANJA / VERDE / GRIS dejaron de describir nada: el
 * estado «naranja» se pinta de ocre y el «gris» de azul apagado. Un esquema que
 * llama a las cosas por un color que ya no tienen es un esquema que engaña a
 * quien lo lee dentro de un año.
 *
 *   centros.umbral_rojo            → centros.umbral_bajo
 *   centros.umbral_verde           → centros.umbral_alto
 *   indices_rendimiento.color      → indices_rendimiento.nivel
 *   y los valores ROJO/NARANJA/VERDE/GRIS → BAJO/MEDIO/ALTO/SIN_DATOS
 *
 * Quien parta de cero no necesita esta migración: las de 2026_08_21 ya crean
 * las tablas con los nombres nuevos. Está para las bases de datos que ya
 * existían, que es el caso del entorno de desarrollo.
 *
 * Cada paso comprueba antes si hace falta, así que se puede ejecutar sobre una
 * base ya migrada sin romper nada.
 */
return new class extends Migration
{
    private const TRADUCCION = [
        'ROJO'    => 'BAJO',
        'NARANJA' => 'MEDIO',
        'VERDE'   => 'ALTO',
        'GRIS'    => 'SIN_DATOS',
    ];

    public function up(): void
    {
        if (Schema::hasColumn('centros', 'umbral_rojo')) {
            Schema::table('centros', fn (Blueprint $t) => $t->renameColumn('umbral_rojo', 'umbral_bajo'));
        }
        if (Schema::hasColumn('centros', 'umbral_verde')) {
            Schema::table('centros', fn (Blueprint $t) => $t->renameColumn('umbral_verde', 'umbral_alto'));
        }

        if (! Schema::hasTable('indices_rendimiento')) {
            return;
        }

        if (Schema::hasColumn('indices_rendimiento', 'color')) {
            Schema::table('indices_rendimiento', fn (Blueprint $t) => $t->renameColumn('color', 'nivel'));
        }

        // Los valores antiguos que hubiera dentro. Se traducen uno a uno en vez
        // de con un CASE para que la lista de arriba sea la única fuente.
        foreach (self::TRADUCCION as $antes => $ahora) {
            DB::table('indices_rendimiento')->where('nivel', $antes)->update(['nivel' => $ahora]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('centros', 'umbral_bajo')) {
            Schema::table('centros', fn (Blueprint $t) => $t->renameColumn('umbral_bajo', 'umbral_rojo'));
        }
        if (Schema::hasColumn('centros', 'umbral_alto')) {
            Schema::table('centros', fn (Blueprint $t) => $t->renameColumn('umbral_alto', 'umbral_verde'));
        }

        if (! Schema::hasTable('indices_rendimiento')) {
            return;
        }

        foreach (array_flip(self::TRADUCCION) as $ahora => $antes) {
            DB::table('indices_rendimiento')->where('nivel', $ahora)->update(['nivel' => $antes]);
        }

        if (Schema::hasColumn('indices_rendimiento', 'nivel')) {
            Schema::table('indices_rendimiento', fn (Blueprint $t) => $t->renameColumn('nivel', 'color'));
        }
    }
};
