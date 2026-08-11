<?php

namespace Tests\Feature\Catalogos;

use App\Models\CategoriaProducto;
use App\Models\Impuesto;
use App\Models\MarcaProducto;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaUsuarioConPermiso;
use Tests\TestCase;

class CatalogosSimplesControllerTest extends TestCase
{
    use RefreshDatabase, CreaUsuarioConPermiso;

    public function test_crea_categoria_marca_y_unidad(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');

        $this->actingAs($usuario, 'web')->postJson('/api/categorias', ['nombre' => 'Bebidas'])->assertCreated();
        $this->actingAs($usuario, 'web')->postJson('/api/marcas', ['nombre' => 'Coca-Cola'])->assertCreated();
        $this->actingAs($usuario, 'web')->postJson('/api/unidades', ['nombre' => 'Pieza', 'codigo' => 'PZA'])->assertCreated();
    }

    public function test_producto_crear_tambien_puede_gestionar_catalogos_simples(): void
    {
        $usuario = $this->usuarioConPermisos('producto.crear');

        $this->actingAs($usuario, 'web')->postJson('/api/marcas', ['nombre' => 'Marca Almacen'])->assertCreated();
    }

    public function test_categoria_no_puede_ser_su_propio_padre(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');
        $categoria = CategoriaProducto::factory()->create();

        $response = $this->actingAs($usuario, 'web')
            ->putJson("/api/categorias/{$categoria->id}", ['nombre' => $categoria->nombre, 'categoria_padre_id' => $categoria->id]);

        $response->assertStatus(422);
    }

    public function test_baja_logica_de_marca(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');
        $marca = MarcaProducto::factory()->create(['activo' => true]);

        $response = $this->actingAs($usuario, 'web')->patchJson("/api/marcas/{$marca->id}/estado", ['activo' => false]);

        $response->assertOk();
        $this->assertDatabaseHas('marca_producto', ['id' => $marca->id, 'activo' => false]);
    }

    public function test_p06_no_existe_ruta_para_editar_tasa_de_impuesto(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');
        $impuesto = Impuesto::factory()->create(['tasa' => 0.16]);

        $response = $this->actingAs($usuario, 'web')
            ->putJson("/api/impuestos/{$impuesto->id}", ['tasa' => 0.20]);

        $response->assertStatus(404);
        $this->assertSame('0.1600', $impuesto->fresh()->tasa);
    }

    public function test_crear_impuesto_nuevo_en_vez_de_editar_funciona(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');

        $response = $this->actingAs($usuario, 'web')->postJson('/api/impuestos', [
            'codigo' => 'IVA16_V2', 'nombre' => 'IVA 16% (nuevo)', 'tasa' => 0.16,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('auditoria', ['tabla' => 'impuestos', 'accion' => 'INSERT']);
    }

    public function test_p08_tasa_de_impuesto_fuera_de_rango_devuelve_422(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');

        $response = $this->actingAs($usuario, 'web')->postJson('/api/impuestos', [
            'codigo' => 'INVALIDO', 'nombre' => 'Tasa invalida', 'tasa' => 1.0,
        ]);

        $response->assertStatus(422);
    }

    public function test_sin_permiso_no_puede_crear_categoria(): void
    {
        $usuario = $this->usuarioConPermisos();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/categorias', ['nombre' => 'Sin permiso']);

        $response->assertStatus(403);
    }
}
