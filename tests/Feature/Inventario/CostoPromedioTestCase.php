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
 * Base compartida para las pruebas unitarias de InventarioService: producto/
 * sucursal de prueba + helpers para armar el array que espera
 * aplicarMovimiento() sin repetir boilerplate en cada caso de Regla 4.2.
 */
abstract class CostoPromedioTestCase extends TestCase
{
    use RefreshDatabase;

    protected InventarioService $servicio;

    protected Producto $producto;

    protected Sucursal $sucursal;

    protected int $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(InventarioService::class);
        $this->producto = Producto::factory()->create();
        $this->sucursal = Sucursal::factory()->create();
        $this->usuarioId = User::factory()->create()->id;
    }

    protected function crearInventario(float $stock, float $costoPromedio): void
    {
        DB::table('inventario')->insert([
            'producto_id' => $this->producto->id,
            'sucursal_id' => $this->sucursal->id,
            'stock' => $stock,
            'stock_minimo' => 0,
            'costo_promedio' => $costoPromedio,
        ]);
    }

    protected function entrada(float $cantidad, float $costo): array
    {
        return [
            'tipo' => 'ENTRADA_COMPRA',
            'producto_id' => $this->producto->id,
            'sucursal_id' => $this->sucursal->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'usuario_id' => $this->usuarioId,
            'referencia_tabla' => 'compras',
            'referencia_id' => 1,
        ];
    }

    protected function salida(float $cantidad): array
    {
        return [
            'tipo' => 'SALIDA_VENTA',
            'producto_id' => $this->producto->id,
            'sucursal_id' => $this->sucursal->id,
            'cantidad' => $cantidad,
            'usuario_id' => $this->usuarioId,
            'referencia_tabla' => 'ventas',
            'referencia_id' => 1,
        ];
    }
}
