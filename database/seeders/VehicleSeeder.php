<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared(file_get_contents(database_path('seeders/sql/vehicles.sql')));

        $immobilises = DB::table('vehicles')
            ->whereNotNull('inspection_valid_until')
            ->whereRaw('inspection_valid_until < current_date')
            ->update(['is_available' => false]);

        if ($immobilises > 0) {
            $this->command?->info("  {$immobilises} camion(s) immobilise(s) : controle technique expire.");
        }
    }
}
