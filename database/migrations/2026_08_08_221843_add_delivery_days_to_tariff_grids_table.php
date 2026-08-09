<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariff_grids', function (Blueprint $table) {
            $table->unsignedTinyInteger('delivery_days')->default(3)->after('is_active');
        });

        $updates = [
            1 => ['label' => 'National (BE) — Éco',      'base_rate' => 48,  'price_per_km' => 0.90, 'price_per_kg' => 0.052, 'delivery_days' => 5, 'is_active' => true],
            2 => ['label' => 'National (BE) — Standard', 'base_rate' => 66,  'price_per_km' => 0.90, 'price_per_kg' => 0.068, 'delivery_days' => 3, 'is_active' => true],
            3 => ['label' => 'National (BE) — Express',  'base_rate' => 95,  'price_per_km' => 0.90, 'price_per_kg' => 0.085, 'delivery_days' => 1, 'is_active' => true],
            4 => ['label' => 'Export FR — Standard',     'base_rate' => 98,  'price_per_km' => 1.15, 'price_per_kg' => 0.088, 'delivery_days' => 3, 'is_active' => true],
            5 => ['label' => 'Export FR — Éco',          'base_rate' => 70,  'price_per_km' => 1.15, 'price_per_kg' => 0.062, 'delivery_days' => 5, 'is_active' => true],
            6 => ['label' => 'Export NL — Standard',     'base_rate' => 88,  'price_per_km' => 1.05, 'price_per_kg' => 0.076, 'delivery_days' => 3, 'is_active' => true],
            7 => ['label' => 'Export NL — Express',      'base_rate' => 132, 'price_per_km' => 1.05, 'price_per_kg' => 0.098, 'delivery_days' => 1, 'is_active' => true],
            8 => ['label' => 'Export DE — Standard',     'base_rate' => 105, 'price_per_km' => 1.20, 'price_per_kg' => 0.085, 'delivery_days' => 3, 'is_active' => true],
            9 => ['label' => 'Export DE — Express',      'base_rate' => 158, 'price_per_km' => 1.20, 'price_per_kg' => 0.099, 'delivery_days' => 1, 'is_active' => true],
            10 => ['label' => 'Export LU — Standard',     'base_rate' => 80,  'price_per_km' => 1.00, 'price_per_kg' => 0.070, 'delivery_days' => 3, 'is_active' => true],
            11 => ['label' => 'Export LU — Éco',          'base_rate' => 58,  'price_per_km' => 1.00, 'price_per_kg' => 0.048, 'delivery_days' => 5, 'is_active' => true],
            12 => ['label' => 'Export LU — Express',      'base_rate' => 118, 'price_per_km' => 1.00, 'price_per_kg' => 0.090, 'delivery_days' => 1, 'is_active' => true],
        ];
        foreach ($updates as $id => $values) {
            DB::table('tariff_grids')->where('id', $id)->update($values);
        }

        $now = now();
        DB::table('tariff_grids')->insert([
            ['id' => 13, 'label' => 'Export FR — Express', 'zone' => 'France',    'base_rate' => 140, 'price_per_km' => 1.15, 'price_per_kg' => 0.099, 'adr_coefficient' => 1.30, 'is_active' => true, 'delivery_days' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'label' => 'Export NL — Éco',     'zone' => 'Pays-Bas',  'base_rate' => 62,  'price_per_km' => 1.05, 'price_per_kg' => 0.055, 'adr_coefficient' => 1.20, 'is_active' => true, 'delivery_days' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 15, 'label' => 'Export DE — Éco',     'zone' => 'Allemagne', 'base_rate' => 74,  'price_per_km' => 1.20, 'price_per_kg' => 0.060, 'adr_coefficient' => 1.20, 'is_active' => true, 'delivery_days' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::statement("SELECT setval('tariff_grids_id_seq', 15)");
    }

    public function down(): void
    {
        DB::table('tariff_grids')->whereIn('id', [13, 14, 15])->delete();

        Schema::table('tariff_grids', function (Blueprint $table) {
            $table->dropColumn('delivery_days');
        });
    }
};
