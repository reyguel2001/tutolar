<?php

namespace App\Models;

use App\Models\Concerns\TieneAvatar;
use App\Support\Avatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TutorLegal extends Model
{
    use TieneAvatar;

    protected string $tipoAvatar    = Avatar::ADULTO;
    protected string $columnaAvatar = 'avatar';   // vive en su cuenta, no aquí

    /** La foto está en `users`: el perfil y la cuenta son la misma persona. */
    public function avatarUrl(): ?string
    {
        return $this->user?->avatarUrl();
    }

    protected $table = 'tutores_legales';
    protected $fillable = ['user_id', 'nombre', 'apellidos', 'telefono'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Los alumnos que este tutor puede ver. Ninguna consulta debe saltarse esta relación. */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'tutelas')
            ->withPivot(['parentesco', 'activa'])
            ->wherePivot('activa', true);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellidos}";
    }
}
