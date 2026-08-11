<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CategoriaProducto>
 */
class CategoriaProductoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'categoria_padre_id' => null,
            'activo' => true,
        ];
    }
}
