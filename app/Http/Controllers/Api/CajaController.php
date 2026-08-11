<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Caja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CajaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cajas = Caja::query()
            ->when($request->filled('sucursal_id'), fn ($q) => $q->where('sucursal_id', $request->integer('sucursal_id')))
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('codigo')
            ->get();

        return response()->json(['cajas' => $cajas]);
    }

    /**
     * D-M1-3 reforzado: si la serie de esta caja todavia no tiene folios
     * provisionados en su sucursal (p.ej. una caja nueva con una serie que
     * no es la de la sucursal), se crean aqui mismo, idempotente. Ninguna
     * caja puede quedar sin folios sin importar por donde se dio de alta.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:60'],
            'serie_folio' => ['required', 'string', 'size:3'],
            'identificador_hw' => ['nullable', 'string', 'max:80'],
        ]);

        $data['codigo'] = strtoupper($data['codigo']);
        $data['serie_folio'] = strtoupper($data['serie_folio']);

        $this->validarUnicidadPorSucursal($data);

        $caja = DB::transaction(function () use ($data, $request) {
            $caja = Caja::create($data);

            foreach (['VENTA', 'DEVOL'] as $tipoDoc) {
                DB::table('folios')->insertOrIgnore([
                    'sucursal_id' => $data['sucursal_id'],
                    'serie' => $data['serie_folio'],
                    'tipo_doc' => $tipoDoc,
                    'ultimo_folio' => 0,
                ]);
            }

            Auditoria::create([
                'usuario_id' => $request->user()->id,
                'sucursal_id' => $data['sucursal_id'],
                'tabla' => 'cajas',
                'registro_id' => $caja->id,
                'accion' => 'INSERT',
                'valores_nuevos' => ['codigo' => $caja->codigo, 'serie_folio' => $caja->serie_folio],
                'ip_origen' => $request->ip(),
            ]);

            return $caja;
        });

        return response()->json($caja->fresh(), 201);
    }

    public function update(Request $request, Caja $caja): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:60'],
            'identificador_hw' => ['nullable', 'string', 'max:80'],
        ]);

        $caja->update($data);

        return response()->json($caja->fresh());
    }

    /**
     * Baja/reactivacion (D-M1-5: se audita). Igual que el resto de
     * catalogos, activo nunca se toca desde update(), solo desde aqui.
     */
    public function estado(Request $request, Caja $caja): JsonResponse
    {
        $data = $request->validate(['activo' => ['required', 'boolean']]);
        $anterior = $caja->activo;

        $caja->update($data);

        Auditoria::create([
            'usuario_id' => $request->user()->id,
            'sucursal_id' => $caja->sucursal_id,
            'tabla' => 'cajas',
            'registro_id' => $caja->id,
            'accion' => 'UPDATE',
            'valores_anteriores' => ['activo' => $anterior],
            'valores_nuevos' => ['activo' => $caja->activo],
            'ip_origen' => $request->ip(),
        ]);

        return response()->json($caja->fresh());
    }

    /**
     * uq_caja_suc_codigo y uq_caja_suc_serie son unicas POR SUCURSAL, no
     * globalmente (Reglas de negocio.pdf S3.3) — se valida a mano porque
     * Rule::unique()->where() ya conoce sucursal_id solo si esta en $data.
     */
    private function validarUnicidadPorSucursal(array $data): void
    {
        validator($data, [
            'codigo' => [Rule::unique('cajas', 'codigo')->where('sucursal_id', $data['sucursal_id'])],
            'serie_folio' => [Rule::unique('cajas', 'serie_folio')->where('sucursal_id', $data['sucursal_id'])],
        ], [
            'codigo.unique' => 'Ya existe una caja con ese código en esta sucursal.',
            'serie_folio.unique' => 'Ya existe una caja con esa serie en esta sucursal.',
        ])->validate();
    }
}
