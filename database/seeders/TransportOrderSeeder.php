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

        // Le fichier couvre quatre mois devant nous : une commande pour
        // novembre y etait aussi « creee » en novembre, et le journal
        // s'ouvrait sur des entrees futures. Une commande a venir est passee
        // ces derniers jours. Le numero de suivi porte l'annee ou la
        // commande est creee, comme en production.
        DB::table('transport_orders')->where('created_at', '>', now())->orderBy('id')->get(['id'])
            ->each(function ($o) {
                $cree = now()->subDays(1 + $o->id % 10)->setTime(9 + $o->id % 8, ($o->id * 7) % 60);
                DB::table('transport_orders')->where('id', $o->id)->update([
                    'created_at' => $cree,
                    'created_date' => $cree->toDateString(),
                    'updated_at' => $cree,
                ]);
            });
        DB::statement("UPDATE transport_orders SET tracking_number = 'TRK-' || to_char(created_at, 'YYYY') || '-' || lpad(id::text, 5, '0')");

        (new CoherenceDesAffectations)();
        $this->call(ScenarioFretRetour::class);
    }
}
