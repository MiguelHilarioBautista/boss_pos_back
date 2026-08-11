<?php

namespace Tests\Feature\Usuarios;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Extension deliberada de M0 (alta/baja/reactivar de usuarios), fuera del
 * alcance original del PDF pero acordada con el usuario del proyecto.
 * P2: nunca hay destroy() real, "eliminar" = desactivar.
 */
class UsuarioControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminConPermiso(): User
    {
        $rol = Role::factory()->create();
        $permiso = Permiso::create(['codigo' => 'usuario.gestionar', 'modulo' => 'SISTEMA', 'nombre' => 'Administrar usuarios y roles']);
        $rol->permisos()->attach($permiso->id);

        return User::factory()->create(['rol_id' => $rol->id]);
    }

    public function test_crea_usuario_con_sucursal_principal_y_registra_auditoria(): void
    {
        $admin = $this->adminConPermiso();
        $rolCajero = Role::factory()->create();
        $sucursal1 = Sucursal::factory()->create();
        $sucursal2 = Sucursal::factory()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', [
            'usuario' => 'cajero.nuevo',
            'password_inicial' => 'Temporal123',
            'nombre' => 'Juan',
            'apellidos' => 'Pérez',
            'rol_id' => $rolCajero->id,
            'sucursales' => [$sucursal1->id, $sucursal2->id],
            'sucursal_principal_id' => $sucursal2->id,
        ]);

        $response->assertCreated()->assertJsonPath('usuario', 'cajero.nuevo');

        $nuevo = User::where('usuario', 'cajero.nuevo')->firstOrFail();
        $this->assertTrue($nuevo->debe_cambiar_pass);
        $this->assertTrue($nuevo->activo);
        $this->assertTrue(Hash::check('Temporal123', $nuevo->password_hash));

        $this->assertDatabaseHas('usuario_sucursal', [
            'usuario_id' => $nuevo->id, 'sucursal_id' => $sucursal2->id, 'es_principal' => true,
        ]);
        $this->assertDatabaseHas('usuario_sucursal', [
            'usuario_id' => $nuevo->id, 'sucursal_id' => $sucursal1->id, 'es_principal' => false,
        ]);

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $admin->id,
            'tabla' => 'usuarios',
            'registro_id' => $nuevo->id,
            'accion' => 'INSERT',
        ]);
    }

    public function test_sin_permiso_no_puede_crear_usuarios(): void
    {
        $usuario = User::factory()->create();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/usuarios', [
            'usuario' => 'x', 'password_inicial' => 'Temporal123', 'nombre' => 'A', 'apellidos' => 'B',
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_usuario_duplicado_devuelve_422(): void
    {
        $admin = $this->adminConPermiso();
        $existente = User::factory()->create(['usuario' => 'ya.existe']);
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', [
            'usuario' => 'ya.existe', 'password_inicial' => 'Temporal123', 'nombre' => 'A', 'apellidos' => 'B',
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_password_igual_al_usuario_es_rechazada(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', [
            'usuario' => 'mismo123', 'password_inicial' => 'mismo123', 'nombre' => 'A', 'apellidos' => 'B',
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_lista_usuarios(): void
    {
        $admin = $this->adminConPermiso();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'web')->getJson('/api/usuarios');

        $response->assertOk()->assertJsonCount(4, 'usuarios');
    }

    public function test_desactivar_usuario(): void
    {
        $admin = $this->adminConPermiso();
        $objetivo = User::factory()->create(['activo' => true]);

        $response = $this->actingAs($admin, 'web')->postJson("/api/usuarios/{$objetivo->id}/desactivar");

        $response->assertOk()->assertJsonPath('activo', false);
        $this->assertFalse($objetivo->fresh()->activo);

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $admin->id, 'registro_id' => $objetivo->id, 'accion' => 'UPDATE',
        ]);
    }

    public function test_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->adminConPermiso();

        $response = $this->actingAs($admin, 'web')->postJson("/api/usuarios/{$admin->id}/desactivar");

        $response->assertStatus(422);
        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_reactivar_usuario_limpia_bloqueo(): void
    {
        $admin = $this->adminConPermiso();
        $objetivo = User::factory()->bloqueado()->create(['activo' => false, 'intentos_fallidos' => 5]);

        $response = $this->actingAs($admin, 'web')->postJson("/api/usuarios/{$objetivo->id}/reactivar");

        $response->assertOk()->assertJsonPath('activo', true);

        $objetivo->refresh();
        $this->assertTrue($objetivo->activo);
        $this->assertSame(0, $objetivo->intentos_fallidos);
        $this->assertNull($objetivo->bloqueado_hasta);
    }

    public function test_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        config(['sanctum.middleware.validate_csrf_token' => false]);

        User::factory()->create([
            'usuario' => 'inactivo1',
            'password_hash' => Hash::make('Secreto123'),
            'activo' => false,
        ]);

        $response = $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/auth/login', ['usuario' => 'inactivo1', 'password' => 'Secreto123']);

        $response->assertStatus(403)->assertJson(['error' => 'cuenta_inactiva']);
    }
}
