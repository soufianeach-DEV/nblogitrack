<?php

namespace Database\Seeders;

use App\Models\TransportOrder;
use App\Support\Adresse;
use App\Support\Pays;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransportOrderSeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared(file_get_contents(database_path('seeders/sql/transport_orders.sql')));

        // Le fichier ne dit pas les pays : la livraison se lit dans
        // l'adresse (tous les enlevements y sont belges).
        TransportOrder::select('id', 'delivery_address')->get()->each(function (TransportOrder $o) {
            $code = Pays::depuisNom(Adresse::pays((string) $o->delivery_address)) ?? 'BE';

            if ($code !== 'BE') {
                DB::table('transport_orders')->where('id', $o->id)->update(['delivery_country' => $code]);
            }
        });

        (new CoherenceDesAffectations)();
        (new ScenarioFretRetour)();
    }
}
