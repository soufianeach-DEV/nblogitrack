<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared(file_get_contents(database_path('seeders/sql/clients.sql')));

        // Le jeu de demonstration inscrit chaque entreprise par un compte
        // du meme numero : ce compte en devient l'administrateur, et la
        // numerotation des entreprises reprend apres la derniere.
        DB::statement("UPDATE users SET client_id = users.id, company_role = 'ADMIN'
            WHERE role = 'CLIENT' AND client_id IS NULL AND EXISTS (SELECT 1 FROM clients WHERE clients.id = users.id)");
        DB::statement("SELECT setval('clients_id_seq', GREATEST((SELECT COALESCE(MAX(id), 0) FROM clients), 1))");
    }
}
