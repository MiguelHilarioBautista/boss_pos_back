<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CU-03 (existencias) + complemento necesario para que CU-04 (alertas)
 * tenga sentido: sin un lugar para fijar stock_minimo, v_stock_bajo nunca
 * dispara nada (default 0). Ver decision en el plan de M2 ("gap real
 * encontrado").
 */
class InventarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $existencias = Inventario::query()
            ->with(['producto:id,nombre,sku,codigo_barras', 'sucursal:id,nombre,codigo'])
            ->when($request->filled('sucursal_id'), fn ($q) => $q->where('sucursal_id', $request->integer('sucursal_id')))
            ->when($request->filled('producto_id'), fn ($q) => $q->where('producto_id', $request->integer('producto_id')))
            ->when($request->boolean('bajo_minimo'), fn ($q) => $q->whereColumn('stock', '<=', 'stock_minimo'))
            ->paginate($request->integer('por_pagina', 20));

        return response()->json([
            'existencias' => $existencias->getCollection()->map(fn (Inventario $i) => [
                'producto_id' => $i->producto_id,
                'producto' => $i->producto?->nombre,
                'sku' => $i->producto?->sku,
                'sucursal_id' => $i->sucursal_id,
                'sucursal' => $i->sucursal?->nombre,
                'stock' => $i->stock,
                'stock_minimo' => $i->stock_minimo,
                'stock_maximo' => $i->stock_maximo,
                'costo_promedio' => $i->costo_promedio,
                'ubicacion' => $i->ubicacion,
            ])->values(),
            'total' => $existencias->total(),
        ]);
    }

    /**
     * Fija stock_minimo/maximo/ubicacion. No es un movimiento (no toca el
     * kardex ni el stock) — crea la fila de inventario si aun no existe
     * (p.ej. configurar el minimo antes de la primera compra).
     */
    public function minimos(Request $request, Producto $producto): JsonResponse
    {
        $data = $request->validate([
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'stock_maximo' => ['nullable', 'numeric', 'gte:stock_minimo'],
            'ubicacion' => ['nullable', 'string', 'max:50'],
        ]);

        DB::table('inventario')->updateOrInsert(
            ['producto_id' => $producto->id, 'sucursal_id' => $data['sucursal_id']],
            [
                'stock_minimo' => $data['stock_minimo'],
                'stock_maximo' => $data['stock_maximo'] ?? null,
                'ubicacion' => $data['ubicacion'] ?? null,
            ]
        );

        $fila = DB::table('inventario')
            ->where('producto_id', $producto->id)
            ->where('sucursal_id', $data['sucursal_id'])
            ->first();

        return response()->json($fila);
    }
}
