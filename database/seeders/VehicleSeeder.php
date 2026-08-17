<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::unprepared(file_get_contents(database_path('seeders/sql/vehicles.sql')));

        // Un camion dont le controle technique a expire ne prend pas la
        // route : il ne peut donc pas etre disponible a l'affectation.
        //
        // La regle se calcule ici plutot que de figer « is_available »
        // dans le fichier, parce que les dates y sont absolues et que le
        // jour d'aujourd'hui avance. Fige, le jeu redeviendrait faux au
        // premier controle qui expire apres l'ecriture du fichier.
        //
        // L'ecran du parc garde son filtre « controle depasse » et son
        // compteur : ces camions restent visibles, ils ne sont plus
        // proposables.
        $immobilises = DB::table('vehicles')
            ->whereNotNull('inspection_valid_until')
            ->whereRaw('inspection_valid_until < current_date')
            ->update(['is_available' => false]);

        if ($immobilises > 0) {
            $this->command?->info("  {$immobilises} camion(s) immobilise(s) : controle technique expire.");
        }
    }
}
