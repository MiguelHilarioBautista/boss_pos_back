<?php

namespace App\Support;

/**
 * Politica minima de contraseña (D6, MO.pdf S10.2): 8+ caracteres, al menos
 * una letra y un numero. Compartida entre AuthController::cambiarPassword y
 * UsuarioController::store para no divergir entre alta y autocambio.
 */
class PasswordPolicy
{
    /**
     * @return array<int, string>
     */
    public static function reglas(): array
    {
        return ['min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'];
    }
}
