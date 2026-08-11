<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

/**
 * Reproduce el bloque 15 del esquema (cierra el mismo hueco que
 * ImpuestosSeeder -- ver comentario ahi).
 */
class UnidadesMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            ['nombre' => 'Pieza', 'codigo' => 'PZA', 'permite_decimales' => false],
            ['nombre' => 'Kilogramo', 'codigo' => 'KG', 'permite_decimales' => true],
            ['nombre' => 'Gramo', 'codigo' => 'G', 'permite_decimales' => true],
            ['nombre' => 'Litro', 'codigo' => 'LT', 'permite_decimales' => true],
            ['nombre' => 'Mililitro', 'codigo' => 'ML', 'permite_decimales' => true],
            ['nombre' => 'Metro', 'codigo' => 'M', 'permite_decimales' => true],
            ['nombre' => 'Caja', 'codigo' => 'CJA', 'permite_decimales' => false],
            ['nombre' => 'Paquete', 'codigo' => 'PQT', 'permite_decimales' => false],
            ['nombre' => 'Servicio', 'codigo' => 'SRV', 'permite_decimales' => false],
        ];

        foreach ($unidades as $unidad) {
            UnidadMedida::updateOrCreate(['codigo' => $unidad['codigo']], $unidad);
        }
    }
}
