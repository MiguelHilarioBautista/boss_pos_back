<?php

namespace Tests\Feature\Inventario;

use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KardexTest extends TestCase
{
    use RefreshDatabase;

    public function test_p02_consulta_de_kardex_filtrada_devuelve_movimientos_con_snapshots(): void
    {
        $usuario = User::factory()->create();
        $producto = Producto::factory()->create();
        $sucursalA = Sucursal::factory()->create();
        $sucursalB = Sucursal::factory()->create();

        DB::table('inventario')->insert([
            ['producto_id' => $producto->id, 'sucursal_id' => $sucursalA->id, 'stock' => 0, 'stock_minimo' => 0, 'costo_promedio' => 0],
            ['producto_id' => $producto->id, 'sucursal_id' => $sucursalB->id, 'stock' => 0, 'stock_minimo' => 0, 'costo_promedio' => 0],
        ]);

        $servicio = app(InventarioService::class);
        $servicio->aplicarMovimiento([
            'tipo' => 'ENTRADA_COMPRA', 'producto_id' => $producto->id, 'sucursal_id' => $sucursalA->id,
            'cantidad' => 10, 'costo_unitario' => 5, 'usuario_id' => $usuario->id,
        ]);
        $servicio->aplicarMovimiento([
            'tipo' => 'ENTRADA_COMPRA', 'producto_id' => $producto->id, 'sucursal_id' => $sucursalB->id,
            'cantidad' => 3, 'costo_unitario' => 5, 'usuario_id' => $usuario->id,
        ]);

        $response = $this->actingAs($usuario, 'web')
            ->getJson("/api/inventario/{$producto->id}/kardex?sucursal_id={$sucursalA->id}");

        $response->assertOk();
        $movimientos = $response->json('movimientos');
        $this->assertCount(1, $movimientos);
        $this->assertSame('10.000', $movimientos[0]['stock_nuevo']);
        $this->assertEquals($sucursalA->id, $movimientos[0]['sucursal_id']);
    }

    public function test_p05_update_directo_sobre_movimientos_inventario_lanza_error_del_trigger(): void
    {
        $usuario = User::factory()->create();
        $producto = Producto::factory()->create();
        $sucursal = Sucursal::factory()->create();
        DB::table('inventario')->insert([
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'stock' => 0, 'stock_minimo' => 0, 'costo_promedio' => 0,
        ]);

        $mov = app(InventarioService::class)->aplicarMovimiento([
            'tipo' => 'ENTRADA_COMPRA', 'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'cantidad' => 1, 'costo_unitario' => 1, 'usuario_id' => $usuario->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('movimientos_inventario')->where('id', $mov->id)->update(['cantidad' => 99]);
    }

    public function test_sin_sesion_devuelve_401(): void
    {
        $producto = Producto::factory()->create();

        $response = $this->getJson("/api/inventario/{$producto->id}/kardex");

        $response->assertStatus(401);
    }
}
