<?php

namespace App\Models;

use App\Models\Concerns\TieneAvatar;
use App\Support\Avatar;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Base de autenticación de los tres roles.
 *
 * El rol se guarda aquí y el perfil concreto cuelga de él: un usuario CENTRO
 * no tiene perfil, un PROFESOR tiene `profesor` y un TUTOR_LEGAL tiene
 * `tutorLegal`.
 */
class User extends Authenticatable
{
    use Notifiable;
    use TieneAvatar;

    /** Dirección, profesorado y familias: todos adultos. */
    protected string $tipoAvatar    = Avatar::ADULTO;
    protected string $columnaAvatar = 'avatar';

    public const ROL_CENTRO      = 'CENTRO';
    public const ROL_PROFESOR    = 'PROFESOR';
    public const ROL_TUTOR_LEGAL = 'TUTOR_LEGAL';

    protected $fillable = [
        'name', 'email', 'password', 'rol', 'centro_id', 'telefono', 'activo', 'avatar',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_en'  => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
        ];
    }

    public function centro(): BelongsTo     { return $this->belongsTo(Centro::class); }
    public function profesor(): HasOne      { return $this->hasOne(Profesor::class); }
    public function tutorLegal(): HasOne    { return $this->hasOne(TutorLegal::class); }

    public function esCentro(): bool    { return $this->rol === self::ROL_CENTRO; }
    public function esProfesor(): bool  { return $this->rol === self::ROL_PROFESOR; }
    public function esTutor(): bool     { return $this->rol === self::ROL_TUTOR_LEGAL; }

    /** Nombre legible del rol, para la barra lateral. */
    public function etiquetaRol(): string
    {
        return match ($this->rol) {
            self::ROL_CENTRO      => 'Centro educativo',
            self::ROL_PROFESOR    => 'Profesorado',
            self::ROL_TUTOR_LEGAL => 'Familia',
            default               => 'Usuario',
        };
    }

    /** A dónde va este usuario nada más entrar. */
    public function rutaInicio(): string
    {
        return match ($this->rol) {
            self::ROL_CENTRO      => route('centro.panel'),
            self::ROL_PROFESOR    => route('profesor.grupos'),
            self::ROL_TUTOR_LEGAL => route('familia.inicio'),
            default               => route('login'),
        };
    }

    public function getInicialesAttribute(): string
    {
        $partes = preg_split('/\s+/', trim($this->name));
        $ini = mb_substr($partes[0] ?? '', 0, 1);
        if (count($partes) > 1) {
            $ini .= mb_substr(end($partes), 0, 1);
        }

        return mb_strtoupper($ini);
    }
}
