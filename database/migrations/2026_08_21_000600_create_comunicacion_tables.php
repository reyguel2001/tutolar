<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOMINIO 6 · COMUNICACIÓN Y TRAZABILIDAD
 *
 * El profesor emite una notificación de grupo; el sistema la reparte
 * («fan-out») en una fila por cada pareja (tutor, alumno). Esa materialización
 * es lo que permite responder «¿quién la ha confirmado?» con una consulta
 * trivial en vez de recorrer tutelas en cada carga de pantalla.
 *
 * `notificaciones` es el hecho —alguien avisó de algo, en tal fecha— y
 * `notificacion_destinatarios` es su proyección. De ahí el reparto de reglas de
 * borrado: la proyección se puede regenerar y va en cascada, el hecho no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', [
                'EXAMEN_PROGRAMADO', 'TAREA_ASIGNADA', 'RESULTADOS_EXAMEN', 'RESULTADOS_TAREA',
                'RECORDATORIO', 'ALERTA_RENDIMIENTO', 'DISCREPANCIA', 'AVISO_GENERAL', 'ANULACION',
            ]);
            // emisor_id nulo significa que la generó el sistema, no una persona.
            $table->foreignId('emisor_id')->nullable()->constrained('users')->nullOnDelete();
            // Si el examen se anula, el aviso de que existió no se borra: se
            // queda huérfano a propósito, con su título y su fecha.
            $table->foreignId('evaluable_id')->nullable()->constrained('evaluables')->nullOnDelete();
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->restrictOnDelete();
            $table->string('titulo', 150);
            $table->text('mensaje')->nullable();
            $table->timestamp('emitida_en')->useCurrent();
            $table->timestamps();

            $table->index(['grupo_id', 'emitida_en']);
            $table->index(['emisor_id', 'emitida_en']);
        });

        Schema::create('notificacion_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notificacion_id')->constrained('notificaciones')->cascadeOnDelete();
            $table->foreignId('tutor_legal_id')->constrained('tutores_legales')->cascadeOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->timestamp('leida_en')->nullable();
            $table->timestamp('confirmada_en')->nullable();
            $table->boolean('push_entregado')->default(false);

            $table->unique(['notificacion_id', 'tutor_legal_id', 'alumno_id'], 'notif_dest_unica');
            // La bandeja de la familia y el contador de no leídos del menú.
            $table->index(['tutor_legal_id', 'leida_en'], 'notif_dest_tutor_leida_idx');
            $table->index(['tutor_legal_id', 'confirmada_en'], 'notif_dest_tutor_confirmada_idx');
        });

        /**
         * Registro sólo-añadir. Tratar calificaciones de menores obliga a poder
         * responder siempre quién cambió qué y cuándo, así que esta tabla no
         * tiene `updated_at`, no se modifica y no se borra en cascada de nada.
         * `autor_id` a null si la cuenta desaparece: se pierde el enlace, no el
         * hecho —el nombre queda dentro del JSON.
         */
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 50);
            $table->unsignedBigInteger('entidad_id');
            $table->string('accion', 20);                  // CREAR, MODIFICAR, ANULAR
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('valor_anterior')->nullable();
            $table->json('valor_nuevo')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('ocurrido_en')->useCurrent();

            $table->index(['entidad', 'entidad_id', 'ocurrido_en']);
            // «¿Qué ha tocado esta persona?», que es la otra pregunta que se le
            // hace siempre a un registro de auditoría.
            $table->index(['autor_id', 'ocurrido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('notificacion_destinatarios');
        Schema::dropIfExists('notificaciones');
    }
};
