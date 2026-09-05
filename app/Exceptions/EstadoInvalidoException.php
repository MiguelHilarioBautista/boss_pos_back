<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * E3 (M3): transicion de estado invalida (p.ej. recibir/cancelar una
 * compra ya RECIBIDA o CANCELADA). Mismo patron que
 * StockInsuficienteException de M2 -> 409, nunca un 500 crudo.
 */
class EstadoInvalidoException extends RuntimeException
{
    public static function paraTransicion(string $entidad, string $estadoActual, string $accion): self
    {
        return new self("No se puede {$accion} porque {$entidad} está en estado {$estadoActual}.");
    }
}
