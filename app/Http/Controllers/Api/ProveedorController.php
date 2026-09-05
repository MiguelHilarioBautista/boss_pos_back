<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $proveedores = Proveedor::query()
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('razon_social')
            ->get();

        return response()->json(['proveedores' => $proveedores]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validarProveedor($request);

        $proveedor = Proveedor::create($data);

        return response()->json($proveedor, 201);
    }

    public function update(Request $request, Proveedor $proveedor): JsonResponse
    {
        $data = $this->validarProveedor($request, $proveedor);

        $proveedor->update($data);

        return response()->json($proveedor->fresh());
    }

    public function estado(Request $request, Proveedor $proveedor): JsonResponse
    {
        $data = $request->validate(['activo' => ['required', 'boolean']]);

        $proveedor->update($data);

        return response()->json($proveedor);
    }

    private function validarProveedor(Request $request, ?Proveedor $proveedor = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:20', Rule::unique('proveedores', 'codigo')->ignore($proveedor)],
            'razon_social' => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:150'],
            'rfc' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (! in_array(strlen($value), [12, 13], true)) {
                    $fail('El RFC debe tener 12 o 13 caracteres.');
                }
            }],
            'contacto' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'dias_credito' => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
