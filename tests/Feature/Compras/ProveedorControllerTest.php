<?php

namespace Tests\Feature\Compras;

use App\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaUsuarioConPermiso;
use Tests\TestCase;

class ProveedorControllerTest extends TestCase
{
    use RefreshDatabase, CreaUsuarioConPermiso;

    public function test_crea_proveedor(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');

        $response = $this->actuandoComo($usuario)->postJson('/api/proveedores', [
            'codigo' => 'PROV-001',
            'razon_social' => 'Distribuidora del Norte SA de CV',
            'rfc' => 'DNO010203AB1',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('proveedores', ['codigo' => 'PROV-001', 'activo' => true]);
    }

    public function test_config_modificar_tambien_puede_crear_proveedores(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');

        $this->actuandoComo($usuario)->postJson('/api/proveedores', [
            'codigo' => 'PROV-002', 'razon_social' => 'Otro Proveedor',
        ])->assertCreated();
    }

    public function test_sin_permiso_devuelve_403(): void
    {
        $usuario = $this->usuarioConPermisos();

        $this->actuandoComo($usuario)->postJson('/api/proveedores', [
            'codigo' => 'PROV-003', 'razon_social' => 'Sin Permiso SA',
        ])->assertStatus(403);
    }

    public function test_rfc_con_longitud_invalida_devuelve_422(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');

        $response = $this->actuandoComo($usuario)->postJson('/api/proveedores', [
            'codigo' => 'PROV-004', 'razon_social' => 'RFC Malo SA', 'rfc' => 'CORTO',
        ]);

        $response->assertStatus(422);
    }

    public function test_codigo_duplicado_devuelve_422(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        Proveedor::factory()->create(['codigo' => 'PROV-DUP']);

        $response = $this->actuandoComo($usuario)->postJson('/api/proveedores', [
            'codigo' => 'PROV-DUP', 'razon_social' => 'Duplicado SA',
        ]);

        $response->assertStatus(422);
    }

    public function test_actualiza_proveedor(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create(['razon_social' => 'Nombre Viejo']);

        $response = $this->actuandoComo($usuario)->putJson("/api/proveedores/{$proveedor->id}", [
            'codigo' => $proveedor->codigo,
            'razon_social' => 'Nombre Nuevo',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'razon_social' => 'Nombre Nuevo']);
    }

    public function test_baja_logica_de_proveedor(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        $proveedor = Proveedor::factory()->create(['activo' => true]);

        $response = $this->actuandoComo($usuario)->patchJson("/api/proveedores/{$proveedor->id}/estado", ['activo' => false]);

        $response->assertOk();
        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'activo' => false]);
    }

    public function test_index_filtra_por_activo(): void
    {
        $usuario = $this->usuarioConPermisos('compra.registrar');
        Proveedor::factory()->create(['activo' => true]);
        Proveedor::factory()->create(['activo' => false]);

        $response = $this->actuandoComo($usuario)->getJson('/api/proveedores?activo=0');

        $response->assertOk();
        $this->assertCount(1, $response->json('proveedores'));
    }
}
