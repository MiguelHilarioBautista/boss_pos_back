<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->lexify('ROL_????')),
            'nombre' => fake()->jobTitle(),
            'descripcion' => null,
            'activo' => true,
        ];
    }
}
