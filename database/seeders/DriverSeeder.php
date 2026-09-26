<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared(file_get_contents(database_path('seeders/sql/drivers.sql')));

        // Le jeu de demonstration numerote chaque fiche comme son compte :
        // le lien se fait par user_id, et la numerotation des fiches
        // reprend plus loin.
        DB::statement('UPDATE drivers SET user_id = id WHERE user_id IS NULL AND EXISTS (SELECT 1 FROM users WHERE users.id = drivers.id)');

        // Un chauffeur dont la retraite prevue est passee est parti : sa
        // fiche le dit, et son compte est ferme, comme le fait l'ecran
        // Chauffeurs. Un motif de depart sans date ne veut rien dire.
        DB::statement("UPDATE drivers SET left_on = retirement_planned_on, departure_reason = 'RETRAITE', is_available = FALSE WHERE retirement_planned_on < CURRENT_DATE AND left_on IS NULL");
        DB::statement('UPDATE drivers SET departure_reason = NULL WHERE left_on IS NULL');
        DB::statement('UPDATE users SET is_active = FALSE WHERE id IN (SELECT user_id FROM drivers WHERE left_on IS NOT NULL AND left_on <= CURRENT_DATE)');

        // Le certificat ADR vaut cinq ans : les chauffeurs certifies du jeu
        // de demonstration recoivent une echeance etalee sur les annees a
        // venir, sans quoi aucune matiere dangereuse ne pourrait partir.
        DB::statement("UPDATE drivers SET adr_expiry = CURRENT_DATE + ((id % 40) + 6) * INTERVAL '1 month' WHERE adr_certified AND adr_expiry IS NULL");
        DB::statement("SELECT setval('drivers_id_seq', GREATEST((SELECT COALESCE(MAX(id), 0) FROM drivers), (SELECT COALESCE(MAX(id), 0) FROM users)) + 1000)");
    }
}
