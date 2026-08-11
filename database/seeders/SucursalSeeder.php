<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bloque 15: sucursal Matriz, su caja y sus series de folios (VENTA/DEVOL).
 */
class SucursalSeeder extends Seeder
{
    public function run(): void
    {
        $sucursal = Sucursal::updateOrCreate(
            ['codigo' => 'MTZ'],
            ['nombre' => 'Matriz', 'activo' => true]
        );

        $cajaId = DB::table('cajas')->where('sucursal_id', $sucursal->id)->where('codigo', 'CAJA-01')->value('id');

        if (! $cajaId) {
            DB::table('cajas')->insert([
                'sucursal_id' => $sucursal->id,
                'codigo' => 'CAJA-01',
                'nombre' => 'Caja 1',
                'serie_folio' => 'A01',
                'activo' => true,
                'creado_fecha' => now(),
                'modificado_fecha' => now(),
            ]);
        }

        foreach (['VENTA', 'DEVOL'] as $tipoDoc) {
            $existe = DB::table('folios')
                ->where(['sucursal_id' => $sucursal->id, 'serie' => 'A01', 'tipo_doc' => $tipoDoc])
                ->exists();

            if (! $existe) {
                DB::table('folios')->insert([
                    'sucursal_id' => $sucursal->id,
                    'serie' => 'A01',
                    'tipo_doc' => $tipoDoc,
                    'ultimo_folio' => 0,
                ]);
            }
        }
    }
}
