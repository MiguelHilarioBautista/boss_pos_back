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
}
