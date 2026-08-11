<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarcaProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarcaProductoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $marcas = MarcaProducto::query()
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('nombre')
            ->get();

        return response()->json(['marcas' => $marcas]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80', 'unique:marca_producto,nombre'],
        ]);

        $marca = MarcaProducto::create($data);

        return response()->json($marca, 201);
    }

    public function update(Request $request, MarcaProducto $marca): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('marca_producto', 'nombre')->ignore($marca)],
        ]);

        $marca->update($data);

        return response()->json($marca->fresh());
    }

    public function estado(Request $request, MarcaProducto $marca): JsonResponse
    {
        $data = $request->validate(['activo' => ['required', 'boolean']]);

        $marca->update($data);

        return response()->json($marca);
    }
}
