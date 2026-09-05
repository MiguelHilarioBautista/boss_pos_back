<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La pantalla "Datos de tu Tienda" pide direccion en campos separados
 * (numero exterior, codigo postal, pais, estado) ademas del `direccion`
 * VARCHAR libre ya existente, que ya cubre la calle — no se duplica.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE sucursales
  ADD COLUMN numero_exterior  VARCHAR(20)  NULL AFTER logo_url,
  ADD COLUMN codigo_postal    VARCHAR(10)  NULL AFTER numero_exterior,
  ADD COLUMN pais             VARCHAR(60)  NULL AFTER codigo_postal,
  ADD COLUMN estado           VARCHAR(60)  NULL AFTER pais
SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE sucursales DROP COLUMN numero_exterior, DROP COLUMN codigo_postal, DROP COLUMN pais, DROP COLUMN estado');
    }
};
