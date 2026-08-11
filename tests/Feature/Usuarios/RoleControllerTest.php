<?php

namespace Tests\Feature\Usuarios;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_roles_activos_con_sus_permisos_anidados(): void
    {
        $rolConPermiso = Role::factory()->create(['activo' => true]);
        $permisoGestion = Permiso::create(['codigo' => 'usuario.gestionar', 'modulo' => 'SISTEMA', 'nombre' => 'Administrar usuarios y roles']);
        $permisoVenta = Permiso::create(['codigo' => 'venta.registrar', 'modulo' => 'VENTAS', 'nombre' => 'Vender']);
        $rolConPermiso->permisos()->attach([$permisoGestion->id, $permisoVenta->id]);

        Role::factory()->create(['activo' => false]); // no debe aparecer

        $admin = User::factory()->create(['rol_id' => $rolConPermiso->id]);

        $response = $this->actingAs($admin, 'web')->getJson('/api/roles');

        $response->assertOk();
        $roles = $response->json('roles');

        $this->assertCount(1, $roles);
        $this->assertSame($rolConPermiso->codigo, $roles[0]['codigo']);
        $this->assertCount(2, $roles[0]['permisos']);
        $this->assertContains('usuario.gestionar', array_column($roles[0]['permisos'], 'codigo'));
    }

    public function test_sin_permiso_usuario_gestionar_no_puede_listar_roles(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario, 'web')->getJson('/api/roles');

        $response->assertStatus(403);
    }
}
