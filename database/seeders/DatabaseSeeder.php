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
        // Les donnees de demonstration vident les tables avant de les remplir et
        // creent des comptes dont le mot de passe est public. Hors developpement,
        // les executer detruirait les donnees reelles et ouvrirait l'application.
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
            // Ces deux-la manquaient a l'appel. Une installation neuve
            // sortait donc sans ses pages legales, que le pied de page
            // publie, et sans son registre des traitements, que le
            // reglement europeen impose de tenir.
            PageSeeder::class,
            ProcessingRecordSeeder::class,
        ]);

        foreach (['users', 'tariff_grids', 'client_contacts', 'transport_orders', 'purchase_invoices'] as $t) {
            DB::statement("SELECT setval('{$t}_id_seq', (SELECT COALESCE(MAX(id), 1) FROM {$t}))");
        }

        DB::statement('UPDATE transport_orders SET tracking_code = upper(substr(md5(random()::text || id::text), 1, 12)) WHERE tracking_code IS NULL');

        // Les codes postaux ne sont pas un jeu de demonstration : ce sont
        // six cent mille lignes telechargees chez GeoNames, dont dependent
        // le geocodage, le calcul des distances et donc tous les prix.
        // Les embarquer dans ce fichier alourdirait le depot pour rien ;
        // les oublier laisse une application qui ne sait plus tarifer.
        if (DB::table('postal_codes')->doesntExist()) {
            $this->command?->warn('Les codes postaux sont absents : lancez « php artisan geo:import-postal-codes ».');
        }
    }
}
