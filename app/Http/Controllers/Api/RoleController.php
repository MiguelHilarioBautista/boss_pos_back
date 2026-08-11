<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * Solo lectura: el picker de rol en "Añadir usuario" necesita mostrar, para
 * cada uno de los 6 roles sembrados, que permisos trae de fija (RBAC es por
 * rol via rol_permiso, no hay permisos por-usuario en el esquema — decisión
 * explicita: no se agregan roles a medida desde la UI en esta entrega).
 */
class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with(['permisos' => fn ($q) => $q->orderBy('modulo')->orderBy('nombre')])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'roles' => $roles->map(fn (Role $rol) => [
                'id' => $rol->id,
                'codigo' => $rol->codigo,
                'nombre' => $rol->nombre,
                'descripcion' => $rol->descripcion,
                'permisos' => $rol->permisos->map(fn ($p) => [
                    'codigo' => $p->codigo,
                    'modulo' => $p->modulo,
                    'nombre' => $p->nombre,
                ])->values(),
            ])->values(),
        ]);
    }
}
