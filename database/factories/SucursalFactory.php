<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sucursal>
 */
class SucursalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->city(),
            'codigo' => strtoupper(fake()->unique()->lexify('SUC-???')),
            'direccion' => fake()->address(),
            'telefono' => fake()->numerify('##########'),
            'zona_frontera' => false,
            'activo' => true,
        ];
    }
}
