<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('Jeu de démonstration refusé : réservé aux environnements local et testing.');

            return;
        }

        DB::statement('TRUNCATE users, vehicles, tariff_grids, clients, client_contacts, drivers, transport_orders, invoices, invoice_lines, purchase_invoices CASCADE');
        $this->call([
            UserSeeder::class,
            VehicleSeeder::class,
            TariffGridSeeder::class,
            ClientSeeder::class,
            ClientContactSeeder::class,
            DriverSeeder::class,
            TransportOrderSeeder::class,
            InvoiceSeeder::class,
            PurchaseInvoiceSeeder::class,
            TranslationSeeder::class,
            PageSeeder::class,
            ProcessingRecordSeeder::class,
        ]);

        foreach (['users', 'tariff_grids', 'client_contacts', 'transport_orders', 'purchase_invoices'] as $t) {
            DB::statement("SELECT setval('{$t}_id_seq', (SELECT COALESCE(MAX(id), 1) FROM {$t}))");
        }

        DB::statement('UPDATE transport_orders SET tracking_code = upper(substr(md5(random()::text || id::text), 1, 12)) WHERE tracking_code IS NULL');

        if (DB::table('postal_codes')->doesntExist()) {
            $this->command?->warn('Les codes postaux sont absents : lancez « php artisan geo:import-postal-codes ».');
        }
    }
}
