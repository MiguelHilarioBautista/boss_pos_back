<?php

namespace Tests\Feature\Inventario;

use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaUsuarioConPermiso;
use Tests\TestCase;

class ExistenciasAlertasTest extends TestCase
{
    use RefreshDatabase, CreaUsuarioConPermiso;

    public function test_cu03_lista_existencias_con_filtro_de_sucursal(): void
    {
        $usuario = User::factory()->create();
        $producto = Producto::factory()->create();
        $sucursal = Sucursal::factory()->create();
        DB::table('inventario')->insert([
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id,
            'stock' => 7, 'stock_minimo' => 2, 'costo_promedio' => 4,
        ]);

        $response = $this->actingAs($usuario, 'web')->getJson("/api/inventario?sucursal_id={$sucursal->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('existencias'));
        $this->assertEquals(7, (float) $response->json('existencias.0.stock'));
    }

    public function test_cu04_panel_de_alertas_refleja_v_stock_bajo(): void
    {
        $usuario = User::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $bajo = Producto::factory()->create(['activo' => true, 'controla_stock' => true]);
        DB::table('inventario')->insert([
            'producto_id' => $bajo->id, 'sucursal_id' => $sucursal->id,
            'stock' => 1, 'stock_minimo' => 5, 'costo_promedio' => 1,
        ]);

        $normal = Producto::factory()->create(['activo' => true, 'controla_stock' => true]);
        DB::table('inventario')->insert([
            'producto_id' => $normal->id, 'sucursal_id' => $sucursal->id,
            'stock' => 50, 'stock_minimo' => 5, 'costo_promedio' => 1,
        ]);

        $response = $this->actingAs($usuario, 'web')->getJson('/api/inventario/alertas');

        $response->assertOk();
        $alertas = collect($response->json('alertas'));
        $this->assertTrue($alertas->contains('producto_id', $bajo->id));
        $this->assertFalse($alertas->contains('producto_id', $normal->id));
    }

    public function test_fijar_minimos_crea_la_fila_si_no_existia(): void
    {
        $usuario = $this->usuarioConPermisos('inventario.ajustar');
        $producto = Producto::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($usuario, 'web')
            ->putJson("/api/inventario/{$producto->id}/minimos", [
                'sucursal_id' => $sucursal->id, 'stock_minimo' => 10, 'stock_maximo' => 100,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('inventario', [
            'producto_id' => $producto->id, 'sucursal_id' => $sucursal->id, 'stock_minimo' => 10,
        ]);
    }

    public function test_fijar_minimos_sin_permiso_devuelve_403(): void
    {
        $usuario = $this->usuarioConPermisos();
        $producto = Producto::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($usuario, 'web')
            ->putJson("/api/inventario/{$producto->id}/minimos", [
                'sucursal_id' => $sucursal->id, 'stock_minimo' => 10,
            ]);

        $response->assertStatus(403);
    }
}
