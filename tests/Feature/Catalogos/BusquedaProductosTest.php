<?php

namespace Tests\Feature\Catalogos;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU-03 / P-03: SKU exacto -> codigo de barras exacto -> nombre por
 * prefijo (D-M1-1), solo activos, limite 20.
 */
class BusquedaProductosTest extends TestCase
{
    use RefreshDatabase;

    public function test_p03_busca_por_sku_exacto(): void
    {
        $usuario = User::factory()->create();
        Producto::factory()->create(['sku' => 'SKU-EXACTO', 'nombre' => 'Producto X']);
        Producto::factory()->create(['nombre' => 'Otro que empieza igual SKU-EXACTO-2']);

        $response = $this->actingAs($usuario, 'web')->getJson('/api/productos/buscar?q=SKU-EXACTO');

        $response->assertOk();
        $this->assertCount(1, $response->json('resultados'));
        $this->assertSame('SKU-EXACTO', $response->json('resultados.0.sku'));
    }

    public function test_busca_por_codigo_de_barras_exacto_si_no_hay_sku(): void
    {
        $usuario = User::factory()->create();
        Producto::factory()->create(['codigo_barras' => '7501112223334']);

        $response = $this->actingAs($usuario, 'web')->getJson('/api/productos/buscar?q=7501112223334');

        $response->assertOk();
        $this->assertCount(1, $response->json('resultados'));
    }

    public function test_busca_por_nombre_con_prefijo(): void
    {
        $usuario = User::factory()->create();
        Producto::factory()->create(['nombre' => 'Refresco de cola 600ml']);
        Producto::factory()->create(['nombre' => 'Agua natural']);

        $response = $this->actingAs($usuario, 'web')->getJson('/api/productos/buscar?q=Refresco');

        $response->assertOk();
        $this->assertCount(1, $response->json('resultados'));
    }

    public function test_no_devuelve_productos_inactivos(): void
    {
        $usuario = User::factory()->create();
        Producto::factory()->create(['sku' => 'INACTIVO-1', 'activo' => false]);

        $response = $this->actingAs($usuario, 'web')->getJson('/api/productos/buscar?q=INACTIVO-1');

        $response->assertOk();
        $this->assertCount(0, $response->json('resultados'));
    }

    public function test_limita_a_20_resultados_por_nombre(): void
    {
        $usuario = User::factory()->create();
        Producto::factory()->count(25)->create(['nombre' => fn () => 'Coincide '.fake()->unique()->word()]);

        $response = $this->actingAs($usuario, 'web')->getJson('/api/productos/buscar?q=Coincide');

        $response->assertOk();
        $this->assertCount(20, $response->json('resultados'));
    }

    public function test_sin_sesion_devuelve_401(): void
    {
        $response = $this->getJson('/api/productos/buscar?q=algo');

        $response->assertStatus(401);
    }
}
