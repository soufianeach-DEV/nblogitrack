<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::statement('TRUNCATE users, vehicles, tariff_grids, clients, client_contacts, drivers, transport_orders CASCADE');

        $this->call([
            UserSeeder::class,
            VehicleSeeder::class,
            TariffGridSeeder::class,
            ClientSeeder::class,
            ClientContactSeeder::class,
            DriverSeeder::class,
            TransportOrderSeeder::class,
        ]);

        foreach (['users', 'tariff_grids', 'client_contacts', 'transport_orders'] as $t) {
            DB::statement("SELECT setval('{$t}_id_seq', (SELECT COALESCE(MAX(id), 1) FROM {$t}))");
        }

        DB::statement('UPDATE transport_orders SET tracking_code = upper(substr(md5(random()::text || id::text), 1, 12)) WHERE tracking_code IS NULL');
    }
}
