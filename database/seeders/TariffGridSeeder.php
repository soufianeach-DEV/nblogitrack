<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TariffGridSeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared(file_get_contents(database_path('seeders/sql/tariff_grids.sql')));
    }
}
