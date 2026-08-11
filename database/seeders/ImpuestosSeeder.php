<?php

namespace Database\Seeders;

use App\Models\Impuesto;
use Illuminate\Database\Seeder;

/**
 * Reproduce el bloque 15 del esquema. M1 SS1.5 da por hecho que esto ya esta
 * cargado desde M0 -- cierra ese hueco (no se sembro en la entrega de M0).
 */
class ImpuestosSeeder extends Seeder
{
    public function run(): void
    {
        $impuestos = [
            ['codigo' => 'IVA16', 'nombre' => 'IVA 16%', 'tasa' => 0.1600],
            ['codigo' => 'IVA8FRONTERA', 'nombre' => 'IVA 8% frontera', 'tasa' => 0.0800],
            ['codigo' => 'IVA0', 'nombre' => 'Tasa 0%', 'tasa' => 0.0000],
            ['codigo' => 'EXENTO', 'nombre' => 'Exento de IVA', 'tasa' => 0.0000],
        ];

        foreach ($impuestos as $impuesto) {
            Impuesto::updateOrCreate(['codigo' => $impuesto['codigo']], $impuesto);
        }
    }
}
