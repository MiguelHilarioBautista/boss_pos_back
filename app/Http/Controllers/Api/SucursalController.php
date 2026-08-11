<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SucursalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sucursales = Sucursal::query()
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('nombre')
            ->get();

        return response()->json(['sucursales' => $sucursales]);
    }

    /**
     * RN-M1-06: toda sucursal nueva requiere sus series en folios antes de
     * operar. Se automatiza aqui, en la misma transaccion que el alta
     * (D-M1-3): si algo falla, no queda una sucursal a medias sin folios.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'codigo' => ['required', 'string', 'max:20', 'unique:sucursales,codigo'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'zona_frontera' => ['sometimes', 'boolean'],
            'serie' => ['sometimes', 'string', 'size:3'],
        ]);

        $serie = strtoupper($data['serie'] ?? 'A01');
        unset($data['serie']);

        $sucursal = DB::transaction(function () use ($data, $serie, $request) {
            $sucursal = Sucursal::create($data);

            foreach (['VENTA', 'DEVOL'] as $tipoDoc) {
                DB::table('folios')->insertOrIgnore([
                    'sucursal_id' => $sucursal->id,
                    'serie' => $serie,
                    'tipo_doc' => $tipoDoc,
                    'ultimo_folio' => 0,
                ]);
            }

            Auditoria::create([
                'usuario_id' => $request->user()->id,
                'tabla' => 'sucursales',
                'registro_id' => $sucursal->id,
                'accion' => 'INSERT',
                'valores_nuevos' => ['nombre' => $sucursal->nombre, 'codigo' => $sucursal->codigo],
                'ip_origen' => $request->ip(),
            ]);

            return $sucursal;
        });

        return response()->json(array_merge($sucursal->fresh()->toArray(), ['serie_folios_creada' => $serie]), 201);
    }

    public function update(Request $request, Sucursal $sucursal): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'zona_frontera' => ['sometimes', 'boolean'],
        ]);

        $sucursal->update($data);

        return response()->json($sucursal->fresh());
    }

    /**
     * Baja/reactivacion (D-M1-5: se audita, igual que producto/caja). Igual
     * que el resto de catalogos, activo NUNCA se toca desde update() — solo
     * desde aqui, para dejar un rastro explicito de auditoria del cambio.
     */
    public function estado(Request $request, Sucursal $sucursal): JsonResponse
    {
        $data = $request->validate(['activo' => ['required', 'boolean']]);
        $anterior = $sucursal->activo;

        $sucursal->update($data);

        Auditoria::create([
            'usuario_id' => $request->user()->id,
            'sucursal_id' => $sucursal->id,
            'tabla' => 'sucursales',
            'registro_id' => $sucursal->id,
            'accion' => 'UPDATE',
            'valores_anteriores' => ['activo' => $anterior],
            'valores_nuevos' => ['activo' => $sucursal->activo],
            'ip_origen' => $request->ip(),
        ]);

        return response()->json($sucursal->fresh());
    }
}
