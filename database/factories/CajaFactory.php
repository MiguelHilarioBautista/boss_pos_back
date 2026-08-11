<?php

namespace Database\Factories;

use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Caja>
 */
class CajaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sucursal_id' => Sucursal::factory(),
            'codigo' => strtoupper(fake()->unique()->lexify('CAJA-??')),
            'nombre' => 'Caja '.fake()->unique()->numberBetween(1, 999),
            'serie_folio' => strtoupper(fake()->unique()->lexify('?##')),
            'activo' => true,
        ];
    }
}
