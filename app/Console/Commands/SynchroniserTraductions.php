<?php

namespace App\Console\Commands;

use App\Models\Translation;
use App\Support\Traductions;
use Database\Seeders\TranslationSeeder;
use Illuminate\Console\Command;

/**
 * Met le dictionnaire de la base a jour avec celui du code : ajoute les
 * nouvelles cles, met a jour les textes qui n'ont pas ete retouches a la
 * main, puis vide le cache. Lancee automatiquement apres chaque
 * « php artisan migrate » : un deploiement ne laisse plus de texte en
 * francais dans les autres langues.
 */
class SynchroniserTraductions extends Command
{
    protected $signature = 'traductions:synchroniser';

    protected $description = 'Ajoute les nouvelles traductions du code et vide leur cache';

    public function handle(): int
    {
        $existantes = Translation::all()->keyBy('cle');
        $ajoutees = 0;
        $misesAJour = 0;

        foreach (TranslationSeeder::textes() as $groupe => $cles) {
            foreach ($cles as $cle => [$fr, $nl, $en]) {
                $complete = $groupe.'.'.$cle;
                $ligne = $existantes->get($complete);

                if ($ligne === null) {
                    Translation::create(['cle' => $complete, 'groupe' => $groupe, 'fr' => $fr, 'nl' => $nl, 'en' => $en]);
                    $ajoutees++;
                } elseif (! $ligne->modifiee_a_la_main && [$ligne->fr, $ligne->nl, $ligne->en] !== [$fr, $nl, $en]) {
                    $ligne->update(['fr' => $fr, 'nl' => $nl, 'en' => $en]);
                    $misesAJour++;
                }
            }
        }

        Traductions::oublier();

        $this->line(sprintf('  Traductions : %d ajoutee(s), %d mise(s) a jour.', $ajoutees, $misesAJour));

        return self::SUCCESS;
    }
}
