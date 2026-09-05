<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOMINIO 3 · ESTRUCTURA ACADÉMICA
 *
 * Cursos, grupos-clase, catálogo de asignaturas y plan de estudios.
 *
 * Los índices únicos de `cursos` y `asignaturas` no son decorativos: la
 * aplicación ya validaba que no hubiera dos «3º ESO» ni dos «Matemáticas» en el
 * mismo centro, pero esa regla vivía solo en el controlador. Ahora la garantiza
 * la base de datos, así que sigue en pie aunque alguien inserte por phpMyAdmin,
 * por un seeder o por una futura importación CSV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->string('denominacion', 80);          // «3º ESO»
            $table->string('anio_academico', 9);         // «2026/2027»
            $table->timestamps();

            // Coincide exactamente con la Rule::unique de GrupoController.
            $table->unique(['centro_id', 'denominacion']);
        });

        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->string('denominacion', 20);          // «B»

            // El tutor de grupo sí se puede quedar a null: si el profesor se va
            // del centro el grupo sigue existiendo, simplemente sin tutor.
            $table->foreignId('tutor_grupo_id')->nullable()->constrained('profesores')->nullOnDelete();
            $table->timestamps();

            $table->unique(['curso_id', 'denominacion']);
        });

        Schema::create('asignaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->string('denominacion', 120);
            $table->unsignedSmallInteger('horas_semanales')->default(1);
            $table->timestamps();

            $table->unique(['centro_id', 'denominacion']);
        });

        /**
         * Plan de estudios: en qué cursos se imparte cada asignatura.
         *
         * Es N:M y no una columna en `asignaturas` porque una materia entra en
         * varios cursos: Matemáticas está en los cuatro de la ESO, Física y
         * Química solo en tres.
         *
         * REGLA: una asignatura **sin ningún curso marcado** se considera
         * disponible en todos. Así el campo es opcional y nadie se queda
         * bloqueado por no haber rellenado el plan de estudios todavía.
         *
         * Cascada, y aquí sí: el plan de estudios no es historial, es
         * configuración. Se puede reconstruir en cualquier momento.
         */
        Schema::create('asignatura_curso', function (Blueprint $table) {
            $table->foreignId('asignatura_id')->constrained('asignaturas')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();

            $table->primary(['asignatura_id', 'curso_id']);
            $table->index('curso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignatura_curso');
        Schema::dropIfExists('asignaturas');
        Schema::dropIfExists('grupos');
        Schema::dropIfExists('cursos');
    }
};
