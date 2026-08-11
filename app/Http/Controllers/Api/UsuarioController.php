<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\UsuarioPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion de usuarios (alta / baja / reactivar). Extension deliberada de M0
 * mas alla de su alcance original en MO.pdf S1.3 (que la marca como "solo
 * seed inicial, CRUD via UI es M1+") — decidido con el usuario del proyecto.
 * Protegido por el permiso `usuario.gestionar` ya sembrado (S15 del .sql).
 *
 * P2 (Reglas de negocio.pdf): "nada se borra". No existe destroy(): dar de
 * baja es desactivar (activo=false), nunca un DELETE real.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuarios = User::query()
            ->when($request->boolean('solo_activos'), fn ($q) => $q->where('activo', true))
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->get();

        return response()->json([
            'usuarios' => $usuarios->map(fn (User $u) => UsuarioPresenter::resumen($u))->values(),
        ]);
    }

    public function show(User $usuario): JsonResponse
    {
        return response()->json(UsuarioPresenter::detalle($usuario));
    }

    /**
     * Alta de usuario. Segregacion de funciones (Reglas de negocio.pdf S11.2):
     * "Alta de usuario | Administrador | —" — no requiere autorizacion
     * adicional de un segundo usuario, solo el permiso `usuario.gestionar`.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'usuario' => ['required', 'string', 'max:50', 'unique:usuarios,usuario'],
            'password_inicial' => array_merge(['required', 'string'], PasswordPolicy::reglas()),
            'nombre' => ['required', 'string', 'max:60'],
            'apellidos' => ['required', 'string', 'max:80'],
            'rol_id' => ['required', 'integer', 'exists:roles,id'],
            'numero_empleado' => ['nullable', 'string', 'max:20', 'unique:usuarios,numero_empleado'],
            'email' => ['nullable', 'email', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'sucursales' => ['required', 'array', 'min:1'],
            'sucursales.*' => ['integer', 'distinct', 'exists:sucursales,id'],
            'sucursal_principal_id' => ['required', 'integer', Rule::in($request->input('sucursales', []))],
        ]);

        if (strcasecmp($data['password_inicial'], $data['usuario']) === 0) {
            throw ValidationException::withMessages([
                'password_inicial' => 'La contraseña inicial no puede ser igual al usuario.',
            ]);
        }

        $usuario = DB::transaction(function () use ($data, $request) {
            $usuario = User::create([
                'usuario' => $data['usuario'],
                'password_hash' => Hash::make($data['password_inicial']),
                'nombre' => $data['nombre'],
                'apellidos' => $data['apellidos'],
                'rol_id' => $data['rol_id'],
                'numero_empleado' => $data['numero_empleado'] ?? null,
                'email' => $data['email'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'activo' => true,
                'debe_cambiar_pass' => true,
            ]);

            $usuario->sucursales()->sync(collect($data['sucursales'])->mapWithKeys(fn ($id) => [
                $id => ['es_principal' => $id === $data['sucursal_principal_id']],
            ])->all());

            Auditoria::create([
                'usuario_id' => $request->user()->id,
                'tabla' => 'usuarios',
                'registro_id' => $usuario->id,
                'accion' => 'INSERT',
                'valores_nuevos' => ['usuario' => $usuario->usuario, 'rol_id' => $usuario->rol_id],
                'ip_origen' => $request->ip(),
            ]);

            return $usuario;
        });

        return response()->json(UsuarioPresenter::detalle($usuario->fresh()), 201);
    }

    /**
     * Baja. Nunca DELETE (P2) — solo desactiva. Un usuario no puede
     * desactivarse a si mismo (evita bloquearse fuera del sistema).
     */
    public function desactivar(Request $request, User $usuario): JsonResponse
    {
        if ($usuario->id === $request->user()->id) {
            return response()->json([
                'error' => 'operacion_invalida',
                'mensaje' => 'No puedes desactivar tu propia cuenta.',
            ], 422);
        }

        if (! $usuario->activo) {
            return response()->json(UsuarioPresenter::detalle($usuario));
        }

        $usuario->forceFill(['activo' => false])->save();

        Auditoria::create([
            'usuario_id' => $request->user()->id,
            'tabla' => 'usuarios',
            'registro_id' => $usuario->id,
            'accion' => 'UPDATE',
            'valores_anteriores' => ['activo' => true],
            'valores_nuevos' => ['activo' => false],
            'ip_origen' => $request->ip(),
        ]);

        return response()->json(UsuarioPresenter::detalle($usuario));
    }

    /**
     * Reactivar (MO.pdf S3.4, diagrama de estados: Inactiva --reactivacion
     * admin--> Activa). Limpia el bloqueo/intentos previos para que el
     * usuario no vuelva a una cuenta bloqueada sin saberlo.
     */
    public function reactivar(Request $request, User $usuario): JsonResponse
    {
        if ($usuario->activo) {
            return response()->json(UsuarioPresenter::detalle($usuario));
        }

        $usuario->forceFill([
            'activo' => true,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();

        Auditoria::create([
            'usuario_id' => $request->user()->id,
            'tabla' => 'usuarios',
            'registro_id' => $usuario->id,
            'accion' => 'UPDATE',
            'valores_anteriores' => ['activo' => false],
            'valores_nuevos' => ['activo' => true],
            'ip_origen' => $request->ip(),
        ]);

        return response()->json(UsuarioPresenter::detalle($usuario));
    }
}
