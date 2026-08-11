<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PermisoDenegadoException;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Producto;
use App\Support\ProductoPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    private const RELACIONES = ['categoria', 'marca', 'unidadMedida', 'impuesto'];

    public function index(Request $request): JsonResponse
    {
        $productos = Producto::query()
            ->with(self::RELACIONES)
            ->when($request->filled('categoria_id'), fn ($q) => $q->where('id_categoria', $request->integer('categoria_id')))
            ->when($request->filled('marca_id'), fn ($q) => $q->where('id_marca', $request->integer('marca_id')))
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->when($request->filled('q'), fn ($q) => $q->where('nombre', 'like', $request->string('q').'%'))
            ->orderBy('nombre')
            ->paginate($request->integer('por_pagina', 20));

        return response()->json([
            'productos' => $productos->getCollection()->map(fn (Producto $p) => ProductoPresenter::detalle($p))->values(),
            'total' => $productos->total(),
            'pagina' => $productos->currentPage(),
            'por_pagina' => $productos->perPage(),
        ]);
    }

    /**
     * GET /productos/buscar?q= — CU-03 / diagrama de actividades S3.2:
     * SKU exacto -> codigo de barras exacto -> nombre por prefijo (D-M1-1),
     * solo activos, limite 20.
     */
    public function buscar(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:1']]);
        $q = $data['q'];

        $porSku = Producto::with('unidadMedida')->where('activo', true)->where('sku', $q)->first();
        $coincidencia = $porSku ? collect([$porSku]) : null;

        if (! $coincidencia) {
            $porCodigo = Producto::with('unidadMedida')->where('activo', true)->where('codigo_barras', $q)->first();
            $coincidencia = $porCodigo ? collect([$porCodigo]) : null;
        }

        $resultados = $coincidencia ?? Producto::with('unidadMedida')
            ->where('activo', true)
            ->where('nombre', 'like', $q.'%')
            ->limit(20)
            ->get();

        return response()->json([
            'resultados' => $resultados->map(fn (Producto $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'nombre' => $p->nombre,
                'precio' => $p->precio,
                'impuesto_id' => $p->impuesto_id,
                'unidad' => $p->unidadMedida?->codigo,
            ])->values(),
            'total' => $resultados->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validarProducto($request);

        $producto = Producto::create($data);

        return response()->json(ProductoPresenter::detalle($producto->fresh(self::RELACIONES)), 201);
    }

    /**
     * PUT /productos/{id} — CU-02 / diagrama de flujo S3.1: cambiar precio o
     * costo exige ademas el permiso precio.modificar (el de la ruta,
     * producto.crear O precio.modificar, solo habilita intentar editar).
     * SetAppUsuarioId (middleware global del grupo) ya fijo @app_usuario_id
     * antes de llegar aqui, asi que el trigger de historial atribuye bien.
     */
    public function update(Request $request, Producto $producto): JsonResponse
    {
        $data = $this->validarProducto($request, $producto);

        $cambiaPrecioOCosto = bccomp((string) $data['precio'], (string) $producto->precio, 2) !== 0
            || bccomp((string) ($data['costo_neto'] ?? 0), (string) $producto->costo_neto, 2) !== 0;

        if ($cambiaPrecioOCosto && ! $request->user()->tienePermiso('precio.modificar')) {
            throw PermisoDenegadoException::paraPermiso('precio.modificar');
        }

        $producto->update($data);

        return response()->json(ProductoPresenter::detalle($producto->fresh(self::RELACIONES)));
    }

    /**
     * PATCH /productos/{id}/estado — CU-04: baja logica / reactivacion,
     * nunca DELETE fisico (RN-M1-02).
     */
    public function estado(Request $request, Producto $producto): JsonResponse
    {
        $data = $request->validate(['activo' => ['required', 'boolean']]);
        $anterior = $producto->activo;

        $producto->update($data);

        Auditoria::create([
            'usuario_id' => $request->user()->id,
            'tabla' => 'productos',
            'registro_id' => $producto->id,
            'accion' => 'UPDATE',
            'valores_anteriores' => ['activo' => $anterior],
            'valores_nuevos' => ['activo' => $producto->activo],
            'ip_origen' => $request->ip(),
        ]);

        return response()->json(ProductoPresenter::detalle($producto->fresh(self::RELACIONES)));
    }

    public function historialPrecios(Request $request, Producto $producto): JsonResponse
    {
        $historial = $producto->historialPrecios()
            ->with('usuario:id,usuario,nombre,apellidos')
            ->paginate($request->integer('por_pagina', 20));

        return response()->json([
            'historial' => $historial->getCollection()->map(fn ($h) => [
                'id' => $h->id,
                'precio_anterior' => $h->precio_anterior,
                'precio_nuevo' => $h->precio_nuevo,
                'costo_anterior' => $h->costo_anterior,
                'costo_nuevo' => $h->costo_nuevo,
                'usuario' => $h->usuario?->usuario,
                'fecha' => $h->fecha,
            ])->values(),
            'total' => $historial->total(),
        ]);
    }

    private function validarProducto(Request $request, ?Producto $producto = null): array
    {
        return $request->validate([
            'tipo_prod_serv' => ['sometimes', 'in:PRODUCTO,SERVICIO'],
            'nombre' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('productos', 'sku')->ignore($producto)],
            'codigo_barras' => ['nullable', 'string', 'max:50', Rule::unique('productos', 'codigo_barras')->ignore($producto)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0'],
            'costo_neto' => ['nullable', 'numeric', 'min:0'],
            'impuesto_id' => ['required', 'integer', 'exists:impuestos,id'],
            'id_categoria' => ['required', 'integer', 'exists:categoria_productos,id'],
            'id_marca' => ['nullable', 'integer', 'exists:marca_producto,id'],
            'id_unidad_medida' => ['required', 'integer', 'exists:unidades_medida,id'],
            'controla_stock' => ['sometimes', 'boolean'],
            'imagen_url' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
