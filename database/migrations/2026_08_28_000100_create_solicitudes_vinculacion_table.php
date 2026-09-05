<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de vinculación: «soy familia de este alumno, dadme un código».
 *
 * Es la pieza que faltaba para que una familia sin código pueda arrancar sola
 * el trámite sin que eso abra ninguna puerta.
 *
 * LA DECISIÓN QUE HAY QUE PODER DEFENDER. Los datos del alumno se guardan aquí
 * **como texto suelto**, no como una clave foránea a `alumnos`. Es a propósito:
 * quien rellena el formulario todavía no es nadie, y si la solicitud tuviera que
 * apuntar a un alumno concreto, el formulario tendría que buscarlo y decir si
 * existe. Eso convertiría una pantalla pública en un comprobador de matrículas:
 * «¿está Lucía Ramos en este instituto?» respondido a cualquiera.
 *
 * Aquí la solicitud es una **declaración sin verificar**. El cruce con los
 * registros reales lo hace una persona del centro, que ya sabe quién es quién,
 * y la clave foránea a `alumnos` solo aparece al aprobarla, en el
 * `codigos_vinculacion` que se genera entonces.
 *
 * Por eso tampoco hay unicidad ni índices sobre los datos del alumno: no son
 * una identidad, son lo que alguien escribió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_vinculacion', function (Blueprint $table) {
            $table->id();

            // El centro sí es dato público —los institutos están en el mapa—,
            // así que este sí es un desplegable de verdad.
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();

            // Lo que la familia declara del alumno. Texto, sin verificar.
            $table->string('alumno_nombre', 100);
            $table->string('alumno_apellidos', 150);
            $table->string('alumno_curso', 80)->nullable();   // «3º ESO B», tal cual lo escriban

            // Quién solicita.
            $table->string('nombre', 100);
            $table->string('apellidos', 150);
            $table->string('email', 180);
            $table->string('telefono', 20);
            $table->enum('parentesco', ['MADRE', 'PADRE', 'TUTOR', 'ABUELO', 'ABUELA', 'OTRO'])->default('TUTOR');
            $table->text('mensaje')->nullable();

            $table->enum('estado', ['PENDIENTE', 'APROBADA', 'RECHAZADA'])->default('PENDIENTE');

            // Quién la resolvió y con qué código, si se aprobó. `nullOnDelete`
            // en el usuario: se pierde el enlace, no el hecho.
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelta_en')->nullable();
            $table->string('codigo_emitido', 10)->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamps();

            // La bandeja del centro: las pendientes primero, las más viejas arriba.
            $table->index(['centro_id', 'estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_vinculacion');
    }
};
