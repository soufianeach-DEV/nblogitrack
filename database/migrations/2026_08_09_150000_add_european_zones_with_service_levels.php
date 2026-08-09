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
            $table->string('service_level', 10)->default('STANDARD')->after('delivery_days');
        });

        DB::table('tariff_grids')->where('delivery_days', 5)->update(['service_level' => 'ECO']);
        DB::table('tariff_grids')->where('delivery_days', 3)->update(['service_level' => 'STANDARD']);
        DB::table('tariff_grids')->where('delivery_days', 1)->update(['service_level' => 'EXPRESS']);

        foreach (['Belgique' => 'BE', 'France' => 'FR', 'Pays-Bas' => 'NL', 'Allemagne' => 'DE', 'Luxembourg' => 'LU'] as $nom => $code) {
            DB::table('tariff_grids')->where('zone', $nom)->update(['zone' => $code]);
        }

        $bandes = [
            2 => ['eco' => [85, 0.070, 6],  'standard' => [120, 0.095, 4], 'express' => [170, 0.105, 2]],
            3 => ['eco' => [100, 0.078, 7], 'standard' => [140, 0.102, 5], 'express' => [200, 0.112, 2]],
            4 => ['eco' => [120, 0.085, 8], 'standard' => [165, 0.110, 6], 'express' => [235, 0.120, 3]],
        ];

        $paysEurope = [
            ['AT', 'Autriche', 3, 0],
            ['BG', 'Bulgarie', 4, 0],
            ['CH', 'Suisse', 2, 0],
            ['CZ', 'Tchéquie', 2, 0],
            ['DK', 'Danemark', 2, 0],
            ['EE', 'Estonie', 4, 0],
            ['ES', 'Espagne', 4, 0],
            ['FI', 'Finlande', 4, 0],
            ['GB', 'Royaume-Uni', 2, 40],
            ['GR', 'Grèce', 4, 0],
            ['HR', 'Croatie', 3, 0],
            ['HU', 'Hongrie', 3, 0],
            ['IE', 'Irlande', 3, 60],
            ['IT', 'Italie', 4, 0],
            ['LT', 'Lituanie', 4, 0],
            ['LV', 'Lettonie', 4, 0],
            ['NO', 'Norvège', 3, 0],
            ['PL', 'Pologne', 3, 0],
            ['PT', 'Portugal', 4, 0],
            ['RO', 'Roumanie', 4, 0],
            ['SE', 'Suède', 4, 0],
            ['SI', 'Slovénie', 3, 0],
            ['SK', 'Slovaquie', 3, 0],
        ];

        $niveaux = [
            'eco' => ['suffixe' => 'Éco', 'service' => 'ECO', 'km' => 0.225, 'adr' => 1.20],
            'standard' => ['suffixe' => 'Standard', 'service' => 'STANDARD', 'km' => 0.3125, 'adr' => 1.25],
            'express' => ['suffixe' => 'Express', 'service' => 'EXPRESS', 'km' => 2.125, 'adr' => 1.30],
        ];

        $now = now();
        $lignes = [];

        foreach ($paysEurope as [$code, $nom, $bande, $ferry]) {
            foreach ($niveaux as $cle => $niveau) {
                [$base, $kg, $jours] = $bandes[$bande][$cle];
                $lignes[] = [
                    'label' => "Export {$nom} — {$niveau['suffixe']}",
                    'zone' => $code,
                    'base_rate' => $base + $ferry,
                    'price_per_km' => $niveau['km'],
                    'price_per_kg' => $kg,
                    'adr_coefficient' => $niveau['adr'],
                    'is_active' => true,
                    'delivery_days' => $jours,
                    'service_level' => $niveau['service'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('tariff_grids')->insert($lignes);

        DB::statement("SELECT setval('tariff_grids_id_seq', (SELECT MAX(id) FROM tariff_grids))");
    }

    public function down(): void
    {
        DB::table('tariff_grids')->whereNotIn('zone', ['BE', 'FR', 'NL', 'DE', 'LU'])->delete();

        foreach (['BE' => 'Belgique', 'FR' => 'France', 'NL' => 'Pays-Bas', 'DE' => 'Allemagne', 'LU' => 'Luxembourg'] as $code => $nom) {
            DB::table('tariff_grids')->where('zone', $code)->update(['zone' => $nom]);
        }

        Schema::table('tariff_grids', function (Blueprint $table) {
            $table->dropColumn('service_level');
        });
    }
};
