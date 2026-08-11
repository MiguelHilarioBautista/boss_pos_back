<?php

namespace App\Exceptions;

use RuntimeException;

class PermisoDenegadoException extends RuntimeException
{
    public static function paraPermiso(string ...$codigosPermiso): self
    {
        $lista = implode(' o ', $codigosPermiso);

        return new self("No tienes el permiso requerido: {$lista}.");
    }
}
