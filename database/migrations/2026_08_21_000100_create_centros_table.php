<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOMINIO 1 · EL CENTRO
 *
 * Raíz de todo el esquema. TUTOLAR es multicentro: cada fila de esta tabla es
 * un instituto y absolutamente ninguna consulta de la aplicación se hace sin
 * acotar por `centro_id`. Va sola en su migración porque es la única tabla de
 * la que dependen todas las demás.
 *
 * Un centro NO se borra: se desactiva (`activo`). Por eso todas las claves
 * foráneas que apuntan aquí son `restrictOnDelete` — ver la nota de borrado en
 * ARQUITECTURA.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros', function (Blueprint $table) {
            $table->id();
            $table->string('denominacion', 200);
            $table->string('cif', 15)->unique();
            $table->text('direccion')->nullable();
            $table->string('email', 180);
            $table->string('telefono', 20)->nullable();

            // Configuración del semáforo. Cada centro afina sus umbrales; el
            // orden rojo < verde se comprueba en la aplicación porque una
            // restricción CHECK entre dos columnas no es portable a SQLite.
            $table->decimal('umbral_bajo', 5, 2)->default(50);
            $table->decimal('umbral_alto', 5, 2)->default(70);
            $table->decimal('peso_examenes', 3, 2)->default(0.60);
            $table->decimal('peso_tareas', 3, 2)->default(0.40);
            $table->decimal('escala_maxima', 5, 2)->default(10);

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros');
    }
};
