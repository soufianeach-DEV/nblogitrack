<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Supplements poses par le planificateur sur des transports livres :
 * attente au quai, manutention, peage non prevu. Ils passent avant la
 * facturation, qui les reprend sur la facture du mois.
 */
class SupplementSeeder extends Seeder
{
    private const MOTIFS = [
        ['Attente au quai (1 h)', 45.00],
        ['Attente au quai (2 h)', 90.00],
        ['Manutention à la livraison', 60.00],
        ['Rendez-vous hors horaires', 75.00],
        ['Péage non prévu', 28.50],
        ['Hayon demandé sur place', 40.00],
    ];

    public function run(): void
    {
        $planificateur = DB::table('users')->where('role', 'PLANNER')->orderBy('id')->value('id');

        $livrees = DB::table('transport_orders')
            ->where('status', 'DELIVERED')
            ->orderBy('id')
            ->get(['id', 'delivered_at', 'actual_delivery_date']);

        $lignes = [];

        foreach ($livrees as $rang => $ordre) {
            // Environ trois livraisons sur quatre ont un supplement, et une
            // sur trois en a deux.
            if ($rang % 4 === 3) {
                continue;
            }

            $date = $ordre->delivered_at ?? $ordre->actual_delivery_date;

            foreach ($rang % 3 === 0 ? [0, 1] : [0] as $n) {
                [$libelle, $montant] = self::MOTIFS[($rang + $n * 2) % count(self::MOTIFS)];
                $lignes[] = [
                    'transport_order_id' => $ordre->id,
                    'label' => $libelle,
                    'amount' => $montant,
                    'created_by' => $planificateur,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
        }

        foreach (array_chunk($lignes, 500) as $paquet) {
            DB::table('order_charges')->insert($paquet);
        }
    }
}
