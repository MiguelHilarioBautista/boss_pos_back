<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnidadMedida>
 */
class UnidadMedidaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'codigo' => strtoupper(fake()->unique()->lexify('U??')),
            'permite_decimales' => false,
            'activo' => true,
        ];
    }
}
