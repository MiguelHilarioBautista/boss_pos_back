<?php

namespace Database\Factories;

use App\Models\CategoriaProducto;
use App\Models\Impuesto;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Producto>
 */
class ProductoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tipo_prod_serv' => 'PRODUCTO',
            'nombre' => fake()->unique()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'codigo_barras' => fake()->unique()->numerify('750############'),
            'descripcion' => fake()->sentence(),
            'precio' => fake()->randomFloat(2, 10, 500),
            'costo_neto' => fake()->randomFloat(2, 5, 300),
            'impuesto_id' => Impuesto::factory(),
            'controla_stock' => true,
            'activo' => true,
            'id_categoria' => CategoriaProducto::factory(),
            'id_marca' => null,
            'id_unidad_medida' => UnidadMedida::factory(),
        ];
    }
}
