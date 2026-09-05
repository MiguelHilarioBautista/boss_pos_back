<?php

namespace App\Console\Commands;

use App\Models\CategoriaProducto;
use App\Models\Impuesto;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\UnidadMedida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Solo para scripts/prueba-concurrencia-inventario.sh (P-07). Crea un
 * producto + fila de inventario con stock conocido, aislado de cualquier
 * dato real, e imprime "producto_id sucursal_id" para que el script de
 * shell los capture.
 */
class InventarioSembrarPruebaConcurrenciaCommand extends Command
{
    protected $signature = 'inventario:sembrar-prueba-concurrencia {stock=10}';

    protected $description = 'Siembra un producto aislado con stock conocido para la prueba de concurrencia real';

    public function handle(): int
    {
        $categoria = CategoriaProducto::firstOrCreate(['nombre' => 'Prueba Concurrencia']);
        $unidad = UnidadMedida::firstOrCreate(['codigo' => 'PZA'], ['nombre' => 'Pieza', 'permite_decimales' => false]);
        $impuesto = Impuesto::firstOrCreate(['codigo' => 'IVA16'], ['nombre' => 'IVA 16%', 'tasa' => 0.16]);
        $sucursal = Sucursal::query()->first() ?? Sucursal::factory()->create();

        $producto = Producto::create([
            'nombre' => 'Producto concurrencia '.uniqid(),
            'sku' => 'CONC-'.uniqid(),
            'precio' => 10,
            'costo_neto' => 5,
            'impuesto_id' => $impuesto->id,
            'id_categoria' => $categoria->id,
            'id_unidad_medida' => $unidad->id,
        ]);

        DB::table('inventario')->insert([
            'producto_id' => $producto->id,
            'sucursal_id' => $sucursal->id,
            'stock' => (int) $this->argument('stock'),
            'stock_minimo' => 0,
            'costo_promedio' => 5,
        ]);

        $this->line("{$producto->id} {$sucursal->id}");

        return self::SUCCESS;
    }
}
