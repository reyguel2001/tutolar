<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOMINIO 4 · ALUMNADO
 *
 * El alumno y todo lo que lo conecta: su grupo, sus asignaturas, quién le
 * imparte cada una y qué familias lo tutelan.
 *
 * `alumnos.centro_id` es redundante —se podría deducir por grupo → curso →
 * centro— y se mantiene a propósito. Es la columna sobre la que se apoya el
 * aislamiento entre centros, la regla de seguridad más importante del proyecto:
 * que esté en la propia fila permite que `BaseCentroController` acote con un
 * `where` directo, sin un JOIN de tres saltos que alguien pueda olvidar. La
 * coherencia entre las dos vías la garantiza `AlumnoController::comprobarGrupo()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('apellidos', 150);
            $table->date('fecha_nacimiento');
            $table->string('foto_url')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Los tres accesos reales al listado: por centro (aislamiento),
            // por centro + activo (el filtro por defecto) y por grupo.
            $table->index(['centro_id', 'activo']);
            $table->index(['centro_id', 'apellidos']);
            $table->index(['grupo_id', 'activo']);
        });

        /**
         * Un profesor imparte una asignatura en un grupo. La pareja
         * (asignatura, grupo) es única: no hay dos docentes para lo mismo.
         *
         * De aquí cuelga toda la evaluación, así que sus tres claves foráneas
         * son `restrict`: borrar un grupo o una asignatura no puede llevarse
         * por delante los exámenes y las notas que cuelgan de ellos.
         */
        Schema::create('imparticiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesor_id')->constrained('profesores')->restrictOnDelete();
            $table->foreignId('asignatura_id')->constrained('asignaturas')->restrictOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['asignatura_id', 'grupo_id']);
            $table->index(['grupo_id', 'profesor_id']);
        });

        /**
         * Matrícula: en qué asignaturas está el alumno.
         *
         * Clave primaria compuesta, sin `id`. La fecha se guarda porque una
         * matrícula tiene un «desde cuándo» que conviene poder enseñar, y la
         * rellena la propia base de datos para no tocar los `attach()` que ya
         * existen en MatriculaController.
         */
        Schema::create('matriculas', function (Blueprint $table) {
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('asignatura_id')->constrained('asignaturas')->restrictOnDelete();
            $table->timestamp('matriculada_en')->useCurrent();

            $table->primary(['alumno_id', 'asignatura_id']);
            // La clave primaria ya indexa por alumno; falta el sentido inverso,
            // que es el que usa «¿cuántos alumnos hay en Matemáticas?».
            $table->index('asignatura_id');
        });

        /**
         * Tutela: qué familia puede ver a qué alumno.
         *
         * Es la tabla que decide quién ve qué —toda consulta de un tutor pasa
         * por aquí— y por eso es la única N:M del esquema con borrado
         * restringido en los dos extremos. Una tutela no se borra: se desactiva
         * (`activa = false`), porque es la traza de quién pudo ver qué y hasta
         * cuándo. Si el borrado fuese en cascada, eliminar una cuenta de familia
         * se llevaría esa traza por delante sin dejar rastro.
         */
        Schema::create('tutelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_legal_id')->constrained('tutores_legales')->restrictOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();

            // Antes era un string(30) libre: convivían «MADRE», «Madre» y
            // «madre» sin que nada lo impidiera. El dominio es cerrado y ahora
            // lo dice el tipo. La lista canónica vive en App\Models\Tutela.
            $table->enum('parentesco', ['MADRE', 'PADRE', 'TUTOR', 'ABUELO', 'ABUELA', 'OTRO'])->default('TUTOR');

            $table->timestamp('vinculado_en')->useCurrent();
            $table->boolean('activa')->default(true);

            $table->unique(['tutor_legal_id', 'alumno_id']);
            $table->index(['alumno_id', 'activa']);
            // El sentido que más se consulta: «¿qué hijos tengo?», en cada
            // carga de pantalla de la zona de familia.
            $table->index(['tutor_legal_id', 'activa']);
        });

        /**
         * Código de un solo uso para que una familia se vincule a un alumno.
         * Es un token efímero, no historial: cascada sin reparos.
         */
        Schema::create('codigos_vinculacion', function (Blueprint $table) {
            $table->string('codigo', 10)->primary();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->timestamp('caduca_en');
            $table->timestamp('usado_en')->nullable();
            $table->timestamps();

            $table->index('caduca_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_vinculacion');
        Schema::dropIfExists('tutelas');
        Schema::dropIfExists('matriculas');
        Schema::dropIfExists('imparticiones');
        Schema::dropIfExists('alumnos');
    }
};
