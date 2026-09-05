<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
     * Usada por pantallas tipo "Datos de tu Tienda" para precargar una
     * sucursal puntual (p.ej. la marcada es_principal para el usuario en
     * login) antes de editarla con update().
     */
    public function show(Sucursal $sucursal): JsonResponse
    {
        return response()->json($sucursal);
    }

    /**
     * RN-M1-06: toda sucursal nueva requiere sus series en folios antes de
     * operar. Se automatiza aqui, en la misma transaccion que el alta
     * (D-M1-3): si algo falla, no queda una sucursal a medias sin folios.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizarZonaFrontera($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'codigo' => ['required', 'string', 'max:20', 'unique:sucursales,codigo'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'numero_exterior' => ['nullable', 'string', 'max:20'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'pais' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', 'string', 'max:60'],
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
        $this->normalizarZonaFrontera($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:3072'],
            'numero_exterior' => ['nullable', 'string', 'max:20'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'pais' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', 'string', 'max:60'],
            'zona_frontera' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_url'] = $this->guardarLogo($request->file('logo'), $sucursal);
        }
        unset($data['logo']);

        $sucursal->update($data);

        return response()->json($sucursal->fresh());
    }

    /**
     * multipart/form-data (subida de logo) siempre manda booleanos como
     * texto "true"/"false", que la regla `boolean` de Laravel rechaza (solo
     * acepta true/false/1/0/"1"/"0"). $request->boolean() si los entiende.
     */
    private function normalizarZonaFrontera(Request $request): void
    {
        if ($request->has('zona_frontera')) {
            $request->merge(['zona_frontera' => $request->boolean('zona_frontera')]);
        }
    }

    /**
     * Guarda el logo subido en storage/app/public/logoempresa (accesible
     * via /storage/logoempresa/... una vez corrido `storage:link`) y borra
     * el archivo anterior de la sucursal si tambien vivia ahi.
     */
    private function guardarLogo(UploadedFile $archivo, Sucursal $sucursal): string
    {
        // logo_url puede venir absoluta (accessor del modelo) o relativa;
        // parse_url() normaliza ambas antes de comparar el prefijo.
        $rutaAnterior = $sucursal->logo_url ? parse_url($sucursal->logo_url, PHP_URL_PATH) : null;

        if ($rutaAnterior && str_starts_with($rutaAnterior, '/storage/logoempresa/')) {
            Storage::disk('public')->delete('logoempresa/'.basename($rutaAnterior));
        }

        $ruta = $archivo->store('logoempresa', 'public');

        return '/storage/'.$ruta;
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
