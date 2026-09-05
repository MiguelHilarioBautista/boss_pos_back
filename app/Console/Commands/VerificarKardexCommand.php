<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * D-M2-6 / Verificacion 13.3: el SUM firmado del kardex debe reconstruir
 * exactamente inventario.stock. M2 no la corre en linea (redundante por
 * construccion: el motor escribe stock y movimiento en la misma
 * transaccion) — este comando queda listo e inerte para que M11 lo
 * programe en el job nocturno.
 */
class VerificarKardexCommand extends Command
{
    protected $signature = 'inventario:verificar-kardex';

    protected $description = 'Verifica que el SUM firmado de movimientos_inventario reconstruya inventario.stock (13.3)';

    public function handle(): int
    {
        $discrepancias = DB::select(<<<'SQL'
            SELECT i.producto_id, i.sucursal_id, i.stock AS stock_tabla,
                   COALESCE(SUM(CASE WHEN m.tipo IN ('ENTRADA_COMPRA','DEVOLUCION_CLIENTE',
                                                      'AJUSTE_POSITIVO','TRASPASO_ENTRADA')
                                 THEN m.cantidad ELSE -m.cantidad END), 0) AS stock_kardex
              FROM inventario i
              LEFT JOIN movimientos_inventario m
                ON m.producto_id = i.producto_id AND m.sucursal_id = i.sucursal_id
             GROUP BY i.producto_id, i.sucursal_id, i.stock
            HAVING stock_tabla <> stock_kardex
        SQL);

        if (empty($discrepancias)) {
            $this->info('OK: el kardex reconstruye inventario.stock sin discrepancias.');

            return self::SUCCESS;
        }

        $this->error(count($discrepancias).' discrepancia(s) encontrada(s):');
        $this->table(['producto_id', 'sucursal_id', 'stock_tabla', 'stock_kardex'], array_map(
            fn ($d) => [$d->producto_id, $d->sucursal_id, $d->stock_tabla, $d->stock_kardex],
            $discrepancias
        ));

        return self::FAILURE;
    }
}
