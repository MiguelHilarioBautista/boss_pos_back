<?php

namespace Tests\Feature\Auth;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P-05 / P-12: un usuario sin el permiso de la ruta recibe 403 (nunca 500),
 * con el mismo formato JSON consistente del handler global. Se registra una
 * ruta de prueba porque M0 no define todavia ninguna ruta de negocio real
 * protegida por `permiso:`.
 */
class PermisoMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'permiso:venta.registrar'])
            ->get('/api/_test/solo-con-permiso', fn () => response()->json(['ok' => true]));
    }

    public function test_usuario_sin_permiso_recibe_403_json_consistente(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario, 'web')->getJson('/api/_test/solo-con-permiso');

        $response->assertStatus(403)->assertJsonStructure(['error', 'mensaje']);
    }

    public function test_usuario_con_permiso_pasa(): void
    {
        $rol = Role::factory()->create();
        $permiso = Permiso::create(['codigo' => 'venta.registrar', 'modulo' => 'VENTAS', 'nombre' => 'Registrar ventas']);
        $rol->permisos()->attach($permiso->id);

        $usuario = User::factory()->create(['rol_id' => $rol->id]);

        $response = $this->actingAs($usuario, 'web')->getJson('/api/_test/solo-con-permiso');

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_sin_autenticar_devuelve_401_no_500(): void
    {
        $response = $this->getJson('/api/_test/solo-con-permiso');

        $response->assertStatus(401);
    }
}
