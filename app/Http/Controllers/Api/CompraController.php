<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EstadoInvalidoException;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompraController extends Controller
{
    private const RELACIONES = ['proveedor:id,razon_social', 'sucursal:id,nombre', 'detalle.producto:id,nombre,controla_stock'];

    public function __construct(private InventarioService $inventarioService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $compras = Compra::query()
            ->with(['proveedor:id,razon_social', 'sucursal:id,nombre'])
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->when($request->filled('estatus'), fn ($q) => $q->where('estatus', $request->string('estatus')))
            ->when($request->filled('sucursal_id'), fn ($q) => $q->where('sucursal_id', $request->integer('sucursal_id')))
            ->orderByDesc('fecha_documento')
            ->paginate($request->integer('por_pagina', 20));

        return response()->json([
            'compras' => $compras->getCollection()->map(fn (Compra $compra) => $this->resumen($compra))->values(),
            'total' => $compras->total(),
        ]);
    }

    public function show(Compra $compra): JsonResponse
    {
        $compra->load(self::RELACIONES);

        return response()->json($this->detalle($compra));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validarCompra($request);

        $compra = DB::transaction(fn () => $this->guardarCompra(new Compra(), $data, $request->user()->id));

        return response()->json($this->detalle($compra->load(self::RELACIONES)), 201);
    }

    public function update(Request $request, Compra $compra): JsonResponse
    {
        if ($compra->estatus !== 'BORRADOR') {
            throw EstadoInvalidoException::paraTransicion('la compra', $compra->estatus, 'editar');
        }

        $data = $this->validarCompra($request, $compra);

        $compra = DB::transaction(function () use ($compra, $data) {
            $compra->detalle()->delete();

            return $this->guardarCompra($compra, $data, $compra->usuario_id);
        });

        return response()->json($this->detalle($compra->load(self::RELACIONES)));
    }

    public function recibir(Request $request, Compra $compra): JsonResponse
    {
        if (! in_array($compra->estatus, ['BORRADOR', 'PARCIAL'], true)) {
            throw EstadoInvalidoException::paraTransicion('la compra', $compra->estatus, 'recibir');
        }

        $data = $request->validate([
            'recepciones' => ['required', 'array', 'min:1'],
            'recepciones.*.detalle_id' => ['required', 'integer'],
            'recepciones.*.cantidad_recibida' => ['required', 'numeric', 'gt:0'],
        ]);

        $usuario = $request->user();

        DB::transaction(function () use ($data, $compra, $usuario) {
            foreach ($data['recepciones'] as $recepcion) {
                $detalle = DetalleCompra::where('id', $recepcion['detalle_id'])
                    ->where('compra_id', $compra->id)
                    ->lockForUpdate()
                    ->first();

                if (! $detalle) {
                    throw ValidationException::withMessages([
                        'recepciones' => "El renglón {$recepcion['detalle_id']} no pertenece a esta compra.",
                    ]);
                }

                $pendiente = bcsub((string) $detalle->cantidad, (string) $detalle->cantidad_recibida, 3);
                $cantidadEvento = (string) $recepcion['cantidad_recibida'];

                if (bccomp($cantidadEvento, $pendiente, 3) > 0) {
                    throw ValidationException::withMessages([
                        'recepciones' => "La cantidad recibida para el renglón {$detalle->id} excede lo pendiente ({$pendiente}).",
                    ]);
                }

                $producto = Producto::find($detalle->producto_id);

                if ($producto && $producto->controla_stock) {
                    $this->inventarioService->aplicarMovimiento([
                        'tipo' => 'ENTRADA_COMPRA',
                        'producto_id' => $detalle->producto_id,
                        'sucursal_id' => $compra->sucursal_id,
                        'cantidad' => (float) $cantidadEvento,
                        'costo_unitario' => (float) $detalle->costo_unitario,
                        'usuario_id' => $usuario->id,
                        'referencia_tabla' => 'compras',
                        'referencia_id' => $compra->id,
                    ]);
                }

                $detalle->update([
                    'cantidad_recibida' => bcadd((string) $detalle->cantidad_recibida, $cantidadEvento, 3),
                ]);
            }

            $estatusAnterior = $compra->estatus;
            $todoRecibido = $compra->detalle()->whereColumn('cantidad_recibida', '<', 'cantidad')->doesntExist();

            $compra->update([
                'estatus' => $todoRecibido ? 'RECIBIDA' : 'PARCIAL',
                'fecha_recepcion' => $todoRecibido ? now() : $compra->fecha_recepcion,
            ]);

            Auditoria::create([
                'usuario_id' => $usuario->id,
                'sucursal_id' => $compra->sucursal_id,
                'tabla' => 'compras',
                'registro_id' => $compra->id,
                'accion' => 'UPDATE',
                'valores_anteriores' => ['estatus' => $estatusAnterior],
                'valores_nuevos' => ['estatus' => $compra->estatus],
                'ip_origen' => request()->ip(),
            ]);
        });

        return response()->json($this->detalle($compra->fresh(self::RELACIONES)));
    }

    public function cancelar(Request $request, Compra $compra): JsonResponse
    {
        if (in_array($compra->estatus, ['RECIBIDA', 'CANCELADA'], true)) {
            throw EstadoInvalidoException::paraTransicion('la compra', $compra->estatus, 'cancelar');
        }

        $data = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        $estatusAnterior = $compra->estatus;

        $compra->update([
            'estatus' => 'CANCELADA',
            'cancelada_por' => $request->user()->id,
            'cancelada_fecha' => now(),
            'cancelada_motivo' => $data['motivo'],
        ]);

        Auditoria::create([
            'usuario_id' => $request->user()->id,
            'sucursal_id' => $compra->sucursal_id,
            'tabla' => 'compras',
            'registro_id' => $compra->id,
            'accion' => 'UPDATE',
            'valores_anteriores' => ['estatus' => $estatusAnterior],
            'valores_nuevos' => ['estatus' => 'CANCELADA', 'motivo' => $data['motivo']],
            'ip_origen' => $request->ip(),
        ]);

        return response()->json($this->detalle($compra->fresh(self::RELACIONES)));
    }

    private function validarCompra(Request $request, ?Compra $compra = null): array
    {
        $data = $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'folio_documento' => [
                'required', 'string', 'max:40',
                Rule::unique('compras', 'folio_documento')
                    ->where('proveedor_id', $request->input('proveedor_id'))
                    ->ignore($compra),
            ],
            'fecha_documento' => ['required', 'date'],
            'detalle' => ['required', 'array', 'min:1'],
            'detalle.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalle.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalle.*.costo_unitario' => ['required', 'numeric', 'min:0'],
            'detalle.*.descuento_monto' => ['nullable', 'numeric', 'min:0'],
            'detalle.*.tasa_iva' => ['nullable', 'numeric', 'min:0', 'lt:1'],
        ]);

        $proveedor = Proveedor::findOrFail($data['proveedor_id']);

        if (! $proveedor->activo) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'El proveedor está inactivo, no admite nuevas compras.',
            ]);
        }

        return $data;
    }

    private function guardarCompra(Compra $compra, array $data, int $usuarioId): Compra
    {
        $compra->fill([
            'sucursal_id' => $data['sucursal_id'],
            'proveedor_id' => $data['proveedor_id'],
            'usuario_id' => $usuarioId,
            'folio_documento' => $data['folio_documento'],
            'fecha_documento' => $data['fecha_documento'],
            'estatus' => 'BORRADOR',
        ]);
        $compra->save();

        foreach (array_values($data['detalle']) as $indice => $linea) {
            $producto = Producto::findOrFail($linea['producto_id']);

            DetalleCompra::create([
                'compra_id' => $compra->id,
                'producto_id' => $producto->id,
                'numero_renglon' => $indice + 1,
                'descripcion' => $producto->nombre,
                'cantidad' => $linea['cantidad'],
                'costo_unitario' => $linea['costo_unitario'],
                'descuento_monto' => $linea['descuento_monto'] ?? 0,
                'tasa_iva' => $linea['tasa_iva'] ?? 0,
            ]);
        }

        $totales = DB::table('detalle_compra')
            ->where('compra_id', $compra->id)
            ->selectRaw('SUM(descuento_monto) as descuento, SUM(importe_neto) as subtotal, SUM(impuesto_monto) as impuesto_total')
            ->first();

        $compra->update([
            'subtotal' => $totales->subtotal ?? 0,
            'descuento_total' => $totales->descuento ?? 0,
            'impuesto_total' => $totales->impuesto_total ?? 0,
            'total' => ($totales->subtotal ?? 0) + ($totales->impuesto_total ?? 0),
        ]);

        return $compra->fresh();
    }

    private function resumen(Compra $compra): array
    {
        return [
            'id' => $compra->id,
            'folio_documento' => $compra->folio_documento,
            'proveedor' => $compra->proveedor?->razon_social,
            'sucursal' => $compra->sucursal?->nombre,
            'fecha_documento' => $compra->fecha_documento,
            'estatus' => $compra->estatus,
            'total' => $compra->total,
        ];
    }

    private function detalle(Compra $compra): array
    {
        return array_merge($this->resumen($compra), [
            'subtotal' => $compra->subtotal,
            'descuento_total' => $compra->descuento_total,
            'impuesto_total' => $compra->impuesto_total,
            'fecha_recepcion' => $compra->fecha_recepcion,
            'cancelada_motivo' => $compra->cancelada_motivo,
            'detalle' => $compra->detalle->map(fn (DetalleCompra $linea) => [
                'id' => $linea->id,
                'numero_renglon' => $linea->numero_renglon,
                'producto_id' => $linea->producto_id,
                'producto' => $linea->producto?->nombre ?? $linea->descripcion,
                'cantidad' => $linea->cantidad,
                'cantidad_recibida' => $linea->cantidad_recibida,
                'costo_unitario' => $linea->costo_unitario,
                'descuento_monto' => $linea->descuento_monto,
                'tasa_iva' => $linea->tasa_iva,
                'importe_neto' => $linea->importe_neto,
                'impuesto_monto' => $linea->impuesto_monto,
            ])->values(),
        ]);
    }
}
