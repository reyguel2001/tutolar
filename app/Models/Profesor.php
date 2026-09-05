<?php

namespace App\Models;

use App\Models\Concerns\TieneAvatar;
use App\Support\Avatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profesor extends Model
{
    use TieneAvatar;

    protected string $tipoAvatar    = Avatar::ADULTO;
    protected string $columnaAvatar = 'avatar';   // vive en su cuenta, no aquí

    /** La foto está en `users`: el perfil y la cuenta son la misma persona. */
    public function avatarUrl(): ?string
    {
        return $this->user?->avatarUrl();
    }

    protected $table = 'profesores';
    protected $fillable = ['user_id', 'centro_id', 'nombre', 'apellidos'];

    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function centro(): BelongsTo       { return $this->belongsTo(Centro::class); }
    public function imparticiones(): HasMany  { return $this->hasMany(Imparticion::class); }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellidos}";
    }
}
