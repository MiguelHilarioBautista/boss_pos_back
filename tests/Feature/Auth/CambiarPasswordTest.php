<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * CU-03 / P-03: cambio de contraseña obligatorio en primer acceso.
 */
class CambiarPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambia_la_contrasena_y_limpia_debe_cambiar_pass(): void
    {
        $usuario = User::factory()->debeCambiarPassword()->create([
            'password_hash' => Hash::make('Actual123'),
        ]);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/auth/cambiar-password', [
            'password_actual' => 'Actual123',
            'password_nuevo' => 'Nueva12345',
            'password_confirmacion' => 'Nueva12345',
        ]);

        $response->assertOk()->assertJson(['cambiada' => true]);

        $usuario->refresh();
        $this->assertFalse($usuario->debe_cambiar_pass);
        $this->assertNotNull($usuario->password_actualizado);
        $this->assertTrue(Hash::check('Nueva12345', $usuario->password_hash));
    }

    public function test_password_actual_incorrecta_devuelve_401(): void
    {
        $usuario = User::factory()->create(['password_hash' => Hash::make('Actual123')]);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/auth/cambiar-password', [
            'password_actual' => 'incorrecta',
            'password_nuevo' => 'Nueva12345',
            'password_confirmacion' => 'Nueva12345',
        ]);

        $response->assertStatus(401)->assertJson(['error' => 'password_incorrecto']);
    }

    public function test_confirmacion_que_no_coincide_devuelve_422(): void
    {
        $usuario = User::factory()->create(['password_hash' => Hash::make('Actual123')]);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/auth/cambiar-password', [
            'password_actual' => 'Actual123',
            'password_nuevo' => 'Nueva12345',
            'password_confirmacion' => 'Otra12345',
        ]);

        $response->assertStatus(422);
    }

    public function test_password_que_no_cumple_politica_minima_devuelve_422(): void
    {
        $usuario = User::factory()->create(['password_hash' => Hash::make('Actual123')]);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/auth/cambiar-password', [
            'password_actual' => 'Actual123',
            'password_nuevo' => 'sololetras',
            'password_confirmacion' => 'sololetras',
        ]);

        $response->assertStatus(422);
    }

    public function test_password_igual_al_email_devuelve_422(): void
    {
        // El email debe cumplir tambien la politica de formato (letra+digito)
        // para que el 422 se deba especificamente a la igualdad con el
        // email, no a que el valor de prueba no pase el regex.
        $usuario = User::factory()->create([
            'email' => 'igual1@example.com', 'password_hash' => Hash::make('Actual123'),
        ]);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/auth/cambiar-password', [
            'password_actual' => 'Actual123',
            'password_nuevo' => 'igual1@example.com',
            'password_confirmacion' => 'igual1@example.com',
        ]);

        $response->assertStatus(422);
    }
}
