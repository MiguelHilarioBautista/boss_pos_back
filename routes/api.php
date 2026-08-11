<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CajaController;
use App\Http\Controllers\Api\CategoriaProductoController;
use App\Http\Controllers\Api\ImpuestoController;
use App\Http\Controllers\Api\MarcaProductoController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SucursalController;
use App\Http\Controllers\Api\UnidadMedidaController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Middleware\SetAppUsuarioId;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', SetAppUsuarioId::class])->prefix('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::post('pin', [AuthController::class, 'pin']);
    Route::post('cambiar-password', [AuthController::class, 'cambiarPassword']);
});

Route::middleware(['auth:sanctum', SetAppUsuarioId::class, 'permiso:usuario.gestionar'])
    ->group(function () {
        Route::get('roles', [RoleController::class, 'index']);

        Route::prefix('usuarios')->group(function () {
            Route::get('/', [UsuarioController::class, 'index']);
            Route::get('{usuario}', [UsuarioController::class, 'show']);
            Route::post('/', [UsuarioController::class, 'store']);
            Route::post('{usuario}/desactivar', [UsuarioController::class, 'desactivar']);
            Route::post('{usuario}/reactivar', [UsuarioController::class, 'reactivar']);
        });
    });

// M1 — Catalogos: /productos es "autenticado" (cualquier rol logueado
// consulta el catalogo), el resto exige config.modificar o producto.crear.
Route::middleware(['auth:sanctum', SetAppUsuarioId::class])->group(function () {
    Route::prefix('productos')->group(function () {
        Route::get('/', [ProductoController::class, 'index']);
        Route::get('buscar', [ProductoController::class, 'buscar']);
        Route::get('{producto}/historial-precios', [ProductoController::class, 'historialPrecios']);

        Route::middleware('permiso:producto.crear')->post('/', [ProductoController::class, 'store']);
        Route::middleware('permiso:producto.crear')->patch('{producto}/estado', [ProductoController::class, 'estado']);
        Route::middleware('permiso:producto.crear,precio.modificar')->put('{producto}', [ProductoController::class, 'update']);
    });

    // Nombres de parametro fijados a mano: el pluralizador de Laravel es para
    // ingles y adivina mal palabras en espanol (p.ej. "sucursales" ->
    // "sucursale" en vez de "sucursal") si se le deja el default.
    Route::middleware('permiso:config.modificar,producto.crear')->group(function () {
        Route::apiResource('categorias', CategoriaProductoController::class)->only(['index', 'store', 'update'])
            ->parameters(['categorias' => 'categoria']);
        Route::patch('categorias/{categoria}/estado', [CategoriaProductoController::class, 'estado']);

        Route::apiResource('marcas', MarcaProductoController::class)->only(['index', 'store', 'update'])
            ->parameters(['marcas' => 'marca']);
        Route::patch('marcas/{marca}/estado', [MarcaProductoController::class, 'estado']);

        Route::apiResource('unidades', UnidadMedidaController::class)->only(['index', 'store', 'update'])
            ->parameters(['unidades' => 'unidad']);
        Route::patch('unidades/{unidad}/estado', [UnidadMedidaController::class, 'estado']);

        Route::apiResource('impuestos', ImpuestoController::class)->only(['index', 'store'])
            ->parameters(['impuestos' => 'impuesto']);
        Route::patch('impuestos/{impuesto}/estado', [ImpuestoController::class, 'estado']);
    });

    Route::middleware('permiso:config.modificar')->group(function () {
        Route::apiResource('sucursales', SucursalController::class)->only(['index', 'store', 'update'])
            ->parameters(['sucursales' => 'sucursal']);
        Route::patch('sucursales/{sucursal}/estado', [SucursalController::class, 'estado']);

        Route::apiResource('cajas', CajaController::class)->only(['index', 'store', 'update'])
            ->parameters(['cajas' => 'caja']);
        Route::patch('cajas/{caja}/estado', [CajaController::class, 'estado']);
    });
});
