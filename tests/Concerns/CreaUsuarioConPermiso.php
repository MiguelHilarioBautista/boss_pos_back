<?php

namespace Tests\Concerns;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;

/**
 * Helper compartido para tests de RBAC: crea un rol con exactamente los
 * permisos indicados (creandolos si no existen) y un usuario con ese rol.
 */
trait CreaUsuarioConPermiso
{
    protected function usuarioConPermisos(string ...$codigos): User
    {
        $rol = Role::factory()->create();

        foreach ($codigos as $codigo) {
            $permiso = Permiso::firstOrCreate(
                ['codigo' => $codigo],
                ['modulo' => strtoupper(explode('.', $codigo)[0]), 'nombre' => $codigo]
            );
            $rol->permisos()->syncWithoutDetaching([$permiso->id]);
        }

        return User::factory()->create(['rol_id' => $rol->id]);
    }

    /**
     * Como actingAs(), pero para tests que hacen mas de una peticion HTTP
     * real con usuarios distintos: el guard 'sanctum' (Illuminate\Auth\RequestGuard)
     * cachea el usuario resuelto en la primera peticion y no se refresca solo
     * al cambiar de actingAs() dentro del mismo metodo de test (esto no pasa
     * en produccion, donde cada request real es una app nueva). Hay que
     * olvidar los guards resueltos antes de re-autenticar.
     */
    protected function actuandoComo($usuario, string $guard = 'web'): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($usuario, $guard);
    }
}
