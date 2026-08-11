<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

/**
 * Bloque 15 (parametros globales) + las dos claves nuevas de D2
 * (login_intentos_max / login_bloqueo_minutos) que introduce M0.
 */
class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $valores = [
            ['clave' => 'dias_max_cancelacion', 'valor' => '1', 'tipo_dato' => 'INT', 'descripcion' => 'Dias permitidos para cancelar una venta'],
            ['clave' => 'permite_cancelar_cerrado', 'valor' => 'false', 'tipo_dato' => 'BOOLEAN', 'descripcion' => 'Permitir cancelar ventas de un corte ya cerrado'],
            ['clave' => 'tolerancia_arqueo', 'valor' => '20.00', 'tipo_dato' => 'DECIMAL', 'descripcion' => 'Diferencia de arqueo tolerada sin autorizacion'],
            ['clave' => 'permite_venta_sin_stock', 'valor' => 'false', 'tipo_dato' => 'BOOLEAN', 'descripcion' => 'Permitir vender con existencia insuficiente'],
            ['clave' => 'impuesto_default_codigo', 'valor' => 'IVA16', 'tipo_dato' => 'STRING', 'descripcion' => 'Impuesto por default en alta de productos'],
            ['clave' => 'promociones_acumulables', 'valor' => 'false', 'tipo_dato' => 'BOOLEAN', 'descripcion' => 'Permitir acumular promociones en un mismo renglon'],
            ['clave' => 'dias_max_devolucion', 'valor' => '30', 'tipo_dato' => 'INT', 'descripcion' => 'Dias posteriores a la venta para aceptar devolucion'],
            ['clave' => 'login_intentos_max', 'valor' => '5', 'tipo_dato' => 'INT', 'descripcion' => 'Intentos fallidos antes de bloquear la cuenta (D2, MO.pdf S4.4)'],
            ['clave' => 'login_bloqueo_minutos', 'valor' => '15', 'tipo_dato' => 'INT', 'descripcion' => 'Minutos de bloqueo tras superar los intentos fallidos (D2, MO.pdf S4.4)'],
        ];

        foreach ($valores as $valor) {
            Configuracion::updateOrCreate(
                ['clave' => $valor['clave'], 'sucursal_id' => null],
                $valor
            );
        }
    }
}
