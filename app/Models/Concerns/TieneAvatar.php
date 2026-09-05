<?php

namespace App\Models\Concerns;

use App\Support\Avatar;
use Illuminate\Support\Facades\Storage;

/**
 * Todo lo que el parcial `partials/avatar` necesita saber de una persona.
 *
 * El modelo que lo use declara dos cosas:
 *   · `$columnaAvatar`  — dónde guarda el nombre del archivo de su foto.
 *   · `$tipoAvatar`     — Avatar::ADULTO o Avatar::NINO.
 *
 * El resto sale de aquí, y así el parcial trata igual a un profesor y a un
 * alumno sin preguntar de qué clase es cada uno.
 */
trait TieneAvatar
{
    /** La URL de su foto, o null si no ha subido ninguna. */
    public function avatarUrl(): ?string
    {
        $archivo = $this->{$this->columnaAvatar()};

        if (! $archivo) {
            return null;
        }

        // Compatibilidad hacia atrás: si lo guardado ya era una URL o una ruta
        // absoluta —el `foto_url` original del esquema— se respeta tal cual.
        if (str_starts_with($archivo, 'http') || str_starts_with($archivo, '/')) {
            return $archivo;
        }

        return Storage::disk('avatares')->url($archivo);
    }

    public function tieneFoto(): bool
    {
        return $this->avatarUrl() !== null;
    }

    /** Adulto o niño. Decide qué silueta se dibuja cuando no hay foto. */
    public function tipoAvatar(): string
    {
        return property_exists($this, 'tipoAvatar') ? $this->tipoAvatar : Avatar::ADULTO;
    }

    /** Su color, el mismo en toda la aplicación y entre sesiones. */
    public function colorAvatar(): string
    {
        return Avatar::color($this->semillaAvatar());
    }

    /** Sobre qué texto se calcula el color; por defecto, el nombre completo. */
    protected function semillaAvatar(): string
    {
        return (string) ($this->nombre_completo ?? $this->name ?? $this->getKey());
    }

    protected function columnaAvatar(): string
    {
        return property_exists($this, 'columnaAvatar') ? $this->columnaAvatar : 'avatar';
    }
}
