<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnidadMedidaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $unidades = UnidadMedida::query()
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('nombre')
            ->get();

        return response()->json(['unidades' => $unidades]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:40'],
            'codigo' => ['required', 'string', 'max:10', 'unique:unidades_medida,codigo'],
            'permite_decimales' => ['boolean'],
        ]);

        $unidad = UnidadMedida::create($data);

        return response()->json($unidad, 201);
    }

    public function update(Request $request, UnidadMedida $unidad): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:40'],
            'codigo' => ['required', 'string', 'max:10', Rule::unique('unidades_medida', 'codigo')->ignore($unidad)],
            'permite_decimales' => ['boolean'],
        ]);

        $unidad->update($data);

        return response()->json($unidad->fresh());
    }

    public function estado(Request $request, UnidadMedida $unidad): JsonResponse
    {
        $data = $request->validate(['activo' => ['required', 'boolean']]);

        $unidad->update($data);

        return response()->json($unidad);
    }
}
