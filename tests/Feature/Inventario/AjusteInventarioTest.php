<?php

namespace Tests\Feature\Inventario;

use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaUsuarioConPermiso;
use Tests\TestCase;

class AjusteInventarioTest extends TestCase
{
    use RefreshDatabase, CreaUsuarioConPermiso;

    private function crearInventario(int $productoId, int $sucursalId, float $stock = 10, float $costoPromedio = 5): void
    {
        DB::table('inventario')->insert([
            'producto_id' => $productoId, 'sucursal_id' => $sucursalId,
            'stock' => $stock, 'stock_minimo' => 0, 'costo_promedio' => $costoPromedio,
        ]);
    }

    public function test_p01_ajuste_positivo_con_motivo_y_autorizador(): void
    {
        $ejecutor = $this->usuarioConPermisos('inventario.ajustar');
        $autorizador = $this->usuarioConPermisos('inventario.ajustar');
        $producto = Producto::factory()->create();
        $sucursal = \App\Models\Sucursal::factory()->create();
        $this->crearInventario($producto->id, $sucursal->id, 10, 5);

        $response = $this->actingAs($ejecutor, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_POSITIVO',
            'cantidad' => 5,
            'motivo' => 'Se encontraron piezas extra en bodega',
            'autorizado_por_id' => $autorizador->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('inventario', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id, 'stock' => 15,
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $producto->id, 'tipo' => 'AJUSTE_POSITIVO',
            'stock_anterior' => 10, 'stock_nuevo' => 15, 'usuario_id' => $ejecutor->id,
        ]);
        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'movimientos_inventario', 'usuario_id' => $ejecutor->id,
        ]);
    }

    public function test_p03_ajuste_sin_motivo_devuelve_422(): void
    {
        $ejecutor = $this->usuarioConPermisos('inventario.ajustar');
        $autorizador = $this->usuarioConPermisos('inventario.ajustar');
        $producto = Producto::factory()->create();
        $sucursal = \App\Models\Sucursal::factory()->create();
        $this->crearInventario($producto->id, $sucursal->id);

        $response = $this->actingAs($ejecutor, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_NEGATIVO', 'cantidad' => 1, 'autorizado_por_id' => $autorizador->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_p04_salida_que_dejaria_stock_negativo_devuelve_409_y_no_deja_rastro(): void
    {
        $ejecutor = $this->usuarioConPermisos('inventario.ajustar');
        $autorizador = $this->usuarioConPermisos('inventario.ajustar');
        $producto = Producto::factory()->create();
        $sucursal = \App\Models\Sucursal::factory()->create();
        $this->crearInventario($producto->id, $sucursal->id, 3);

        $response = $this->actingAs($ejecutor, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_NEGATIVO', 'cantidad' => 5, 'motivo' => 'Merma',
            'autorizado_por_id' => $autorizador->id,
        ]);

        $response->assertStatus(409)->assertJson(['error' => 'stock_insuficiente']);
        $this->assertDatabaseHas('inventario', ['producto_id' => $producto->id, 'stock' => 3]);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_p06_descontar_exactamente_el_stock_disponible(): void
    {
        $ejecutor = $this->usuarioConPermisos('inventario.ajustar');
        $autorizador = $this->usuarioConPermisos('inventario.ajustar');
        $producto = Producto::factory()->create();
        $sucursal = \App\Models\Sucursal::factory()->create();
        $this->crearInventario($producto->id, $sucursal->id, 5);

        $response = $this->actingAs($ejecutor, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_NEGATIVO', 'cantidad' => 5, 'motivo' => 'Ajuste final',
            'autorizado_por_id' => $autorizador->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('inventario', ['producto_id' => $producto->id, 'stock' => 0]);
    }

    public function test_sin_autorizado_por_id_devuelve_422(): void
    {
        $ejecutor = $this->usuarioConPermisos('inventario.ajustar');
        $producto = Producto::factory()->create();
        $sucursal = \App\Models\Sucursal::factory()->create();
        $this->crearInventario($producto->id, $sucursal->id);

        $response = $this->actingAs($ejecutor, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_POSITIVO', 'cantidad' => 1, 'motivo' => 'x',
        ]);

        $response->assertStatus(422);
    }

    public function test_autorizador_igual_al_ejecutor_devuelve_422(): void
    {
        $ejecutor = $this->usuarioConPermisos('inventario.ajustar');
        $producto = Producto::factory()->create();
        $sucursal = \App\Models\Sucursal::factory()->create();
        $this->crearInventario($producto->id, $sucursal->id);

        $response = $this->actingAs($ejecutor, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_POSITIVO', 'cantidad' => 1, 'motivo' => 'x',
            'autorizado_por_id' => $ejecutor->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_autorizador_sin_permiso_inventario_ajustar_devuelve_422(): void
    {
        $ejecutor = $this->usuarioConPermisos('inventario.ajustar');
        $autorizadorSinPermiso = $this->usuarioConPermisos();
        $producto = Producto::factory()->create();
        $sucursal = \App\Models\Sucursal::factory()->create();
        $this->crearInventario($producto->id, $sucursal->id);

        $response = $this->actingAs($ejecutor, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_POSITIVO', 'cantidad' => 1, 'motivo' => 'x',
            'autorizado_por_id' => $autorizadorSinPermiso->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_cantidad_fraccionaria_en_unidad_sin_decimales_devuelve_422(): void
    {
        $ejecutor = $this->usuarioConPermisos('inventario.ajustar');
        $autorizador = $this->usuarioConPermisos('inventario.ajustar');
        $unidad = UnidadMedida::factory()->create(['permite_decimales' => false]);
        $producto = Producto::factory()->create(['id_unidad_medida' => $unidad->id]);
        $sucursal = \App\Models\Sucursal::factory()->create();
        $this->crearInventario($producto->id, $sucursal->id);

        $response = $this->actingAs($ejecutor, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_POSITIVO', 'cantidad' => 1.5, 'motivo' => 'x',
            'autorizado_por_id' => $autorizador->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_p10_producto_servicio_no_genera_movimiento(): void
    {
        $ejecutor = $this->usuarioConPermisos('inventario.ajustar');
        $autorizador = $this->usuarioConPermisos('inventario.ajustar');
        $producto = Producto::factory()->create(['controla_stock' => false, 'tipo_prod_serv' => 'SERVICIO']);
        $sucursal = \App\Models\Sucursal::factory()->create();

        $response = $this->actingAs($ejecutor, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_POSITIVO', 'cantidad' => 1, 'motivo' => 'x',
            'autorizado_por_id' => $autorizador->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('inventario', 0);
    }

    public function test_sin_permiso_inventario_ajustar_devuelve_403(): void
    {
        $usuario = $this->usuarioConPermisos();
        $producto = Producto::factory()->create();
        $sucursal = \App\Models\Sucursal::factory()->create();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/inventario/ajustes', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'tipo' => 'AJUSTE_POSITIVO', 'cantidad' => 1, 'motivo' => 'x',
            'autorizado_por_id' => $usuario->id,
        ]);

        $response->assertStatus(403);
    }
}
