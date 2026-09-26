<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared(file_get_contents(database_path('seeders/sql/vehicles.sql')));

        // Le fichier ne dit ni le permis exige ni l'equipement ADR : le
        // permis se deduit du gabarit (un tracteur de 44 t exige le CE,
        // quelle que soit sa carrosserie), les citernes sont equipees ADR.
        DB::statement(<<<'SQL'
            UPDATE vehicles SET permis_requis = CASE
                WHEN vehicle_type = 'Semi-remorque' OR capacity_tonnes >= 18 THEN 'CE'
                WHEN capacity_tonnes > 3.5 THEN 'C'
                WHEN capacity_tonnes > 1.5 THEN 'C1'
                ELSE 'B'
            END
            WHERE permis_requis IS NULL
        SQL);
        DB::table('vehicles')->where('vehicle_type', 'Citerne')->update(['adr_equipe' => true]);

        $immobilises = DB::table('vehicles')
            ->whereNotNull('inspection_valid_until')
            ->whereRaw('inspection_valid_until < current_date')
            ->update(['is_available' => false]);

        if ($immobilises > 0) {
            $this->command?->info("  {$immobilises} camion(s) immobilise(s) : controle technique expire.");
        }
    }
}
