<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proveedor>
 */
class ProveedorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('PROV-###')),
            'razon_social' => fake()->unique()->company(),
            'nombre_comercial' => fake()->companySuffix(),
            'rfc' => null,
            'contacto' => fake()->name(),
            'telefono' => fake()->numerify('##########'),
            'email' => fake()->unique()->companyEmail(),
            'direccion' => fake()->address(),
            'dias_credito' => 0,
            'activo' => true,
        ];
    }
}
