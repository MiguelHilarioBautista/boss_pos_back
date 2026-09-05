<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * E2/P-04 (M2): salida o ajuste negativo que dejaria stock < 0. Se lanza
 * SIEMPRE dentro de una DB::transaction en InventarioService, por lo que
 * lanzarla provoca rollback automatico (ninguna fila de movimientos_inventario
 * ni cambio de stock queda a medias).
 */
class StockInsuficienteException extends RuntimeException
{
    public static function paraProducto(int $productoId, int $sucursalId, string $disponible, string $solicitado): self
    {
        return new self(
            "Stock insuficiente para el producto {$productoId} en la sucursal {$sucursalId}: ".
            "disponible {$disponible}, solicitado {$solicitado}."
        );
    }
}
