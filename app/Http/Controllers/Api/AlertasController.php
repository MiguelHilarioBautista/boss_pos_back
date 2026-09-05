<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CU-04: panel de alertas de stock bajo. Lee v_stock_bajo (vista creada en
 * la migracion de M0) directo por query builder — es una vista, no una
 * tabla con modelo Eloquent propio.
 */
class AlertasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $alertas = DB::table('v_stock_bajo')
            ->when($request->filled('sucursal_id'), fn ($q) => $q->where('sucursal_id', $request->integer('sucursal_id')))
            ->orderByDesc('faltante')
            ->get();

        return response()->json(['alertas' => $alertas]);
    }
}
