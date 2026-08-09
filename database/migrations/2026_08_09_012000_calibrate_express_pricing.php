<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tariff_grids')->where('delivery_days', 1)->where('zone', 'Belgique')->update(['price_per_km' => 1.70]);
        DB::table('tariff_grids')->where('delivery_days', 1)->where('zone', '!=', 'Belgique')->update(['price_per_km' => 2.125]);
    }

    public function down(): void
    {
        DB::table('tariff_grids')->where('delivery_days', 1)->where('zone', 'Belgique')->update(['price_per_km' => 0.35]);
        DB::table('tariff_grids')->where('delivery_days', 1)->where('zone', '!=', 'Belgique')->update(['price_per_km' => 0.4375]);
    }
};
