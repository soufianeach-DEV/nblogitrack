<?php

namespace Database\Seeders;

use App\Models\PageView;
use Illuminate\Database\Seeder;

/**
 * Trente jours de visites de demonstration pour l'ecran « Audience du
 * site » : semaine plus chargee que le week-end, provenances et
 * conversions plausibles. Tirage fixe : le meme jeu a chaque fois.
 */
final class AudienceSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(2026);
        PageView::query()->delete();

        $pages = ['/' => 40, '/tarifs' => 22, '/devis' => 18, '/p/conditions-generales' => 5, '/register' => 6, '/login' => 9];
        $sources = ['Direct' => 35, 'Google' => 40, 'LinkedIn' => 12, 'Bing' => 4, 'Facebook' => 4, 'Newsletter' => 5];
        $tirer = function (array $poids): string {
            $n = mt_rand(1, array_sum($poids));
            foreach ($poids as $valeur => $p) {
                if (($n -= $p) <= 0) {
                    return (string) $valeur;
                }
            }

            return (string) array_key_first($poids);
        };

        $lignes = [];
        for ($i = 29; $i >= 0; $i--) {
            $jour = today()->subDays($i);
            $arrivees = $jour->isWeekend() ? mt_rand(6, 14) : mt_rand(22, 48);

            for ($a = 0; $a < $arrivees; $a++) {
                $source = $tirer($sources);
                $langue = $tirer(['fr' => 55, 'nl' => 35, 'en' => 10]);
                $appareil = mt_rand(1, 100) <= 38 ? 'mobile' : 'ordinateur';
                $base = ['jour' => $jour->toDateString(), 'langue' => $langue, 'appareil' => $appareil, 'created_at' => $jour->copy()->setTime(mt_rand(7, 21), mt_rand(0, 59))];

                $lignes[] = $base + ['chemin' => $tirer($pages), 'entree' => true, 'source' => $source,
                    'support' => $source === 'Newsletter' ? 'email' : null, 'campagne' => $source === 'Newsletter' ? 'rentree-2026' : null, 'evenement' => null];

                // Quelques pages de plus pendant la visite.
                for ($p = mt_rand(0, 3); $p > 0; $p--) {
                    $lignes[] = $base + ['chemin' => $tirer($pages), 'entree' => false, 'source' => null, 'support' => null, 'campagne' => null, 'evenement' => null];
                }

                $conversion = mt_rand(1, 100);
                $evenement = $conversion <= 6 ? 'devis' : ($conversion <= 9 ? 'inscription' : ($conversion <= 20 ? 'simulation' : null));
                if ($evenement) {
                    $lignes[] = $base + ['chemin' => $evenement === 'devis' ? '/devis' : ($evenement === 'inscription' ? '/register' : '/tarifs'),
                        'entree' => false, 'source' => null, 'support' => null, 'campagne' => null, 'evenement' => $evenement];
                }
            }
        }

        foreach (array_chunk($lignes, 500) as $lot) {
            PageView::insert($lot);
        }
    }
}
