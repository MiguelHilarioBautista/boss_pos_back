<?php

namespace Tests\Feature\Catalogos;

use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaUsuarioConPermiso;
use Tests\TestCase;

/**
 * R-M1-3 / RN-M1-06: ninguna sucursal ni caja puede quedar operativa sin sus
 * folios. D-M1-3 reforzado: se provisionan tanto al alta de sucursal como al
 * alta de caja (idempotente).
 */
class SucursalCajaControllerTest extends TestCase
{
    use RefreshDatabase, CreaUsuarioConPermiso;

    public function test_actualizar_sucursal_con_logo_lo_guarda_en_logoempresa(): void
    {
        Storage::fake('public');
        $usuario = $this->usuarioConPermisos('config.modificar');
        $sucursal = Sucursal::factory()->create();
        $archivo = UploadedFile::fake()->create('logo.png', 10, 'image/png');

        $response = $this->actingAs($usuario, 'web')->put("/api/sucursales/{$sucursal->id}", [
            'nombre' => $sucursal->nombre,
            'logo' => $archivo,
        ]);

        $response->assertOk();
        $this->assertStringStartsWith('http', $response->json('logo_url'));
        $rutaGuardada = ltrim(str_replace('/storage/', '', parse_url($response->json('logo_url'), PHP_URL_PATH)), '/');
        Storage::disk('public')->assertExists($rutaGuardada);
        $this->assertStringStartsWith('logoempresa/', $rutaGuardada);
    }

    public function test_actualizar_via_multipart_con_zona_frontera_como_texto_false(): void
    {
        // Reproduce el bug real: un form-data (subida de logo) manda
        // zona_frontera como el texto "false", no como boolean JSON, y la
        // regla `boolean` de Laravel rechazaba ese string.
        Storage::fake('public');
        $usuario = $this->usuarioConPermisos('config.modificar');
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($usuario, 'web')->put("/api/sucursales/{$sucursal->id}", [
            'nombre' => $sucursal->nombre,
            'zona_frontera' => 'false',
            'logo' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('logo_url'));
        $this->assertFalse((bool) $response->json('zona_frontera'));
    }

    public function test_reemplazar_logo_borra_el_archivo_anterior(): void
    {
        Storage::fake('public');
        $usuario = $this->usuarioConPermisos('config.modificar');
        $sucursal = Sucursal::factory()->create();

        $primero = $this->actingAs($usuario, 'web')->put("/api/sucursales/{$sucursal->id}", [
            'nombre' => $sucursal->nombre, 'logo' => UploadedFile::fake()->create('viejo.png', 10, 'image/png'),
        ]);
        $rutaVieja = ltrim(str_replace('/storage/', '', parse_url($primero->json('logo_url'), PHP_URL_PATH)), '/');

        $this->actingAs($usuario, 'web')->put("/api/sucursales/{$sucursal->id}", [
            'nombre' => $sucursal->nombre, 'logo' => UploadedFile::fake()->create('nuevo.png', 10, 'image/png'),
        ])->assertOk();

        Storage::disk('public')->assertMissing($rutaVieja);
    }

    public function test_show_devuelve_una_sucursal_con_sus_datos_de_contacto(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');
        $sucursal = Sucursal::factory()->create([
            'email' => 'contacto@sucursal.mx', 'logo_url' => 'https://cdn.example.com/logo.png',
            'numero_exterior' => '123', 'codigo_postal' => '42000', 'pais' => 'MX', 'estado' => 'Hidalgo',
        ]);

        $response = $this->actingAs($usuario, 'web')->getJson("/api/sucursales/{$sucursal->id}");

        $response->assertOk();
        $response->assertJsonPath('email', 'contacto@sucursal.mx');
        $response->assertJsonPath('codigo_postal', '42000');
    }

    public function test_show_sin_permiso_config_modificar_devuelve_403(): void
    {
        $usuario = $this->usuarioConPermisos();
        $sucursal = Sucursal::factory()->create();

        $this->actingAs($usuario, 'web')->getJson("/api/sucursales/{$sucursal->id}")->assertStatus(403);
    }

    public function test_p09_alta_de_sucursal_crea_series_de_folios_venta_y_devol(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');

        $response = $this->actingAs($usuario, 'web')->postJson('/api/sucursales', [
            'nombre' => 'Sucursal Norte', 'codigo' => 'NORTE',
        ]);

        $response->assertCreated();
        $sucursalId = $response->json('id');

        $this->assertDatabaseHas('folios', ['sucursal_id' => $sucursalId, 'serie' => 'A01', 'tipo_doc' => 'VENTA']);
        $this->assertDatabaseHas('folios', ['sucursal_id' => $sucursalId, 'serie' => 'A01', 'tipo_doc' => 'DEVOL']);
    }

    public function test_alta_de_sucursal_admite_serie_personalizada(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');

        $response = $this->actingAs($usuario, 'web')->postJson('/api/sucursales', [
            'nombre' => 'Sucursal Sur', 'codigo' => 'SUR', 'serie' => 'B02',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('folios', ['sucursal_id' => $response->json('id'), 'serie' => 'B02', 'tipo_doc' => 'VENTA']);
    }

    public function test_alta_de_caja_con_serie_nueva_tambien_provisiona_folios(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/cajas', [
            'sucursal_id' => $sucursal->id,
            'codigo' => 'CAJA-02',
            'nombre' => 'Caja 2',
            'serie_folio' => 'C03',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('folios', ['sucursal_id' => $sucursal->id, 'serie' => 'C03', 'tipo_doc' => 'VENTA']);
        $this->assertDatabaseHas('folios', ['sucursal_id' => $sucursal->id, 'serie' => 'C03', 'tipo_doc' => 'DEVOL']);
    }

    public function test_alta_de_caja_no_duplica_folios_si_la_serie_ya_existe(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');
        $sucursal = Sucursal::factory()->create();
        DB::table('folios')->insert([
            ['sucursal_id' => $sucursal->id, 'serie' => 'D04', 'tipo_doc' => 'VENTA', 'ultimo_folio' => 5],
            ['sucursal_id' => $sucursal->id, 'serie' => 'D04', 'tipo_doc' => 'DEVOL', 'ultimo_folio' => 0],
        ]);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/cajas', [
            'sucursal_id' => $sucursal->id, 'codigo' => 'CAJA-03', 'nombre' => 'Caja 3', 'serie_folio' => 'D04',
        ]);

        $response->assertCreated();
        // El folio ya tenia avance (ultimo_folio=5): insertOrIgnore no lo pisa.
        $this->assertDatabaseHas('folios', ['sucursal_id' => $sucursal->id, 'serie' => 'D04', 'tipo_doc' => 'VENTA', 'ultimo_folio' => 5]);
    }

    public function test_gerente_ahora_si_puede_gestionar_sucursales(): void
    {
        $rol = \App\Models\Role::factory()->create(['codigo' => 'GERENTE_TEST']);
        $permiso = \App\Models\Permiso::firstOrCreate(['codigo' => 'config.modificar'], ['modulo' => 'SISTEMA', 'nombre' => 'Modificar configuracion']);
        $rol->permisos()->syncWithoutDetaching([$permiso->id]);
        $gerente = \App\Models\User::factory()->create(['rol_id' => $rol->id]);

        $response = $this->actingAs($gerente, 'web')->postJson('/api/sucursales', [
            'nombre' => 'Sucursal Gerente', 'codigo' => 'GER1',
        ]);

        $response->assertCreated();
    }

    public function test_sin_permiso_config_modificar_no_puede_dar_de_alta_sucursal(): void
    {
        $usuario = $this->usuarioConPermisos('producto.crear');

        $response = $this->actingAs($usuario, 'web')->postJson('/api/sucursales', [
            'nombre' => 'Sucursal X', 'codigo' => 'X1',
        ]);

        $response->assertStatus(403);
    }

    public function test_caja_duplicada_en_la_misma_sucursal_devuelve_422(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');
        $sucursal = Sucursal::factory()->create();
        \App\Models\Caja::factory()->create(['sucursal_id' => $sucursal->id, 'codigo' => 'CAJA-01']);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/cajas', [
            'sucursal_id' => $sucursal->id, 'codigo' => 'CAJA-01', 'nombre' => 'Otra', 'serie_folio' => 'Z99',
        ]);

