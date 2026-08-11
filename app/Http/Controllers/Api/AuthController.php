<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Configuracion;
use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\UsuarioPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /auth/login — CU-01. Sigue el diagrama de flujo de MO.pdf S3.1 y
     * las reglas RN-M0-01..08. Mensajes siempre genericos: nunca revela si el
     * usuario existe.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $usuario = User::where('usuario', $data['usuario'])->first();

        if (! $usuario || ! $usuario->activo) {
            $this->registrarAcceso($usuario?->id, 'LOGIN_FALLIDO', $request);

            return $usuario
                ? $this->error('cuenta_inactiva', 'La cuenta esta inactiva.', 403)
                : $this->error('credenciales_invalidas', 'Usuario o contraseña incorrectos.', 401);
        }

        if ($usuario->bloqueado_hasta && $usuario->bloqueado_hasta->isFuture()) {
            return $this->error('cuenta_bloqueada', 'Tu cuenta esta temporalmente bloqueada. Intenta mas tarde.', 423);
        }

        if (! Hash::check($data['password'], $usuario->password_hash)) {
            $this->registrarIntentoFallido($usuario);
            $this->registrarAcceso($usuario->id, 'LOGIN_FALLIDO', $request);

            return $this->error('credenciales_invalidas', 'Usuario o contraseña incorrectos.', 401);
        }

        $usuario->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso' => now(),
        ])->save();

        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        $this->registrarAcceso($usuario->id, 'LOGIN', $request);

        return response()->json(UsuarioPresenter::contexto($usuario));
    }

    /**
     * POST /auth/logout — CU-04.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    /**
     * GET /auth/me — CU-05.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(UsuarioPresenter::contexto($request->user()));
    }

    /**
     * POST /auth/pin — CU-02. Revalida identidad, no otorga permisos (RN-M0-03).
     */
    public function pin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        $usuario = $request->user();

        if (! $usuario->pin_hash) {
            return $this->error('pin_no_configurado', 'Este usuario no tiene un PIN configurado.', 422);
        }

        if (! Hash::check($data['pin'], $usuario->pin_hash)) {
            return $this->error('pin_incorrecto', 'PIN incorrecto.', 401);
        }

        return response()->json(['revalidado' => true]);
    }

    /**
     * POST /auth/cambiar-password — CU-03.
     */
    public function cambiarPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_actual' => ['required', 'string'],
            'password_nuevo' => ['required', 'string', 'confirmed:password_confirmacion'],
        ]);

        $usuario = $request->user();

        if (! Hash::check($data['password_actual'], $usuario->password_hash)) {
            return $this->error('password_incorrecto', 'La contraseña actual es incorrecta.', 401);
        }

        $this->validarPoliticaPassword($data['password_nuevo'], $usuario);

        $usuario->forceFill([
            'password_hash' => Hash::make($data['password_nuevo']),
            'debe_cambiar_pass' => false,
            'password_actualizado' => now(),
        ])->save();

        return response()->json(['cambiada' => true]);
    }

    /**
     * Politica minima de contraseña (D6): 8+ caracteres, al menos una letra y
     * un numero, distinta del usuario y de la contraseña actual.
     */
    private function validarPoliticaPassword(string $nueva, User $usuario): void
    {
        $validator = Validator::make(['password_nuevo' => $nueva], [
            'password_nuevo' => PasswordPolicy::reglas(),
        ]);

        if (strcasecmp($nueva, $usuario->usuario) === 0 || Hash::check($nueva, $usuario->password_hash)) {
            $validator->errors()->add('password_nuevo', 'La nueva contraseña debe ser distinta del usuario y de la contraseña actual.');
        }

        if ($validator->fails() || $validator->errors()->isNotEmpty()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    private function registrarIntentoFallido(User $usuario): void
    {
        $maximo = (int) Configuracion::valor('login_intentos_max', 5);
        $minutosBloqueo = (int) Configuracion::valor('login_bloqueo_minutos', 15);

        $intentos = $usuario->intentos_fallidos + 1;
        $bloqueado = $intentos >= $maximo ? now()->addMinutes($minutosBloqueo) : null;

        $usuario->forceFill([
            'intentos_fallidos' => $intentos,
            'bloqueado_hasta' => $bloqueado,
        ])->save();
    }

    private function registrarAcceso(?int $usuarioId, string $accion, Request $request): void
    {
        Auditoria::create([
            'usuario_id' => $usuarioId,
            'tabla' => 'usuarios',
            'registro_id' => $usuarioId,
            'accion' => $accion,
            'ip_origen' => $request->ip(),
        ]);
    }

    private function error(string $codigo, string $mensaje, int $status): JsonResponse
    {
        return response()->json(['error' => $codigo, 'mensaje' => $mensaje], $status);
    }
}
