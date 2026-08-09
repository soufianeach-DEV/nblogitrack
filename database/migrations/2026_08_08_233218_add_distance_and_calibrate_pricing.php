<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
       public function up(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('distance_km')->nullable()->after('weight');
        });

        DB::table('tariff_grids')->where('delivery_days', 5)->update(['price_per_km' => 0.18]); 
        DB::table('tariff_grids')->where('delivery_days', 3)->update(['price_per_km' => 0.25]); 
        DB::table('tariff_grids')->where('delivery_days', 1)->update(['price_per_km' => 0.35]);

        DB::table('tariff_grids')->where('zone', '!=', 'Belgique')->update([
            'price_per_km' => DB::raw('price_per_km * 1.25'),
        ]);
    }

    public function down(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropColumn('distance_km');
        });
    }
};