        $response->assertStatus(422);
    }

    public function test_baja_y_reactivacion_de_sucursal_queda_auditada(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');
        $sucursal = Sucursal::factory()->create(['activo' => true]);

        $baja = $this->actingAs($usuario, 'web')->patchJson("/api/sucursales/{$sucursal->id}/estado", ['activo' => false]);
        $baja->assertOk()->assertJsonPath('activo', false);
        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'sucursales', 'registro_id' => $sucursal->id, 'accion' => 'UPDATE', 'usuario_id' => $usuario->id,
        ]);

        $reactivacion = $this->actingAs($usuario, 'web')->patchJson("/api/sucursales/{$sucursal->id}/estado", ['activo' => true]);
        $reactivacion->assertOk()->assertJsonPath('activo', true);
    }

    public function test_baja_y_reactivacion_de_caja_queda_auditada(): void
    {
        $usuario = $this->usuarioConPermisos('config.modificar');
        $caja = \App\Models\Caja::factory()->create(['activo' => true]);

        $baja = $this->actingAs($usuario, 'web')->patchJson("/api/cajas/{$caja->id}/estado", ['activo' => false]);
        $baja->assertOk()->assertJsonPath('activo', false);
        $this->assertDatabaseHas('auditoria', [
            'tabla' => 'cajas', 'registro_id' => $caja->id, 'accion' => 'UPDATE', 'usuario_id' => $usuario->id,
        ]);

        $reactivacion = $this->actingAs($usuario, 'web')->patchJson("/api/cajas/{$caja->id}/estado", ['activo' => true]);
        $reactivacion->assertOk()->assertJsonPath('activo', true);
    }

    public function test_sin_permiso_no_puede_dar_de_baja_una_sucursal(): void
    {
        $usuario = $this->usuarioConPermisos('producto.crear');
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($usuario, 'web')->patchJson("/api/sucursales/{$sucursal->id}/estado", ['activo' => false]);

        $response->assertStatus(403);
    }
}
