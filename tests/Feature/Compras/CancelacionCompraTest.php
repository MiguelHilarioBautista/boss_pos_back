<?php

namespace Tests\Feature\Compras;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaUsuarioConPermiso;
use Tests\TestCase;

class CancelacionCompraTest extends TestCase
{
    use RefreshDatabase, CreaUsuarioConPermiso;

    private function crearCompraBorrador(): array
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        $respuesta = $this->actuandoComo($usuario)->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id,
            'sucursal_id' => $sucursal->id,
            'folio_documento' => 'F-9000',
            'fecha_documento' => now()->toDateString(),
            'detalle' => [['producto_id' => $producto->id, 'cantidad' => 10, 'costo_unitario' => 50]],
        ]);

        return [
            'usuario' => $usuario,
            'compra_id' => $respuesta->json('id'),
            'detalle_id' => $respuesta->json('detalle.0.id'),
            'producto' => $producto,
            'sucursal' => $sucursal,
        ];
    }

    public function test_cancela_una_compra_en_borrador_con_motivo(): void
    {
        $ctx = $this->crearCompraBorrador();

        $response = $this->actuandoComo($ctx['usuario'])->postJson("/api/compras/{$ctx['compra_id']}/cancelar", [
            'motivo' => 'Proveedor ya no tiene existencias',
        ]);

        $response->assertOk();
        $response->assertJsonPath('estatus', 'CANCELADA');
        $this->assertDatabaseHas('compras', [
            'id' => $ctx['compra_id'], 'estatus' => 'CANCELADA', 'cancelada_por' => $ctx['usuario']->id,
        ]);
    }

    public function test_cancelar_sin_motivo_devuelve_422(): void
    {
        $ctx = $this->crearCompraBorrador();

        $response = $this->actuandoComo($ctx['usuario'])->postJson("/api/compras/{$ctx['compra_id']}/cancelar", []);

        $response->assertStatus(422);
    }

    public function test_cancela_el_resto_de_una_compra_parcial(): void
    {
        $ctx = $this->crearCompraBorrador();
        $receptor = $this->usuarioConPermisos('compra.recibir');

        $this->actuandoComo($receptor)->postJson("/api/compras/{$ctx['compra_id']}/recibir", [
            'recepciones' => [['detalle_id' => $ctx['detalle_id'], 'cantidad_recibida' => 4]],
        ])->assertOk();

        $response = $this->actuandoComo($ctx['usuario'])->postJson("/api/compras/{$ctx['compra_id']}/cancelar", [
            'motivo' => 'Se cancela el resto pendiente',
        ]);

        $response->assertOk();
        $response->assertJsonPath('estatus', 'CANCELADA');
    }

    public function test_cancelar_una_compra_recibida_devuelve_409(): void
    {
        $ctx = $this->crearCompraBorrador();
        $receptor = $this->usuarioConPermisos('compra.recibir');

        $this->actuandoComo($receptor)->postJson("/api/compras/{$ctx['compra_id']}/recibir", [
            'recepciones' => [['detalle_id' => $ctx['detalle_id'], 'cantidad_recibida' => 10]],
        ])->assertOk();

        $response = $this->actuandoComo($ctx['usuario'])->postJson("/api/compras/{$ctx['compra_id']}/cancelar", [
            'motivo' => 'Intento tardio',
        ]);

        $response->assertStatus(409)->assertJson(['error' => 'estado_invalido']);
    }

    public function test_lo_ya_recibido_no_se_revierte_al_cancelar_el_resto(): void
    {
        $ctx = $this->crearCompraBorrador();
        $receptor = $this->usuarioConPermisos('compra.recibir');

        $this->actuandoComo($receptor)->postJson("/api/compras/{$ctx['compra_id']}/recibir", [
            'recepciones' => [['detalle_id' => $ctx['detalle_id'], 'cantidad_recibida' => 4]],
        ])->assertOk();

        $this->actuandoComo($ctx['usuario'])->postJson("/api/compras/{$ctx['compra_id']}/cancelar", [
            'motivo' => 'Se cancela el resto pendiente',
        ])->assertOk();

        $this->assertDatabaseHas('inventario', ['producto_id' => $ctx['producto']->id, 'stock' => 4]);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertDatabaseHas('detalle_compra', ['id' => $ctx['detalle_id'], 'cantidad_recibida' => 4]);
    }

    public function test_cancelar_una_compra_ya_cancelada_devuelve_409(): void
    {
        $ctx = $this->crearCompraBorrador();

        $this->actuandoComo($ctx['usuario'])->postJson("/api/compras/{$ctx['compra_id']}/cancelar", [
            'motivo' => 'Primera cancelacion',
        ])->assertOk();

        $response = $this->actuandoComo($ctx['usuario'])->postJson("/api/compras/{$ctx['compra_id']}/cancelar", [
            'motivo' => 'Segunda cancelacion',
        ]);

        $response->assertStatus(409)->assertJson(['error' => 'estado_invalido']);
    }
}
