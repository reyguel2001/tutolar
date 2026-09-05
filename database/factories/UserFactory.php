<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('tutolar2026'),
            'remember_token' => Str::random(10),
            'rol' => User::ROL_TUTOR_LEGAL,
            'activo' => true,
        ];
    }

    public function centro(): static
    {
        return $this->state(fn () => ['rol' => User::ROL_CENTRO]);
    }

    public function profesor(): static
    {
        return $this->state(fn () => ['rol' => User::ROL_PROFESOR]);
    }
}
