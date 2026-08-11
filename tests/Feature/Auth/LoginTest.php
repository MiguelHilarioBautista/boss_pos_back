<?php

namespace Tests\Feature\Auth;

use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cubre P-01, P-04, P-06, P-07, P-08, P-11 de MO.pdf S9.
 *
 * El login exitoso necesita que arranque la sesion (StartSession), lo cual
 * solo ocurre si Sanctum detecta la request como "frontend" (Referer/Origin
 * dentro de sanctum.stateful). Ademas se desactiva la validacion CSRF para
 * este test: es un detalle de integracion navegador<->Sanctum (requiere el
 * baile GET /sanctum/csrf-cookie) ortogonal a la logica de negocio que aqui
 * se prueba; se ejercita manualmente via la coleccion Postman.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.middleware.validate_csrf_token' => false]);
    }

    private function loginRequest(array $payload)
    {
        return $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/auth/login', $payload);
    }

    public function test_login_con_credenciales_validas_devuelve_contexto_y_cookie_httponly(): void
    {
        $usuario = User::factory()->create([
            'usuario' => 'cajero1',
            'password_hash' => Hash::make('Secreto123'),
            'debe_cambiar_pass' => false,
        ]);

        $response = $this->loginRequest(['usuario' => 'cajero1', 'password' => 'Secreto123']);

        $response->assertOk()->assertJsonStructure([
            'usuario' => ['id', 'usuario', 'nombre', 'rol'],
            'permisos',
            'sucursales',
            'debe_cambiar_pass',
        ]);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie, 'la respuesta debe incluir la cookie de sesion');
        $this->assertTrue($cookie->isHttpOnly());

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $usuario->id,
            'accion' => 'LOGIN',
        ]);

        $usuario->refresh();
        $this->assertSame(0, $usuario->intentos_fallidos);
        $this->assertNotNull($usuario->ultimo_acceso);
    }

    public function test_login_con_password_incorrecto_devuelve_401_generico_e_incrementa_intentos(): void
    {
        $usuario = User::factory()->create([
            'usuario' => 'cajero2',
            'password_hash' => Hash::make('Secreto123'),
        ]);

        $response = $this->loginRequest(['usuario' => 'cajero2', 'password' => 'incorrecta']);

        $response->assertStatus(401)->assertJson(['error' => 'credenciales_invalidas']);

        $usuario->refresh();
        $this->assertSame(1, $usuario->intentos_fallidos);

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $usuario->id,
            'accion' => 'LOGIN_FALLIDO',
        ]);
    }

    public function test_n_intentos_fallidos_bloquean_la_cuenta(): void
    {
        Configuracion::create(['clave' => 'login_intentos_max', 'valor' => '3', 'tipo_dato' => 'INT']);
        Configuracion::create(['clave' => 'login_bloqueo_minutos', 'valor' => '15', 'tipo_dato' => 'INT']);

        $usuario = User::factory()->create([
            'usuario' => 'cajero3',
            'password_hash' => Hash::make('Secreto123'),
        ]);

        foreach (range(1, 3) as $intento) {
            $this->loginRequest(['usuario' => 'cajero3', 'password' => 'incorrecta']);
        }

        $usuario->refresh();
        $this->assertSame(3, $usuario->intentos_fallidos);
        $this->assertNotNull($usuario->bloqueado_hasta);
        $this->assertTrue($usuario->bloqueado_hasta->isFuture());

        // Un intento adicional, ya bloqueada, responde 423 sin revelar mas.
        $response = $this->loginRequest(['usuario' => 'cajero3', 'password' => 'Secreto123']);
        $response->assertStatus(423)->assertJson(['error' => 'cuenta_bloqueada']);
    }

    public function test_login_de_usuario_inexistente_registra_auditoria_con_usuario_id_null(): void
    {
        $response = $this->loginRequest(['usuario' => 'no-existe', 'password' => 'cualquiera']);

        $response->assertStatus(401)->assertJson(['error' => 'credenciales_invalidas']);

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => null,
            'accion' => 'LOGIN_FALLIDO',
        ]);
    }

    public function test_ruta_protegida_sin_sesion_devuelve_401(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401)->assertJson(['error' => 'no_autenticado']);
    }
}
