<?php

namespace App\Services;

use App\Exceptions\StockInsuficienteException;
use App\Models\MovimientoInventario;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Motor unico de movimientos de inventario (M2). Todo modulo que necesite
 * modificar existencias (M3 compras, M5 ventas, M6 devoluciones, M9
 * traspasos/conteos) debe pasar por aqui — nunca tocar `inventario` ni
 * `movimientos_inventario` directo desde otro controlador/servicio.
 *
 * Contrato de bloqueo (D-M2-2, decidido: SELECT ... FOR UPDATE uniforme
 * para entrada y salida, no el UPDATE condicional del PDF S4.5 — ver
 * justificacion en el plan de M2). Cada llamada bloquea UNA fila
 * (producto_id, sucursal_id) dentro de su propia transaccion. Si el
 * llamador necesita mover varios productos en una sola operacion de
 * negocio (p.ej. una venta con varios renglones), DEBE invocar este
 * metodo una vez por renglon en orden ASCENDENTE de producto_id, para
 * mantener un orden canonico de bloqueo entre transacciones concurrentes
 * y evitar deadlocks (mismo criterio de Reglas de negocio.pdf S5.2).
 *
 * CONTEO_FISICO no esta en el mapa de direccion: puede ser + o - segun la
 * diferencia, y M9 (todavia no construido) decidira cual lado le
 * corresponde antes de llamar aqui.
 */
class InventarioService
{
    private const ENTRADAS = ['ENTRADA_COMPRA', 'DEVOLUCION_CLIENTE', 'AJUSTE_POSITIVO', 'TRASPASO_ENTRADA'];

    private const SALIDAS = ['SALIDA_VENTA', 'DEVOLUCION_PROVEEDOR', 'AJUSTE_NEGATIVO', 'TRASPASO_SALIDA', 'MERMA'];

    /**
     * @param  array{
     *     tipo: string, producto_id: int, sucursal_id: int, cantidad: float,
     *     usuario_id: int, costo_unitario?: float|null, motivo?: string|null,
     *     referencia_tabla?: string|null, referencia_id?: int|null,
     * }  $datos
     */
    public function aplicarMovimiento(array $datos): MovimientoInventario
    {
        $tipo = $datos['tipo'];
        $esEntrada = in_array($tipo, self::ENTRADAS, true);
        $esSalida = in_array($tipo, self::SALIDAS, true);

        if (! $esEntrada && ! $esSalida) {
            throw new InvalidArgumentException("Tipo de movimiento no soportado por el motor generico: {$tipo}.");
        }

        return DB::transaction(function () use ($datos, $tipo, $esEntrada) {
            $productoId = (int) $datos['producto_id'];
            $sucursalId = (int) $datos['sucursal_id'];
            $cantidad = (string) $datos['cantidad'];

            if ($esEntrada) {
                // Idempotente: si dos entradas concurrentes de un producto
                // nuevo compiten aqui, solo una inserta; ambas siguen abajo
                // al SELECT ... FOR UPDATE, que las serializa correctamente.
                DB::table('inventario')->insertOrIgnore([
                    'producto_id' => $productoId,
                    'sucursal_id' => $sucursalId,
                    'stock' => 0,
                    'stock_minimo' => 0,
                    'costo_promedio' => 0,
                ]);
            }

            $actual = DB::table('inventario')
                ->where('producto_id', $productoId)
                ->where('sucursal_id', $sucursalId)
                ->lockForUpdate()
                ->first();

            if (! $actual) {
                // Salida sobre una fila que ni siquiera existe: 0 disponible.
                throw StockInsuficienteException::paraProducto($productoId, $sucursalId, '0', $cantidad);
            }

            $stockAnterior = (string) $actual->stock;
            $costoPromAnterior = (string) $actual->costo_promedio;

            if ($esEntrada) {
                // Regla 4.2: costo promedio se calcula ANTES de tocar el
                // stock, usando el stock/costo leidos bajo bloqueo arriba.
                $costoCompra = (string) ($datos['costo_unitario'] ?? $costoPromAnterior);

                $costoPromNuevo = bccomp($stockAnterior, '0', 3) <= 0
                    ? $costoCompra
                    : bcdiv(
                        bcadd(bcmul($stockAnterior, $costoPromAnterior, 6), bcmul($cantidad, $costoCompra, 6), 6),
                        bcadd($stockAnterior, $cantidad, 3),
                        6
                    );

                $stockNuevo = bcadd($stockAnterior, $cantidad, 3);
                $costoUnitarioMovimiento = round((float) $costoCompra, 2);
                $costoPromNuevoRedondeado = round((float) $costoPromNuevo, 2);
            } else {
                if (bccomp($stockAnterior, $cantidad, 3) < 0) {
                    throw StockInsuficienteException::paraProducto($productoId, $sucursalId, $stockAnterior, $cantidad);
                }

                // Una salida nunca recalcula el costo promedio (Regla 4.2 /
                // Reglas de negocio S6.4: solo las entradas lo alteran).
                $stockNuevo = bcsub($stockAnterior, $cantidad, 3);
                $costoUnitarioMovimiento = round((float) $costoPromAnterior, 2);
                $costoPromNuevoRedondeado = round((float) $costoPromAnterior, 2);
            }

            DB::table('inventario')
                ->where('producto_id', $productoId)
                ->where('sucursal_id', $sucursalId)
                ->update(['stock' => $stockNuevo, 'costo_promedio' => $costoPromNuevoRedondeado]);

            $movimientoId = DB::table('movimientos_inventario')->insertGetId([
                'producto_id' => $productoId,
                'sucursal_id' => $sucursalId,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'costo_unitario' => $costoUnitarioMovimiento,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'costo_prom_anterior' => round((float) $costoPromAnterior, 2),
                'costo_prom_nuevo' => $costoPromNuevoRedondeado,
                'referencia_tabla' => $datos['referencia_tabla'] ?? null,
                'referencia_id' => $datos['referencia_id'] ?? null,
                'usuario_id' => $datos['usuario_id'],
                'motivo' => $datos['motivo'] ?? null,
                'fecha' => now(),
            ]);

            return MovimientoInventario::findOrFail($movimientoId);
        });
    }
}
