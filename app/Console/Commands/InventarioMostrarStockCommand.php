<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Utilidad minima para scripts/prueba-concurrencia-inventario.sh: imprime
 * el stock actual de una fila de inventario sin pasar por tinker (evita
 * problemas de quoting/heredoc en scripts de shell).
 */
class InventarioMostrarStockCommand extends Command
{
    protected $signature = 'inventario:mostrar-stock {producto} {sucursal}';

    protected $description = 'Imprime el stock actual de un producto/sucursal';

    public function handle(): int
    {
        $stock = DB::table('inventario')
            ->where('producto_id', $this->argument('producto'))
            ->where('sucursal_id', $this->argument('sucursal'))
            ->value('stock');

        $this->line((string) $stock);

        return self::SUCCESS;
    }
}
