<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rol_id' => Role::factory(),
            'usuario' => fake()->unique()->userName(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'pin_hash' => null,
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'numero_empleado' => null,
            'email' => fake()->unique()->safeEmail(),
            'telefono' => null,
            'activo' => true,
            'debe_cambiar_pass' => false,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso' => null,
            'password_actualizado' => now(),
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    public function debeCambiarPassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'debe_cambiar_pass' => true,
        ]);
    }

    public function bloqueado(): static
    {
        return $this->state(fn (array $attributes) => [
            'bloqueado_hasta' => now()->addMinutes(15),
        ]);
    }

    public function conPin(string $pin): static
    {
        return $this->state(fn (array $attributes) => [
            'pin_hash' => Hash::make($pin),
        ]);
    }
}
