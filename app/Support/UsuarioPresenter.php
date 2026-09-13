<?php

namespace App\Support;

use App\Models\User;

/**
 * Forma de respuesta comun para un usuario (login/me/alta usuario, MO.pdf
 * S5.1/S5.2). Nunca incluye password_hash/pin_hash (ya ocultos por el
 * modelo), solo lo que el frontend necesita para pintar sesion/RBAC.
 */
class UsuarioPresenter
{
    public static function contexto(User $usuario): array
    {
        return [
            // Sesion propia: el frontend pinta el rol tal cual, por eso aqui
            // va el nombre legible ("Administrador") y no el codigo interno
            // ("ADMIN") que si se usa en listados/detalle de otros usuarios.
            'usuario' => array_merge(self::resumen($usuario), ['rol' => $usuario->rol?->nombre]),
            'permisos' => $usuario->permisos(),
            'sucursales' => $usuario->sucursales->map(fn ($s) => [
                'id' => $s->id,
                'codigo' => $s->codigo,
                'nombre' => $s->nombre,
                'direccion' => $s->direccion,
                'telefono' => $s->telefono,
                'email' => $s->email,
                'logo_url' => $s->logo_url,
                'numero_exterior' => $s->numero_exterior,
                'codigo_postal' => $s->codigo_postal,
                'pais' => $s->pais,
                'estado' => $s->estado,
                'es_principal' => (bool) $s->pivot->es_principal,
            ])->values(),
            'debe_cambiar_pass' => $usuario->debe_cambiar_pass,
        ];
    }

    /**
     * Detalle de un usuario administrado por otro (GET/POST /usuarios/*):
     * resumen + sucursales asignadas. A diferencia de contexto(), no incluye
     * `permisos` (eso es el RBAC de la sesion propia, no de un tercero).
     */
    public static function detalle(User $usuario): array
    {
        return array_merge(self::resumen($usuario), [
            'sucursales' => $usuario->sucursales->map(fn ($s) => [
                'id' => $s->id,
                'codigo' => $s->codigo,
                'nombre' => $s->nombre,
                'es_principal' => (bool) $s->pivot->es_principal,
            ])->values(),
        ]);
    }

    /**
     * Forma reducida para listados (GET /usuarios).
     *
     * RN-CRED-01: no expone `usuario` — ese concepto ya no existe de cara al
     * negocio (login es por email, ver AuthController::login).
     *
     * `nombre` y `apellidos` van separados (asi vive en la tabla, y asi los
     * pide el formulario de Editar) — el frontend concatena si necesita
     * mostrar el nombre completo, no hace falta mandarlo ya armado.
     */
    public static function resumen(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'apellidos' => $usuario->apellidos,
            'numero_empleado' => $usuario->numero_empleado,
            'email' => $usuario->email,
            'telefono' => $usuario->telefono,
            'rol_id' => $usuario->rol_id,
            'rol' => $usuario->rol?->codigo,
            'activo' => $usuario->activo,
            'debe_cambiar_pass' => $usuario->debe_cambiar_pass,
            'ultimo_acceso' => $usuario->ultimo_acceso?->toIso8601String(),
        ];
    }
}
