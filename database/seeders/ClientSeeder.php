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

        // L'entreprise de demonstration montre aussi les deux autres roles
        // d'une societe cliente : un collegue qui passe les commandes et un
        // comptable qui regle les factures. Meme mot de passe que son
        // administrateur.
        // Le fichier SQL insere les comptes avec leur numero : la sequence
        // doit repartir apres le dernier.
        DB::statement("SELECT setval('users_id_seq', GREATEST((SELECT COALESCE(MAX(id), 0) FROM users), 1))");

        foreach ([
            ['commandes@nblogitrack.be', 'Commandes', 'ORDERS'],
            ['comptabilite@nblogitrack.be', 'Comptabilité', 'BILLING'],
        ] as [$email, $nom, $role]) {
            DB::statement(<<<'SQL'
                INSERT INTO users (first_name, last_name, email, email_verified_at, password, role, is_active, locale, client_id, company_role, created_at, updated_at)
                SELECT 'Demo', ?, ?, now(), password, 'CLIENT', TRUE, 'fr', client_id, ?, now(), now()
                FROM users WHERE email = 'client@nblogitrack.be' AND client_id IS NOT NULL
                ON CONFLICT (email) DO NOTHING
            SQL, [$nom, $email, $role]);
        }
    }
}
