<?php

namespace App\Console\Commands;

use App\Exceptions\StockInsuficienteException;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Console\Command;

/**
 * Pieza de la prueba de concurrencia real (criterio de aceptacion
 * explicito de M2: 50 peticiones simultaneas, stock 10 -> 10 exitos, 40
 * rechazos, nunca negativo). pcntl_fork no existe en Windows, asi que la
 * paralelizacion real se logra invocando este comando 50 veces como
 * procesos de shell independientes (ver scripts/prueba-concurrencia-inventario.sh)
 * — cada invocacion es un proceso PHP nuevo con su propia conexion a
 * MySQL, no hilos ni Promise.all simulados en un solo proceso.
 */
class InventarioDecrementarUnoCommand extends Command
{
    protected $signature = 'inventario:decrementar-uno {producto} {sucursal} {cantidad=1}';

    protected $description = 'Aplica UNA salida (AJUSTE_NEGATIVO) — usado solo por la prueba de concurrencia real';

    public function handle(InventarioService $servicio): int
    {
        $usuarioId = User::query()->value('id');

        try {
            $servicio->aplicarMovimiento([
                'tipo' => 'AJUSTE_NEGATIVO',
                'producto_id' => (int) $this->argument('producto'),
                'sucursal_id' => (int) $this->argument('sucursal'),
                'cantidad' => (float) $this->argument('cantidad'),
                'usuario_id' => $usuarioId,
                'motivo' => 'Prueba de concurrencia (P-07)',
            ]);

            $this->line('OK');

            return self::SUCCESS;
        } catch (StockInsuficienteException $e) {
            $this->line('STOCK_INSUFICIENTE');

            return self::FAILURE;
        }
    }
}
