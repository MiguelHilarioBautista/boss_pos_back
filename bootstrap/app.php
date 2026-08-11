<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Exceptions\PermisoDenegadoException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permiso' => \App\Http\Middleware\VerificarPermiso::class,
        ]);

        $middleware->prependToGroup('api', \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\LogApiRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'no_autenticado',
                    'mensaje' => 'Debes iniciar sesion para continuar.',
                ], 401);
            }
        });

        $exceptions->render(function (PermisoDenegadoException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'permiso_denegado',
                    'mensaje' => $e->getMessage(),
                ], 403);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'validacion',
                    'mensaje' => 'Los datos enviados no son validos.',
                    'detalles' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if (($e->errorInfo[1] ?? null) === 1644) {
                // SIGNAL SQLSTATE '45000' lanzado por un trigger o procedimiento:
                // es una regla de negocio, no un error de servidor (nunca 500).
                return response()->json([
                    'error' => 'regla_negocio',
                    'mensaje' => $e->errorInfo[2] ?? 'La operacion viola una regla de negocio.',
                ], 422);
            }

            if (($e->errorInfo[1] ?? null) === 1062) {
                // ER_DUP_ENTRY: defensa en profundidad ante condiciones de
                // carrera (R-M1-2) — la validacion proactiva de Form Request
                // ya cubre el caso normal, esto blinda el concurrente.
                return response()->json([
                    'error' => 'duplicado',
                    'mensaje' => 'Ya existe un registro con ese valor unico.',
                ], 422);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') && ! app()->hasDebugModeEnabled()) {
                return response()->json([
                    'error' => 'error_servidor',
                    'mensaje' => 'Ocurrio un error inesperado. Intenta de nuevo.',
                ], 500);
            }
        });
    })->create();
