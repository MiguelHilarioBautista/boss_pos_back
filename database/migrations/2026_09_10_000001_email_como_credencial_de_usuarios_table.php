<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RN-Configuracion-Anadir-Usuario (RN-CRED-01/02/03): el login deja de
 * usar `usuario` y pasa a usar `email`. `usuario` ya era NOT NULL+UNIQUE y
 * sus valores existentes ya son unicos por construccion, asi que respaldar
 * `email` desde ahi es seguro (no puede introducir duplicados). Backfill
 * primero, UNIQUE despues — nunca al reves, o la migracion truena si ya
 * habia mas de un email NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE usuarios SET email = usuario WHERE email IS NULL OR email = ''");

        DB::unprepared(<<<'SQL'
ALTER TABLE usuarios
  MODIFY email VARCHAR(120) NOT NULL,
  ADD CONSTRAINT uq_usuario_email UNIQUE (email)
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE usuarios
  DROP INDEX uq_usuario_email,
  MODIFY email VARCHAR(120) NULL
SQL);
    }
};
