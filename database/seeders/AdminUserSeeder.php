<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\GeneradorNumeroEmpleado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Usuario admin inicial. El .sql deja un hash placeholder invalido a proposito
 * (ver database/boss_pos_schema.sql bloque 15); aqui se sustituye por un hash
 * bcrypt real. debe_cambiar_pass=true fuerza el cambio en el primer login, asi
 * que la contraseña impresa aqui no queda vigente mas alla de esa sesion.
 *
 * RN-CRED-02: el login es por email, ya no por `usuario` — la identidad de
 * este seeder (updateOrCreate) se busca por email en vez de por 'admin'.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $rolAdmin = Role::where('codigo', 'ADMIN')->firstOrFail();
        $passwordInicial = env('ADMIN_INITIAL_PASSWORD', 'Cambiar#2026');
        $emailInicial = env('ADMIN_INITIAL_EMAIL', 'admin@mila-pos.local');

        $admin = DB::transaction(function () use ($rolAdmin, $passwordInicial, $emailInicial) {
            $existente = User::where('email', $emailInicial)->first();
            $numeroEmpleado = $existente?->numero_empleado ?? GeneradorNumeroEmpleado::generar();

            return User::updateOrCreate(
                ['email' => $emailInicial],
                [
                    'usuario' => $numeroEmpleado,
                    'numero_empleado' => $numeroEmpleado,
                    'telefono' => $existente?->telefono ?? '0000000000',
                    'rol_id' => $rolAdmin->id,
                    'password_hash' => Hash::make($passwordInicial),
                    'nombre' => 'Administrador',
                    'apellidos' => 'Sistema',
                    'activo' => true,
                    'debe_cambiar_pass' => true,
                ]
            );
        });

        $sucursal = Sucursal::where('codigo', 'MTZ')->first();

        if ($sucursal) {
            $admin->sucursales()->syncWithoutDetaching([
                $sucursal->id => ['es_principal' => true],
            ]);
        }

        $this->command?->warn("Usuario admin inicial: '{$emailInicial}' / '{$passwordInicial}' (debe cambiarla en el primer login).");
    }
}
