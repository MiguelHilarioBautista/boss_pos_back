<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Primera alteracion real al esquema congelado de M0: la pantalla "Datos
 * de tu Tienda" del frontend necesita email y logo de la sucursal, y esas
 * columnas no existian en sucursales. Decision del usuario: agregarlas ahi
 * (no en `configuracion`) para que la sucursal principal sea la fuente de
 * verdad de esos datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE sucursales
  ADD COLUMN email    VARCHAR(120) NULL AFTER telefono,
  ADD COLUMN logo_url VARCHAR(255) NULL AFTER email
SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE sucursales DROP COLUMN email, DROP COLUMN logo_url');
    }
};
