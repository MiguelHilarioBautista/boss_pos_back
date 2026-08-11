<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la variable de sesion MySQL @app_usuario_id al inicio de cada request
 * autenticado, para que los triggers de auditoria/historial (definidos en la
 * migracion del esquema mila_pos) atribuyan los cambios al usuario correcto
 * sin que cada controlador tenga que acordarse de hacerlo (RN-M0-08).
 */
class SetAppUsuarioId
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($usuario = $request->user()) {
            DB::statement('SET @app_usuario_id = ?', [$usuario->getKey()]);
        }

        return $next($request);
    }
}
