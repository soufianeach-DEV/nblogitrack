<?php

namespace Tests\Concerns;

use App\Models\TariffGrid;

/**
 * Les grilles BE, FR, NL, DE et LU viennent du jeu de demonstration, pas
 * des migrations : les tests de trajets les recreent avec les memes
 * valeurs.
 */
trait GrillesDeDemonstration
{
    protected function creerLesGrillesDeDemonstration(): void
    {
        $sql = (string) file_get_contents(database_path('seeders/sql/tariff_grids.sql'));
        preg_match_all("/\\(\\s*\\d+,\\s*'([^']+)',\\s*'([A-Z]{2})',\\s*([\\d.]+),\\s*([\\d.]+),\\s*([\\d.]+),\\s*([\\d.]+),\\s*TRUE,\\s*(\\d+),\\s*'([A-Z]+)'/u", $sql, $lignes, PREG_SET_ORDER);

        foreach ($lignes as [, $libelle, $zone, $base, $km, $kg, $adr, $jours, $niveau]) {
            if (TariffGrid::where('zone', $zone)->where('service_level', $niveau)->exists()) {
                continue;
            }

            TariffGrid::create([
                'label' => $libelle, 'zone' => $zone, 'base_rate' => $base, 'price_per_km' => $km,
                'price_per_kg' => $kg, 'adr_coefficient' => $adr, 'is_active' => true,
                'delivery_days' => (int) $jours, 'service_level' => $niveau,
            ]);
        }
    }
}
