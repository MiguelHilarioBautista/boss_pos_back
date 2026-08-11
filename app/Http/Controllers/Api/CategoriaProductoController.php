<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaProductoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categorias = CategoriaProducto::query()
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('nombre')
            ->get();

        return response()->json(['categorias' => $categorias]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80', 'unique:categoria_productos,nombre'],
            'categoria_padre_id' => ['nullable', 'integer', 'exists:categoria_productos,id'],
        ]);

        $categoria = CategoriaProducto::create($data);

        return response()->json($categoria, 201);
    }

    public function update(Request $request, CategoriaProducto $categoria): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('categoria_productos', 'nombre')->ignore($categoria)],
            'categoria_padre_id' => [
                'nullable', 'integer', 'exists:categoria_productos,id',
                function ($attribute, $value, $fail) use ($categoria) {
                    if ($value == $categoria->id) {
                        $fail('Una categoría no puede ser su propio padre.');
                    }
                },
            ],
        ]);

        $categoria->update($data);

        return response()->json($categoria->fresh());
    }

    public function estado(Request $request, CategoriaProducto $categoria): JsonResponse
    {
        $data = $request->validate(['activo' => ['required', 'boolean']]);

        $categoria->update($data);

        return response()->json($categoria);
    }
}
