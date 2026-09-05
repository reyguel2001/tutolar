<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOMINIO 2 · IDENTIDAD
 *
 * `users` es la única tabla de autenticación de los tres roles, y cada rol
 * cuelga de ella con su tabla de perfil. Un usuario tiene exactamente un rol:
 * si alguien es profesor del centro y padre de un alumno necesita dos cuentas.
 * Es deliberado — mantiene la separación de permisos comprobable de un vistazo.
 *
 * Va antes que la estructura académica porque `grupos.tutor_grupo_id` apunta a
 * `profesores`. En la versión anterior del esquema esa clave foránea se añadía
 * en una migración posterior, con un `Schema::table` suelto; ordenando los
 * dominios por dependencia esa costura desaparece.
 *
 * `users.centro_id` es nullable a propósito: un tutor legal puede existir sin
 * pertenecer a ningún centro (pertenece a través de los alumnos que tutela).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['CENTRO', 'PROFESOR', 'TUTOR_LEGAL'])->default('TUTOR_LEGAL')->after('email');
            $table->foreignId('centro_id')->nullable()->after('rol')->constrained('centros')->restrictOnDelete();
            $table->string('telefono', 20)->nullable()->after('centro_id');
            $table->boolean('activo')->default(true)->after('telefono');
            $table->timestamp('ultimo_acceso_en')->nullable()->after('activo');

            // El listado de cuentas de dirección filtra exactamente por estas
            // tres columnas y ordena por nombre.
            $table->index(['centro_id', 'rol', 'activo'], 'users_centro_rol_activo_idx');
        });

        Schema::create('profesores', function (Blueprint $table) {
            $table->id();
            // El perfil no sobrevive a su cuenta: es la misma persona.
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('apellidos', 150);
            $table->timestamps();

            $table->index(['centro_id', 'apellidos']);
        });

        Schema::create('tutores_legales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('apellidos', 150);
            $table->string('telefono', 20);
            $table->timestamps();

            $table->index('apellidos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutores_legales');
        Schema::dropIfExists('profesores');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_centro_rol_activo_idx');
            $table->dropConstrainedForeignId('centro_id');
            $table->dropColumn(['rol', 'telefono', 'activo', 'ultimo_acceso_en']);
        });
    }
};
