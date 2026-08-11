<?php

namespace App\Http\Middleware;

use App\Exceptions\PermisoDenegadoException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC por ruta: ->middleware('permiso:venta.registrar') o, con varios
 * codigos separados por coma, ->middleware('permiso:config.modificar,producto.crear')
 * (pasa si el usuario tiene CUALQUIERA de los codigos listados — necesario
 * para catalogos que M1 autoriza a mas de un permiso).
 * Debe ir despues de auth:sanctum. Nunca deja pasar una violacion de permiso
 * como 500: siempre 403 via PermisoDenegadoException (ver bootstrap/app.php).
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string ...$codigos): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! collect($codigos)->contains(fn (string $codigo) => $usuario->tienePermiso($codigo))) {
            throw PermisoDenegadoException::paraPermiso(...$codigos);
        }

        return $next($request);
    }
}
