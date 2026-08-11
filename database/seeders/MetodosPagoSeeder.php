<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reproduce el bloque 15 del esquema (cierra el mismo hueco que
 * ImpuestosSeeder -- ver comentario ahi). No hay modelo MetodoPago todavia
 * (el CRUD de metodos de pago es de M5 Ventas, no de M1), se usa el query
 * builder directo sobre la tabla.
 */
class MetodosPagoSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            ['codigo' => 'EFE', 'nombre' => 'Efectivo', 'tipo' => 'EFECTIVO', 'afecta_efectivo_caja' => true, 'permite_cambio' => true, 'requiere_referencia' => false, 'requiere_cliente' => false, 'genera_cxc' => false, 'comision_pct' => 0.0000, 'activo' => true, 'orden' => 1],
            ['codigo' => 'TDD', 'nombre' => 'Tarjeta de debito', 'tipo' => 'TARJETA', 'afecta_efectivo_caja' => false, 'permite_cambio' => false, 'requiere_referencia' => true, 'requiere_cliente' => false, 'genera_cxc' => false, 'comision_pct' => 0.0250, 'activo' => true, 'orden' => 2],
            ['codigo' => 'TDC', 'nombre' => 'Tarjeta de credito', 'tipo' => 'TARJETA', 'afecta_efectivo_caja' => false, 'permite_cambio' => false, 'requiere_referencia' => true, 'requiere_cliente' => false, 'genera_cxc' => false, 'comision_pct' => 0.0290, 'activo' => true, 'orden' => 3],
            ['codigo' => 'TRF', 'nombre' => 'Transferencia', 'tipo' => 'TRANSFERENCIA', 'afecta_efectivo_caja' => false, 'permite_cambio' => false, 'requiere_referencia' => true, 'requiere_cliente' => false, 'genera_cxc' => false, 'comision_pct' => 0.0000, 'activo' => true, 'orden' => 4],
            ['codigo' => 'VAL', 'nombre' => 'Vales de despensa', 'tipo' => 'VALE', 'afecta_efectivo_caja' => false, 'permite_cambio' => false, 'requiere_referencia' => true, 'requiere_cliente' => false, 'genera_cxc' => false, 'comision_pct' => 0.0400, 'activo' => true, 'orden' => 5],
            // CRE nace DESACTIVADO: no es un pago, genera cuenta por cobrar (modulo de credito, Reglas de negocio S10).
            ['codigo' => 'CRE', 'nombre' => 'Credito cliente', 'tipo' => 'CREDITO', 'afecta_efectivo_caja' => false, 'permite_cambio' => false, 'requiere_referencia' => false, 'requiere_cliente' => true, 'genera_cxc' => true, 'comision_pct' => 0.0000, 'activo' => false, 'orden' => 9],
        ];

        foreach ($metodos as $metodo) {
            DB::table('metodos_pago')->updateOrInsert(['codigo' => $metodo['codigo']], $metodo);
        }
    }
}
