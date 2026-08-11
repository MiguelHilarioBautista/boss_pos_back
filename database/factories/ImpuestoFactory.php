<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Impuesto>
 */
class ImpuestoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->lexify('IMP????')),
            'nombre' => fake()->words(2, true),
            'tasa' => 0.16,
            'activo' => true,
        ];
    }
}
