<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Impuesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RN-M1-05: las tasas de impuesto no se editan — deliberadamente NO hay un
 * metodo update(). Cuando cambia la ley se crea un impuesto nuevo y se
 * reasignan los productos (Reglas de negocio.pdf S3.2).
 */
class ImpuestoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $impuestos = Impuesto::query()
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('codigo')
            ->get();

        return response()->json(['impuestos' => $impuestos]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:20', 'unique:impuestos,codigo'],
            'nombre' => ['required', 'string', 'max:60'],
            'tasa' => ['required', 'numeric', 'min:0', 'lt:1'],
        ]);

        $impuesto = Impuesto::create($data);

        Auditoria::create([
            'usuario_id' => $request->user()->id,
            'tabla' => 'impuestos',
            'registro_id' => $impuesto->id,
            'accion' => 'INSERT',
            'valores_nuevos' => $data,
            'ip_origen' => $request->ip(),
        ]);

        return response()->json($impuesto, 201);
    }

    public function estado(Request $request, Impuesto $impuesto): JsonResponse
    {
        $data = $request->validate(['activo' => ['required', 'boolean']]);

        $impuesto->update($data);

        return response()->json($impuesto);
    }
}
