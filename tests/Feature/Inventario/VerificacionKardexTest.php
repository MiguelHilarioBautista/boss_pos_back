<?php

namespace Tests\Feature\Inventario;

use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P-09 / D-M2-6: el SUM firmado del kardex debe reconstruir exactamente
 * inventario.stock tras una serie mixta de entradas y salidas.
 */
class VerificacionKardexTest extends TestCase
{
    use RefreshDatabase;

    public function test_serie_de_entradas_y_salidas_mixtas_reconstruye_el_stock(): void
    {
        $usuario = User::factory()->create();
        $producto = Producto::factory()->create();
        $sucursal = Sucursal::factory()->create();
        DB::table('inventario')->insert([
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'stock' => 0, 'stock_minimo' => 0, 'costo_promedio' => 0,
        ]);

        $servicio = app(InventarioService::class);
        $servicio->aplicarMovimiento(['tipo' => 'ENTRADA_COMPRA', 'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id, 'cantidad' => 20, 'costo_unitario' => 4, 'usuario_id' => $usuario->id]);
        $servicio->aplicarMovimiento(['tipo' => 'SALIDA_VENTA', 'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id, 'cantidad' => 5, 'usuario_id' => $usuario->id]);
        $servicio->aplicarMovimiento(['tipo' => 'DEVOLUCION_CLIENTE', 'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id, 'cantidad' => 2, 'costo_unitario' => 4, 'usuario_id' => $usuario->id]);
        $servicio->aplicarMovimiento(['tipo' => 'MERMA', 'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id, 'cantidad' => 1, 'usuario_id' => $usuario->id]);

        $stock = DB::table('inventario')->where('producto_id', $producto->id)->where('sucursal_id', $sucursal->id)->value('stock');
        $this->assertSame('16.000', $stock); // 20 - 5 + 2 - 1

        $this->artisan('inventario:verificar-kardex')->assertExitCode(0);
    }

    public function test_detecta_discrepancia_si_el_stock_se_toca_por_fuera_del_motor(): void
    {
        $usuario = User::factory()->create();
        $producto = Producto::factory()->create();
        $sucursal = Sucursal::factory()->create();
        DB::table('inventario')->insert([
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'stock' => 0, 'stock_minimo' => 0, 'costo_promedio' => 0,
        ]);

        app(InventarioService::class)->aplicarMovimiento([
            'tipo' => 'ENTRADA_COMPRA', 'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'cantidad' => 10, 'costo_unitario' => 1, 'usuario_id' => $usuario->id,
        ]);

        // Simula una manipulacion directa fuera del motor (no deberia pasar
        // nunca en produccion, pero es justo lo que 13.3 debe detectar).
        DB::table('inventario')->where('producto_id', $producto->id)->update(['stock' => 999]);

        $this->artisan('inventario:verificar-kardex')->assertExitCode(1);
    }
}
