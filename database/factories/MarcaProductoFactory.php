<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MarcaProducto>
 */
class MarcaProductoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'activo' => true,
        ];
    }
}
