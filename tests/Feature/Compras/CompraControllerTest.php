<?php

namespace Tests\Feature\Compras;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaUsuarioConPermiso;
use Tests\TestCase;

class CompraControllerTest extends TestCase
{
    use RefreshDatabase, CreaUsuarioConPermiso;

    private function payloadCompra(Proveedor $proveedor, Sucursal $sucursal, Producto $producto, array $overrides = []): array
    {
        return array_merge([
            'proveedor_id' => $proveedor->id,
            'sucursal_id' => $sucursal->id,
            'folio_documento' => 'F-0001',
            'fecha_documento' => now()->toDateString(),
            'detalle' => [
                ['producto_id' => $producto->id, 'cantidad' => 10, 'costo_unitario' => 50],
            ],
        ], $overrides);
    }

    public function test_p09_crea_compra_en_borrador_sin_afectar_inventario(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create(['costo_neto' => 40]);

        $response = $this->actuandoComo($usuario)->postJson('/api/compras', $this->payloadCompra($proveedor, $sucursal, $producto));

        $response->assertCreated();
        $response->assertJsonPath('estatus', 'BORRADOR');
        $response->assertJsonPath('total', '500.00');
        $this->assertDatabaseCount('inventario', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseHas('detalle_compra', [
            'producto_id' => $producto->id, 'numero_renglon' => 1, 'cantidad' => 10,
        ]);
    }

    public function test_p04_folio_duplicado_para_el_mismo_proveedor_devuelve_422(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        $this->actuandoComo($usuario)->postJson('/api/compras', $this->payloadCompra($proveedor, $sucursal, $producto))->assertCreated();

        $response = $this->actuandoComo($usuario)->postJson('/api/compras', $this->payloadCompra($proveedor, $sucursal, $producto));

        $response->assertStatus(422);
    }

    public function test_mismo_folio_en_otro_proveedor_si_es_valido(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedorA = Proveedor::factory()->create();
        $proveedorB = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        $this->actuandoComo($usuario)->postJson('/api/compras', $this->payloadCompra($proveedorA, $sucursal, $producto))->assertCreated();

        $response = $this->actuandoComo($usuario)->postJson('/api/compras', $this->payloadCompra($proveedorB, $sucursal, $producto));

        $response->assertCreated();
    }

    public function test_alta_con_proveedor_inactivo_devuelve_422(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create(['activo' => false]);
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        $response = $this->actuandoComo($usuario)->postJson('/api/compras', $this->payloadCompra($proveedor, $sucursal, $producto));

        $response->assertStatus(422);
    }

    public function test_detalle_vacio_devuelve_422(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        $response = $this->actuandoComo($usuario)->postJson(
            '/api/compras',
            $this->payloadCompra($proveedor, $sucursal, $producto, ['detalle' => []])
        );

        $response->assertStatus(422);
    }

    public function test_update_de_borrador_recalcula_totales(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        $creada = $this->actuandoComo($usuario)->postJson('/api/compras', $this->payloadCompra($proveedor, $sucursal, $producto));
        $compraId = $creada->json('id');

        $response = $this->actuandoComo($usuario)->putJson("/api/compras/{$compraId}", $this->payloadCompra($proveedor, $sucursal, $producto, [
            'detalle' => [['producto_id' => $producto->id, 'cantidad' => 20, 'costo_unitario' => 50]],
        ]));

        $response->assertOk();
        $response->assertJsonPath('total', '1000.00');
        $this->assertDatabaseCount('detalle_compra', 1);
    }

    public function test_update_bloqueado_si_no_es_borrador(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar', 'compra.recibir');
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        $creada = $this->actuandoComo($usuario)->postJson('/api/compras', $this->payloadCompra($proveedor, $sucursal, $producto));
        $compraId = $creada->json('id');
        $detalleId = $creada->json('detalle.0.id');

        $this->actuandoComo($usuario)->postJson("/api/compras/{$compraId}/recibir", [
            'recepciones' => [['detalle_id' => $detalleId, 'cantidad_recibida' => 10]],
        ])->assertOk();

        $response = $this->actuandoComo($usuario)->putJson("/api/compras/{$compraId}", $this->payloadCompra($proveedor, $sucursal, $producto));

        $response->assertStatus(409)->assertJson(['error' => 'estado_invalido']);
    }

    public function test_show_no_requiere_permiso_de_compras(): void
    {
        $registrador = $this->usuarioConPermisos('compra.registrar');
        $consultor = $this->usuarioConPermisos();
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        $creada = $this->actuandoComo($registrador)->postJson('/api/compras', $this->payloadCompra($proveedor, $sucursal, $producto));

        $response = $this->actuandoComo($consultor)->getJson("/api/compras/{$creada->json('id')}");

        $response->assertOk();
    }

    public function test_index_requiere_permiso_compra_registrar(): void
    {
        $usuario = $this->usuarioConPermisos();

        $this->actuandoComo($usuario)->getJson('/api/compras')->assertStatus(403);
    }
}
