<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use App\Support\GeneradorNumeroEmpleado;
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
     *
     * RN-Configuracion-Anadir-Usuario: el campo "Usuario" desaparece de la
     * pantalla (RN-CRED-01) — el email es la credencial de acceso
     * (RN-CRED-02/03) y numero_empleado se genera solo, nunca se captura
     * (RN-NUM-01/02). `usuario` sigue existiendo en la tabla (NOT NULL+
     * UNIQUE, esquema congelado) pero ya no es dato de negocio: se llena
     * con el mismo numero_empleado, nunca se lee para autenticar.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_inicial' => array_merge(['required', 'string'], PasswordPolicy::reglas()),
            'nombre' => ['required', 'string', 'max:60'],
            'apellidos' => ['required', 'string', 'max:80'],
            'rol_id' => ['required', 'integer', 'exists:roles,id'],
            'email' => ['required', 'email', 'max:120', 'unique:usuarios,email'],
            'telefono' => ['required', 'digits:10'],
            'sucursales' => ['required', 'array', 'min:1'],
            'sucursales.*' => ['integer', 'distinct', 'exists:sucursales,id'],
            'sucursal_principal_id' => ['required', 'integer', Rule::in($request->input('sucursales', []))],
        ]);

        if (strcasecmp($data['password_inicial'], $data['email']) === 0) {
            throw ValidationException::withMessages([
                'password_inicial' => 'La contraseña inicial no puede ser igual al email.',
            ]);
        }

        $usuario = DB::transaction(function () use ($data, $request) {
            $numeroEmpleado = GeneradorNumeroEmpleado::generar();

            $usuario = User::create([
                'usuario' => $numeroEmpleado,
                'password_hash' => Hash::make($data['password_inicial']),
                'nombre' => $data['nombre'],
                'apellidos' => $data['apellidos'],
                'rol_id' => $data['rol_id'],
                'numero_empleado' => $numeroEmpleado,
                'email' => $data['email'],
                'telefono' => $data['telefono'],
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
                'valores_nuevos' => ['numero_empleado' => $usuario->numero_empleado, 'email' => $usuario->email, 'rol_id' => $usuario->rol_id],
                'ip_origen' => $request->ip(),
            ]);

            return $usuario;
        });

        return response()->json(UsuarioPresenter::detalle($usuario->fresh()), 201);
    }

    /**
     * Editar datos de un usuario existente. Mismas reglas de obligatoriedad
     * que el alta (RN-Configuracion-Anadir-Usuario), salvo lo que
     * deliberadamente NO se puede tocar aqui:
     * - numero_empleado / usuario: generados una sola vez en el alta
     *   (RN-NUM-02, "solo lectura"), nunca se aceptan en este request.
     * - password / activo: tienen sus propios flujos dedicados
     *   (restablecer contraseña, desactivar/reactivar) — mezclarlos aqui
     *   perderia el rastro de auditoria especifico de cada accion.
     */
    public function update(Request $request, User $usuario): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:60'],
            'apellidos' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', Rule::unique('usuarios', 'email')->ignore($usuario->id)],
            'telefono' => ['required', 'digits:10'],
            'rol_id' => ['required', 'integer', 'exists:roles,id'],
            'sucursales' => ['required', 'array', 'min:1'],
            'sucursales.*' => ['integer', 'distinct', 'exists:sucursales,id'],
            'sucursal_principal_id' => ['required', 'integer', Rule::in($request->input('sucursales', []))],
        ]);

        DB::transaction(function () use ($data, $usuario, $request) {
            $anterior = $usuario->only(['nombre', 'apellidos', 'email', 'telefono', 'rol_id']);

            $usuario->update([
                'nombre' => $data['nombre'],
                'apellidos' => $data['apellidos'],
                'email' => $data['email'],
                'telefono' => $data['telefono'],
                'rol_id' => $data['rol_id'],
            ]);

            $usuario->sucursales()->sync(collect($data['sucursales'])->mapWithKeys(fn ($id) => [
                $id => ['es_principal' => $id === $data['sucursal_principal_id']],
            ])->all());

            Auditoria::create([
                'usuario_id' => $request->user()->id,
                'tabla' => 'usuarios',
                'registro_id' => $usuario->id,
                'accion' => 'UPDATE',
                'valores_anteriores' => $anterior,
                'valores_nuevos' => $usuario->only(['nombre', 'apellidos', 'email', 'telefono', 'rol_id']),
                'ip_origen' => $request->ip(),
            ]);
        });

        return response()->json(UsuarioPresenter::detalle($usuario->fresh()));
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
