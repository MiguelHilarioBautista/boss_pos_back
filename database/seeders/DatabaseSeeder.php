<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,
            ConfiguracionSeeder::class,
            ImpuestosSeeder::class,
            UnidadesMedidaSeeder::class,
            MetodosPagoSeeder::class,
            SucursalSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
