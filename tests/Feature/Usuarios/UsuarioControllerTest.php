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
 *
 * RN-Configuracion-Anadir-Usuario: sin campo "Usuario" (RN-CRED-01), email
 * como credencial (RN-CRED-02/03), numero_empleado autogenerado (RN-NUM).
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

    private function payloadUsuario(array $overrides = []): array
    {
        return array_merge([
            'password_inicial' => 'Temporal123',
            'nombre' => 'Juan',
            'apellidos' => 'Pérez',
            'email' => 'juan.perez@example.com',
            'telefono' => '8112345678',
        ], $overrides);
    }

    public function test_crea_usuario_con_sucursal_principal_y_registra_auditoria(): void
    {
        $admin = $this->adminConPermiso();
        $rolCajero = Role::factory()->create();
        $sucursal1 = Sucursal::factory()->create();
        $sucursal2 = Sucursal::factory()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', $this->payloadUsuario([
            'rol_id' => $rolCajero->id,
            'sucursales' => [$sucursal1->id, $sucursal2->id],
            'sucursal_principal_id' => $sucursal2->id,
        ]));

        $response->assertCreated();
        $response->assertJsonPath('email', 'juan.perez@example.com');

        $nuevo = User::where('email', 'juan.perez@example.com')->firstOrFail();
        $this->assertTrue($nuevo->debe_cambiar_pass);
        $this->assertTrue($nuevo->activo);
        $this->assertTrue(Hash::check('Temporal123', $nuevo->password_hash));
        $this->assertMatchesRegularExpression('/^27\d{7}$/', $nuevo->numero_empleado);
        $this->assertSame($nuevo->numero_empleado, $nuevo->usuario);

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

    public function test_numero_empleado_es_consecutivo_por_anio(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $this->actingAs($admin, 'web')->postJson('/api/usuarios', $this->payloadUsuario([
            'email' => 'primero@example.com', 'rol_id' => $rol->id,
            'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]))->assertCreated();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', $this->payloadUsuario([
            'email' => 'segundo@example.com', 'rol_id' => $rol->id,
            'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]));

        $primero = User::where('email', 'primero@example.com')->firstOrFail();
        $segundo = User::where('email', 'segundo@example.com')->firstOrFail();

        $anio = now()->year;
        $this->assertSame("27{$anio}001", $primero->numero_empleado);
        $this->assertSame("27{$anio}002", $segundo->numero_empleado);
        $response->assertCreated();
    }

    public function test_sin_permiso_no_puede_crear_usuarios(): void
    {
        $usuario = User::factory()->create();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/usuarios', $this->payloadUsuario([
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]));

        $response->assertStatus(403);
    }

    public function test_email_duplicado_devuelve_422(): void
    {
        $admin = $this->adminConPermiso();
        User::factory()->create(['email' => 'ya.existe@example.com']);
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', $this->payloadUsuario([
            'email' => 'ya.existe@example.com', 'rol_id' => $rol->id,
            'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_password_igual_al_email_es_rechazada(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', $this->payloadUsuario([
            'password_inicial' => 'mismo@example.com', 'email' => 'mismo@example.com',
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_telefono_con_menos_de_10_digitos_devuelve_422(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', $this->payloadUsuario([
            'telefono' => '12345', 'rol_id' => $rol->id,
            'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_telefono_con_letras_devuelve_422(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', $this->payloadUsuario([
            'telefono' => '81123abcde', 'rol_id' => $rol->id,
            'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_sin_telefono_devuelve_422(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $payload = $this->payloadUsuario([
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]);
        unset($payload['telefono']);

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', $payload);

        $response->assertStatus(422);
    }

    public function test_numero_empleado_no_se_puede_capturar_manualmente(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/usuarios', $this->payloadUsuario([
            'numero_empleado' => '999999999', 'rol_id' => $rol->id,
            'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]));

        $response->assertCreated();
        $nuevo = User::where('email', 'juan.perez@example.com')->firstOrFail();
        $this->assertNotSame('999999999', $nuevo->numero_empleado);
    }

    public function test_show_incluye_telefono_numero_empleado_y_rol_id(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $objetivo = User::factory()->create([
            'rol_id' => $rol->id, 'telefono' => '8112345678', 'numero_empleado' => '272026005',
        ]);

        $response = $this->actingAs($admin, 'web')->getJson("/api/usuarios/{$objetivo->id}");

        $response->assertOk();
        $response->assertJsonPath('telefono', '8112345678');
        $response->assertJsonPath('numero_empleado', '272026005');
        $response->assertJsonPath('rol_id', $rol->id);
    }

    public function test_edita_usuario_existente_y_registra_auditoria(): void
    {
        $admin = $this->adminConPermiso();
        $rolNuevo = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $objetivo = User::factory()->create([
            'nombre' => 'Viejo', 'apellidos' => 'Nombre', 'email' => 'viejo@example.com', 'telefono' => '8110000000',
        ]);

        $response = $this->actingAs($admin, 'web')->putJson("/api/usuarios/{$objetivo->id}", [
            'nombre' => 'Nuevo', 'apellidos' => 'Nombre', 'email' => 'nuevo@example.com', 'telefono' => '8119999999',
            'rol_id' => $rolNuevo->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('nombre', 'Nuevo');
        $response->assertJsonPath('apellidos', 'Nombre');
        $response->assertJsonPath('email', 'nuevo@example.com');
        $response->assertJsonPath('telefono', '8119999999');

        $this->assertDatabaseHas('usuarios', [
            'id' => $objetivo->id, 'email' => 'nuevo@example.com', 'telefono' => '8119999999', 'rol_id' => $rolNuevo->id,
        ]);
        $this->assertDatabaseHas('usuario_sucursal', [
            'usuario_id' => $objetivo->id, 'sucursal_id' => $sucursal->id, 'es_principal' => true,
        ]);
        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $admin->id, 'tabla' => 'usuarios', 'registro_id' => $objetivo->id, 'accion' => 'UPDATE',
        ]);
    }

    public function test_editar_no_permite_cambiar_numero_empleado(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $objetivo = User::factory()->create(['numero_empleado' => '272026007']);

        $response = $this->actingAs($admin, 'web')->putJson("/api/usuarios/{$objetivo->id}", [
            'nombre' => 'X', 'apellidos' => 'Y', 'email' => $objetivo->email, 'telefono' => '8112223333',
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
            'numero_empleado' => '000000000',
        ]);

        $response->assertOk();
        $this->assertSame('272026007', $objetivo->fresh()->numero_empleado);
    }

    public function test_editar_email_a_uno_ya_usado_por_otro_devuelve_422(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();
        User::factory()->create(['email' => 'ocupado@example.com']);
        $objetivo = User::factory()->create(['email' => 'libre@example.com']);

        $response = $this->actingAs($admin, 'web')->putJson("/api/usuarios/{$objetivo->id}", [
            'nombre' => 'X', 'apellidos' => 'Y', 'email' => 'ocupado@example.com', 'telefono' => '8112223333',
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_editar_manteniendo_su_propio_email_no_da_422(): void
    {
        $admin = $this->adminConPermiso();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $objetivo = User::factory()->create(['email' => 'propio@example.com']);

        $response = $this->actingAs($admin, 'web')->putJson("/api/usuarios/{$objetivo->id}", [
            'nombre' => 'X', 'apellidos' => 'Y', 'email' => 'propio@example.com', 'telefono' => '8112223333',
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]);

        $response->assertOk();
    }

    public function test_sin_permiso_no_puede_editar_usuarios(): void
    {
        $usuario = User::factory()->create();
        $rol = Role::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $objetivo = User::factory()->create();

        $response = $this->actingAs($usuario, 'web')->putJson("/api/usuarios/{$objetivo->id}", [
            'nombre' => 'X', 'apellidos' => 'Y', 'email' => $objetivo->email, 'telefono' => '8112223333',
            'rol_id' => $rol->id, 'sucursales' => [$sucursal->id], 'sucursal_principal_id' => $sucursal->id,
        ]);

        $response->assertStatus(403);
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
            'email' => 'inactivo1@example.com',
            'password_hash' => Hash::make('Secreto123'),
            'activo' => false,
        ]);

        $response = $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/auth/login', ['email' => 'inactivo1@example.com', 'password' => 'Secreto123']);

        $response->assertStatus(403)->assertJson(['error' => 'cuenta_inactiva']);
    }
}
