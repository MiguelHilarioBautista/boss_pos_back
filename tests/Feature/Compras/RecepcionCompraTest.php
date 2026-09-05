<?php

namespace Tests\Feature\Compras;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaUsuarioConPermiso;
use Tests\TestCase;

class RecepcionCompraTest extends TestCase
{
    use RefreshDatabase, CreaUsuarioConPermiso;

    private function crearCompraBorrador(string $registrador, float $cantidad = 10, float $costo = 50): array
    {
        $usuario = $this->usuarioConPermisos($registrador);
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create(['costo_neto' => $costo]);

        $respuesta = $this->actuandoComo($usuario)->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id,
            'sucursal_id' => $sucursal->id,
            'folio_documento' => 'F-1000',
            'fecha_documento' => now()->toDateString(),
            'detalle' => [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'costo_unitario' => $costo]],
        ]);

        return [
            'usuario' => $usuario,
            'compra_id' => $respuesta->json('id'),
            'detalle_id' => $respuesta->json('detalle.0.id'),
            'producto' => $producto,
            'sucursal' => $sucursal,
        ];
    }

    public function test_p01_recepcion_completa_marca_recibida_y_genera_un_movimiento(): void
    {
        $ctx = $this->crearCompraBorrador('compra.registrar', 10, 50);
        $usuario = $this->usuarioConPermisos('compra.recibir');

        $response = $this->actuandoComo($usuario)->postJson("/api/compras/{$ctx['compra_id']}/recibir", [
            'recepciones' => [['detalle_id' => $ctx['detalle_id'], 'cantidad_recibida' => 10]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('estatus', 'RECIBIDA');
        $this->assertDatabaseHas('compras', ['id' => $ctx['compra_id'], 'estatus' => 'RECIBIDA']);
        $this->assertDatabaseHas('inventario', [
            'producto_id' => $ctx['producto']->id, 'sucursal_id' => $ctx['sucursal']->id, 'stock' => 10, 'costo_promedio' => 50,
        ]);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $ctx['producto']->id, 'tipo' => 'ENTRADA_COMPRA',
            'referencia_tabla' => 'compras', 'referencia_id' => $ctx['compra_id'],
            'costo_unitario' => 50,
        ]);
    }

    public function test_p02_recepcion_parcial_deja_estatus_parcial(): void
    {
        $ctx = $this->crearCompraBorrador('compra.registrar', 10, 50);
        $usuario = $this->usuarioConPermisos('compra.recibir');

        $response = $this->actuandoComo($usuario)->postJson("/api/compras/{$ctx['compra_id']}/recibir", [
            'recepciones' => [['detalle_id' => $ctx['detalle_id'], 'cantidad_recibida' => 4]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('estatus', 'PARCIAL');
        $this->assertDatabaseHas('detalle_compra', ['id' => $ctx['detalle_id'], 'cantidad_recibida' => 4]);
        $this->assertDatabaseHas('inventario', ['producto_id' => $ctx['producto']->id, 'stock' => 4]);
    }

    public function test_p03_recibir_sin_permiso_compra_recibir_devuelve_403(): void
    {
        $ctx = $this->crearCompraBorrador('compra.registrar', 10, 50);
        $sinPermiso = $this->usuarioConPermisos('compra.registrar');

        $response = $this->actuandoComo($sinPermiso)->postJson("/api/compras/{$ctx['compra_id']}/recibir", [
            'recepciones' => [['detalle_id' => $ctx['detalle_id'], 'cantidad_recibida' => 10]],
        ]);

        $response->assertStatus(403);
    }

    public function test_p05_recibir_una_compra_cancelada_devuelve_409(): void
    {
        $ctx = $this->crearCompraBorrador('compra.registrar', 10, 50);
        DB::table('compras')->where('id', $ctx['compra_id'])->update([
            'estatus' => 'CANCELADA', 'cancelada_por' => $ctx['usuario']->id,
            'cancelada_fecha' => now(), 'cancelada_motivo' => 'prueba',
        ]);
        $usuario = $this->usuarioConPermisos('compra.recibir');

        $response = $this->actuandoComo($usuario)->postJson("/api/compras/{$ctx['compra_id']}/recibir", [
            'recepciones' => [['detalle_id' => $ctx['detalle_id'], 'cantidad_recibida' => 10]],
        ]);

        $response->assertStatus(409)->assertJson(['error' => 'estado_invalido']);
    }

    public function test_sobre_recepcion_devuelve_422(): void
    {
        $ctx = $this->crearCompraBorrador('compra.registrar', 10, 50);
        $usuario = $this->usuarioConPermisos('compra.recibir');

        $response = $this->actuandoComo($usuario)->postJson("/api/compras/{$ctx['compra_id']}/recibir", [
            'recepciones' => [['detalle_id' => $ctx['detalle_id'], 'cantidad_recibida' => 15]],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_p10_costo_neto_sin_iva_es_el_que_llega_al_kardex(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        $creada = $this->actuandoComo($usuario)->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id,
            'sucursal_id' => $sucursal->id,
            'folio_documento' => 'F-2000',
            'fecha_documento' => now()->toDateString(),
            'detalle' => [[
                'producto_id' => $producto->id, 'cantidad' => 5, 'costo_unitario' => 100, 'tasa_iva' => 0.16,
            ]],
        ]);

        $receptor = $this->usuarioConPermisos('compra.recibir');
        $this->actuandoComo($receptor)->postJson("/api/compras/{$creada->json('id')}/recibir", [
            'recepciones' => [['detalle_id' => $creada->json('detalle.0.id'), 'cantidad_recibida' => 5]],
        ])->assertOk();

        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $producto->id, 'costo_unitario' => 100,
        ]);
        $this->assertDatabaseHas('inventario', [
            'producto_id' => $producto->id, 'costo_promedio' => 100,
        ]);
    }

    public function test_producto_servicio_no_genera_movimiento_pero_actualiza_cantidad_recibida(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $servicio = Producto::factory()->create(['controla_stock' => false, 'tipo_prod_serv' => 'SERVICIO']);

        $creada = $this->actuandoComo($usuario)->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id,
            'sucursal_id' => $sucursal->id,
            'folio_documento' => 'F-3000',
            'fecha_documento' => now()->toDateString(),
            'detalle' => [['producto_id' => $servicio->id, 'cantidad' => 1, 'costo_unitario' => 200]],
        ]);

        $receptor = $this->usuarioConPermisos('compra.recibir');
        $response = $this->actuandoComo($receptor)->postJson("/api/compras/{$creada->json('id')}/recibir", [
            'recepciones' => [['detalle_id' => $creada->json('detalle.0.id'), 'cantidad_recibida' => 1]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('estatus', 'RECIBIDA');
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('inventario', 0);
        $this->assertDatabaseHas('detalle_compra', ['producto_id' => $servicio->id, 'cantidad_recibida' => 1]);
    }
}
