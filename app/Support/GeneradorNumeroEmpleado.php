<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RN-NUM-04/05: prefijo fijo "27" + año (4 digitos) + consecutivo del año
 * (3 digitos, 001-999). Debe llamarse dentro de una transaccion (el
 * llamador ya envuelve el alta en DB::transaction) para que lockForUpdate
 * sirva de algo; el UNIQUE KEY real de `numero_empleado` es la ultima
 * defensa ante una carrera en el primer alta del año (sin fila que
 * bloquear todavia) — ese caso cae al 422 "duplicado" ya mapeado en
 * bootstrap/app.php, igual que el resto de altas concurrentes del sistema.
 */
class GeneradorNumeroEmpleado
{
    public static function generar(): string
    {
        $prefijo = '27'.now()->year;

        $ultimo = DB::table('usuarios')
            ->where('numero_empleado', 'like', $prefijo.'%')
            ->lockForUpdate()
            ->max('numero_empleado');

        $consecutivo = $ultimo ? ((int) substr($ultimo, -3)) + 1 : 1;

        if ($consecutivo > 999) {
            throw new RuntimeException("Se alcanzo el limite de 999 altas de empleados para el año {$prefijo}.");
        }

        return $prefijo.str_pad((string) $consecutivo, 3, '0', STR_PAD_LEFT);
    }
}
