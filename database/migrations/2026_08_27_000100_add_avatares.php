<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto de perfil.
 *
 * Solo se guarda el **nombre del archivo**, no una ruta ni una URL: la ruta la
 * compone la aplicación a partir del disco `avatares`. Si mañana las fotos se
 * mudan a otra carpeta o a un CDN, cambia una línea de configuración y no 200
 * filas de la base de datos.
 *
 * `alumnos.foto_url` ya existía desde el diseño original y se reutiliza tal
 * cual; esta migración solo añade la columna equivalente en `users`, que es
 * donde viven profesores, familias y dirección.
 *
 * Nadie está obligado a subir nada: sin foto, la aplicación dibuja un avatar
 * generado —silueta de adulto o de niño según de quién sea— y por eso la
 * columna es nullable y no tiene valor por defecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar', 120)->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
