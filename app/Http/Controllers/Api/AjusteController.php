<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Producto;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CU-01 (M2). Unico punto de la API que expone ajustes manuales de
 * inventario — SALIDA_VENTA/ENTRADA_COMPRA/etc. los disparan M3/M5/M6/M9
 * directo contra InventarioService, no por aqui.
 */
class AjusteController extends Controller
{
    public function __construct(private InventarioService $inventarioService) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'tipo' => ['required', 'in:AJUSTE_POSITIVO,AJUSTE_NEGATIVO'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'max:200'],
            'autorizado_por_id' => ['required', 'integer', 'exists:usuarios,id'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
        ]);

        $producto = Producto::with('unidadMedida')->findOrFail($data['producto_id']);
        $ejecutor = $request->user();

        if (! $producto->controla_stock) {
            throw ValidationException::withMessages([
                'producto_id' => 'Este producto es un servicio (controla_stock=false) y no genera movimientos de inventario.',
            ]);
        }

        if ($producto->unidadMedida && ! $producto->unidadMedida->permite_decimales
            && fmod((float) $data['cantidad'], 1.0) !== 0.0) {
            throw ValidationException::withMessages([
                'cantidad' => "La unidad {$producto->unidadMedida->codigo} no admite cantidades fraccionarias.",
            ]);
        }

        $this->validarAutorizador($data['autorizado_por_id'], $ejecutor->id);

        $movimiento = $this->inventarioService->aplicarMovimiento([
            'tipo' => $data['tipo'],
            'producto_id' => $data['producto_id'],
            'sucursal_id' => $data['sucursal_id'],
            'cantidad' => $data['cantidad'],
            'costo_unitario' => $data['costo_unitario'] ?? null,
            'usuario_id' => $ejecutor->id,
            'motivo' => $data['motivo'],
        ]);

        // D-M2-4: movimientos_inventario no tiene columna autorizado_por —
        // el doble control queda registrado en auditoria.
        Auditoria::create([
            'usuario_id' => $ejecutor->id,
            'sucursal_id' => $data['sucursal_id'],
            'tabla' => 'movimientos_inventario',
            'registro_id' => $movimiento->id,
            'accion' => 'INSERT',
            'valores_nuevos' => [
                'tipo' => $data['tipo'],
                'cantidad' => $data['cantidad'],
                'motivo' => $data['motivo'],
                'autorizado_por_id' => $data['autorizado_por_id'],
            ],
            'ip_origen' => $request->ip(),
        ]);

        return response()->json([
            'movimiento' => [
                'id' => $movimiento->id,
                'tipo' => $movimiento->tipo,
                'cantidad' => $movimiento->cantidad,
                'costo_unitario' => $movimiento->costo_unitario,
                'stock_anterior' => $movimiento->stock_anterior,
                'stock_nuevo' => $movimiento->stock_nuevo,
                'costo_prom_anterior' => $movimiento->costo_prom_anterior,
                'costo_prom_nuevo' => $movimiento->costo_prom_nuevo,
                'motivo' => $movimiento->motivo,
                'fecha' => $movimiento->fecha,
            ],
            'ejecutado_por' => $ejecutor->usuario,
            'autorizado_por_id' => $data['autorizado_por_id'],
        ], 201);
    }

    private function validarAutorizador(int $autorizadorId, int $ejecutorId): void
    {
        if ($autorizadorId === $ejecutorId) {
            throw ValidationException::withMessages([
                'autorizado_por_id' => 'Quien ejecuta el ajuste no puede autorizarlo (segregación de funciones).',
            ]);
        }

        $autorizador = User::find($autorizadorId);

        if (! $autorizador || ! $autorizador->activo || ! $autorizador->tienePermiso('inventario.ajustar')) {
            throw ValidationException::withMessages([
                'autorizado_por_id' => 'El autorizador debe ser un usuario activo con permiso para ajustar inventario.',
            ]);
        }
    }
}
