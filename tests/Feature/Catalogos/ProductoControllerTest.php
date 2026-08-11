<?php

namespace Tests\Feature\Catalogos;

use App\Models\CategoriaProducto;
use App\Models\Impuesto;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaUsuarioConPermiso;
use Tests\TestCase;

class ProductoControllerTest extends TestCase
{
    use RefreshDatabase, CreaUsuarioConPermiso;

    private function payloadBase(): array
    {
        return [
            'nombre' => 'Refresco 600ml',
            'sku' => 'ABC-001',
            'codigo_barras' => '7501234567890',
            'precio' => 18.50,
            'costo_neto' => 12.00,
            'impuesto_id' => Impuesto::factory()->create()->id,
            'id_categoria' => CategoriaProducto::factory()->create()->id,
            'id_unidad_medida' => UnidadMedida::factory()->create()->id,
            'controla_stock' => true,
        ];
    }

    public function test_p01_alta_de_producto_valido_devuelve_margen_calculado(): void
    {
        $usuario = $this->usuarioConPermisos('producto.crear');

        $response = $this->actingAs($usuario, 'web')->postJson('/api/productos', $this->payloadBase());

        $response->assertCreated();
        $this->assertEquals(6.50, (float) $response->json('margen_unitario'));
        $this->assertDatabaseHas('productos', ['sku' => 'ABC-001']);
    }

    public function test_p04_sku_duplicado_devuelve_422_no_500(): void
    {
        $usuario = $this->usuarioConPermisos('producto.crear');
        Producto::factory()->create(['sku' => 'ABC-001']);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/productos', $this->payloadBase());

        $response->assertStatus(422);
    }

    public function test_p02_cambio_de_precio_con_permiso_genera_historial_con_usuario_correcto(): void
    {
        $usuario = $this->usuarioConPermisos('producto.crear', 'precio.modificar');
        $producto = Producto::factory()->create(['precio' => 100, 'costo_neto' => 60]);

        $payload = $this->productoComoPayload($producto);
        $payload['precio'] = 150;

        $response = $this->actingAs($usuario, 'web')->putJson("/api/productos/{$producto->id}", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('historial_precios', [
            'producto_id' => $producto->id,
            'precio_anterior' => 100.00,
            'precio_nuevo' => 150.00,
            'usuario_id' => $usuario->id,
        ]);
    }

    public function test_p05_cambio_de_precio_sin_permiso_devuelve_403_aunque_tenga_producto_crear(): void
    {
        $usuario = $this->usuarioConPermisos('producto.crear');
        $producto = Producto::factory()->create(['precio' => 100]);

        $payload = $this->productoComoPayload($producto);
        $payload['precio'] = 999;

        $response = $this->actingAs($usuario, 'web')->putJson("/api/productos/{$producto->id}", $payload);

        $response->assertStatus(403);
        $this->assertSame('100.00', $producto->fresh()->precio);
    }

    public function test_p11_editar_sin_tocar_precio_no_genera_historial(): void
    {
        $usuario = $this->usuarioConPermisos('producto.crear');
        $producto = Producto::factory()->create(['precio' => 100, 'costo_neto' => 60, 'nombre' => 'Original']);

        $payload = $this->productoComoPayload($producto);
        $payload['nombre'] = 'Nombre editado';

        $response = $this->actingAs($usuario, 'web')->putJson("/api/productos/{$producto->id}", $payload);

        $response->assertOk();
        $this->assertDatabaseCount('historial_precios', 0);
        $this->assertSame('Nombre editado', $producto->fresh()->nombre);
    }

    public function test_p10_baja_logica_nunca_elimina_fisicamente(): void
    {
        $usuario = $this->usuarioConPermisos('producto.crear');
        $producto = Producto::factory()->create(['activo' => true]);

        $response = $this->actingAs($usuario, 'web')->patchJson("/api/productos/{$producto->id}/estado", ['activo' => false]);

        $response->assertOk();
        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'activo' => false]);
        $this->assertDatabaseHas('auditoria', ['tabla' => 'productos', 'registro_id' => $producto->id, 'accion' => 'UPDATE']);
    }

    public function test_alta_sin_permiso_devuelve_403(): void
    {
        $usuario = $this->usuarioConPermisos();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/productos', $this->payloadBase());

        $response->assertStatus(403);
    }

    /**
     * Convierte un Producto existente en el payload completo que espera PUT
     * (edicion es reemplazo total en esta API, no parcial).
     */
    private function productoComoPayload(Producto $producto): array
    {
        return [
            'nombre' => $producto->nombre,
            'sku' => $producto->sku,
            'codigo_barras' => $producto->codigo_barras,
            'precio' => $producto->precio,
            'costo_neto' => $producto->costo_neto,
            'impuesto_id' => $producto->impuesto_id,
            'id_categoria' => $producto->id_categoria,
            'id_marca' => $producto->id_marca,
            'id_unidad_medida' => $producto->id_unidad_medida,
            'controla_stock' => $producto->controla_stock,
        ];
    }
}
