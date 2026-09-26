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
        // quelle que soit sa carrosserie). Les citernes sont equipees ADR,
        // et un camion sur trois des carrosseries qui chargent des colis ou
        // des palettes, avec ceux a hayon des porteurs et fourgons : sans
        // eux, un envoi dangereux emballe partait en citerne, et un envoi
        // ADR avec hayon ne trouvait aucun camion.
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
        DB::statement(<<<'SQL'
            UPDATE vehicles v SET adr_equipe = true
            FROM (
                SELECT registration, row_number() OVER (PARTITION BY vehicle_type ORDER BY registration) AS rang
                FROM vehicles
                WHERE vehicle_type IN ('Fourgon', 'Porteur', 'Plateau', 'Semi-remorque')
            ) r
            WHERE r.registration = v.registration
              AND (r.rang % 3 = 1 OR (v.has_tail_lift AND v.vehicle_type IN ('Fourgon', 'Porteur')))
        SQL);

        $immobilises = DB::table('vehicles')
            ->whereNotNull('inspection_valid_until')
            ->whereRaw('inspection_valid_until < current_date')
            ->update(['is_available' => false]);

        if ($immobilises > 0) {
            $this->command?->info("  {$immobilises} camion(s) immobilise(s) : controle technique expire.");
        }
    }
}
