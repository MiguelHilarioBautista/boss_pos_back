<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU-02: consulta de solo lectura del kardex. Ningun metodo de escritura
 * aqui — el kardex es append-only y su unico escritor es InventarioService.
 */
class KardexController extends Controller
{
    private const TIPOS = [
        'ENTRADA_COMPRA', 'SALIDA_VENTA', 'DEVOLUCION_CLIENTE', 'DEVOLUCION_PROVEEDOR',
        'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'TRASPASO_ENTRADA', 'TRASPASO_SALIDA',
        'MERMA', 'CONTEO_FISICO',
    ];

    public function index(Request $request, Producto $producto): JsonResponse
    {
        $filtros = $request->validate([
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'tipo' => ['nullable', Rule::in(self::TIPOS)],
        ]);

        $movimientos = MovimientoInventario::query()
            ->where('producto_id', $producto->id)
            ->when($filtros['sucursal_id'] ?? null, fn ($q, $v) => $q->where('sucursal_id', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->with('usuario:id,usuario')
            ->orderByDesc('fecha')
            ->paginate($request->integer('por_pagina', 20));

        return response()->json([
            'movimientos' => $movimientos->getCollection()->map(fn (MovimientoInventario $m) => [
                'id' => $m->id,
                'sucursal_id' => $m->sucursal_id,
                'tipo' => $m->tipo,
                'cantidad' => $m->cantidad,
                'costo_unitario' => $m->costo_unitario,
                'stock_anterior' => $m->stock_anterior,
                'stock_nuevo' => $m->stock_nuevo,
                'costo_prom_anterior' => $m->costo_prom_anterior,
                'costo_prom_nuevo' => $m->costo_prom_nuevo,
                'referencia_tabla' => $m->referencia_tabla,
                'referencia_id' => $m->referencia_id,
                'usuario' => $m->usuario?->usuario,
                'motivo' => $m->motivo,
                'fecha' => $m->fecha,
            ])->values(),
            'total' => $movimientos->total(),
            'pagina' => $movimientos->currentPage(),
        ]);
    }
}
