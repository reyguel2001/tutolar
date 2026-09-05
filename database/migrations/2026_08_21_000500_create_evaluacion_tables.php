<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOMINIO 5 · EVALUACIÓN
 *
 * Examen y tarea se unifican en `evaluables` con un discriminador, porque
 * comparten todo el ciclo de vida y solo difieren en el peso que tienen dentro
 * del índice de rendimiento.
 *
 * Aquí está el historial académico de menores. Ninguna clave foránea de
 * `evaluables` ni de `resultados` borra en cascada: para hacer desaparecer una
 * nota hay que ir a por ella. `indices_rendimiento` es la excepción — es caché
 * recalculable, no un dato original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imparticion_id')->constrained('imparticiones')->restrictOnDelete();
            $table->enum('tipo', ['EXAMEN', 'TAREA']);
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->date('fecha_prevista');
            $table->decimal('puntuacion_maxima', 5, 2)->default(10);
            $table->enum('estado', [
                'PROGRAMADO', 'NOTIFICADO', 'REALIZADO',
                'PENDIENTE_REGISTRO', 'REGISTRADO', 'ANULADO',
            ])->default('PROGRAMADO');
            $table->foreignId('creado_por')->constrained('profesores')->restrictOnDelete();
            $table->timestamps();

            $table->index(['imparticion_id', 'fecha_prevista']);
            // El formulario de la familia busca justo esto: los evaluables de
            // sus imparticiones que están en PENDIENTE_REGISTRO.
            $table->index(['imparticion_id', 'estado']);
        });

        Schema::create('resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluable_id')->constrained('evaluables')->restrictOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();
            $table->decimal('puntuacion_obtenida', 5, 2);

            // Doble origen del dato: el tutor declara, el centro puede
            // verificar. Si ambos existen y difieren, prevalece el verificado y
            // el declarado se marca DISCREPANCIA (regla RN-01).
            $table->enum('origen', ['DECLARADO', 'VERIFICADO'])->default('DECLARADO');
            $table->enum('estado', ['VALIDO', 'DISCREPANCIA', 'CORREGIDO'])->default('VALIDO');

            $table->text('comentario')->nullable();
            // Quien registró una nota no se puede borrar: es media firma.
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Un mismo evaluable puede tener a la vez el valor declarado y el
            // verificado, pero no dos del mismo origen.
            $table->unique(['evaluable_id', 'alumno_id', 'origen']);
            $table->index(['alumno_id', 'created_at']);
        });

        /**
         * Instantáneas del índice, no un único valor actual: es lo que permite
         * dibujar la evolución sin recalcular todo el histórico en cada
         * consulta. `asignatura_id` nulo = índice global del alumno (IRG).
         *
         * Cascada en las dos claves: si el alumno o la asignatura desaparecen,
         * estas filas se pueden tirar sin perder nada. Se recalculan a partir
         * de `resultados`, que es el dato de verdad.
         */
        Schema::create('indices_rendimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('asignatura_id')->nullable()->constrained('asignaturas')->cascadeOnDelete();
            $table->decimal('valor', 5, 2)->nullable();   // null cuando el nivel es SIN_DATOS
            $table->enum('nivel', ['BAJO', 'MEDIO', 'ALTO', 'SIN_DATOS']);
            $table->enum('tendencia', ['ASCENDENTE', 'ESTABLE', 'DESCENDENTE'])->nullable();
            $table->unsignedSmallInteger('num_resultados')->default(0);
            $table->timestamp('calculado_en')->useCurrent();

            $table->index(['alumno_id', 'asignatura_id', 'calculado_en'], 'indices_alumno_asig_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indices_rendimiento');
        Schema::dropIfExists('resultados');
        Schema::dropIfExists('evaluables');
    }
};
