<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU-02 / RN-M0-03: el PIN solo revalida identidad, nunca otorga permisos.
 * Las rutas protegidas se ejercitan con actingAs('web'): el guard `sanctum`
 * delega en el guard `web` (ver Laravel\Sanctum\Guard::__invoke), asi que
 * fijar el usuario autenticado en `web` basta sin simular cookies reales.
 */
class PinTest extends TestCase
{
    use RefreshDatabase;

    public function test_revalidar_con_pin_correcto(): void
    {
        $usuario = User::factory()->conPin('1234')->create();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/auth/pin', ['pin' => '1234']);

        $response->assertOk()->assertJson(['revalidado' => true]);
    }

    public function test_pin_incorrecto_devuelve_401(): void
    {
        $usuario = User::factory()->conPin('1234')->create();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/auth/pin', ['pin' => '9999']);

        $response->assertStatus(401)->assertJson(['error' => 'pin_incorrecto']);
    }

    public function test_usuario_sin_pin_configurado_devuelve_422(): void
    {
        $usuario = User::factory()->create(['pin_hash' => null]);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/auth/pin', ['pin' => '1234']);

        $response->assertStatus(422)->assertJson(['error' => 'pin_no_configurado']);
    }

    public function test_pin_no_numerico_devuelve_422_de_validacion(): void
    {
        $usuario = User::factory()->conPin('1234')->create();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/auth/pin', ['pin' => 'abcd']);

        $response->assertStatus(422)->assertJson(['error' => 'validacion']);
    }
}
