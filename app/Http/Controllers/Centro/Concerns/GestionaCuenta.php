<?php

namespace App\Http\Controllers\Centro\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Tutores y profesores comparten forma: un perfil colgado de una cuenta de
 * `users`. Las reglas de la parte de cuenta viven aquí para no escribirlas dos
 * veces y, sobre todo, para no escribirlas distinto en cada sitio.
 */
trait GestionaCuenta
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function reglasDeCuenta(?User $usuario = null): array
    {
        return [
            'email'    => [
                'required', 'email', 'max:180',
                Rule::unique('users', 'email')->ignore($usuario?->id),
            ],
            'password' => $usuario
                ? ['nullable', 'string', 'min:8', 'confirmed']
                : ['required', 'string', 'min:8', 'confirmed'],
            'activo'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    protected function crearCuenta(array $datos, string $rol, string $nombreVisible, int $centroId): User
    {
        return User::create([
            'name'      => $nombreVisible,
            'email'     => $datos['email'],
            'password'  => $datos['password'],
            'rol'       => $rol,
            'centro_id' => $centroId,
            'telefono'  => $datos['telefono'] ?? null,
            'activo'    => (bool) ($datos['activo'] ?? true),
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    protected function actualizarCuenta(User $usuario, array $datos, string $nombreVisible): void
    {
        $usuario->name     = $nombreVisible;
        $usuario->email    = $datos['email'];
        $usuario->telefono = $datos['telefono'] ?? $usuario->telefono;
        $usuario->activo   = (bool) ($datos['activo'] ?? false);

        // La contraseña solo se toca si viene rellena: dejar el campo vacío
        // significa «no la cambies», no «bórrala».
        if (! empty($datos['password'])) {
            $usuario->password = $datos['password'];
        }

        $usuario->save();
    }
}
