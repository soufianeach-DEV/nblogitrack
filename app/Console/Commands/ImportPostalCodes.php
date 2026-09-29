<?php

namespace App\Console\Commands;

use App\Support\Geocodeur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class ImportPostalCodes extends Command
{
    protected $signature = 'geo:import-postal-codes
        {--si-absents : N\'importe que si aucun import complet n\'a eu lieu (demarrage du serveur)}';

    // Pose en fin d'import complet, et un par pays (MARQUEUR.BE) des qu'un
    // pays est fait. Ils vivent dans le cache en base : un rechargement de
    // la base (migrate:fresh) les efface avec les codes. Un import coupe
    // (serveur mis en veille) reprend au demarrage suivant la ou il
    // s'etait arrete.
    public const MARQUEUR = 'codes_postaux.importes';

    protected $description = 'Importe les codes postaux européens depuis GeoNames (licence CC-BY)';

    // Tous les pays desservis (App\Support\Pays).
    private const PAYS = ['AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

    // GeoNames publie a part la liste complete de certains pays : tous les
    // codes du Royaume-Uni (SW1A 1AA, pas seulement SW1A) et des Pays-Bas
    // (1012 AB, pas seulement 1012).
    private const COMPLETS = ['GB', 'NL'];

    public function handle(): int
    {
        if (! $this->option('si-absents')) {
            return $this->toutImporter();
        }

        if (Cache::get(self::MARQUEUR) && DB::table('postal_codes')->exists()) {
            $this->line('Codes postaux deja importes.');

            return self::SUCCESS;
        }

        // Lors d'un deploiement, l'ancien et le nouveau conteneur demarrent
        // parfois ensemble : un seul importe. Le verrou d'un conteneur
        // arrete en plein import expire vite.
        $verrou = Cache::lock('codes_postaux.import', 15 * 60);

        if (! $verrou->get()) {
            $this->line('Import deja en cours.');

            return self::SUCCESS;
        }

        try {
            return $this->toutImporter(reprendre: true);
        } finally {
            $verrou->release();
        }
    }

    private function toutImporter(bool $reprendre = false): int
    {
        Cache::forget(self::MARQUEUR);

        if (! $reprendre) {
            Cache::deleteMultiple(array_map(fn ($pays) => self::MARQUEUR.'.'.$pays, self::PAYS));
            DB::table('postal_codes')->truncate();
        }

        $total = 0;
        $complet = true;

        foreach (self::PAYS as $pays) {
            // Pays deja fait : ses codes sont la, ou GeoNames ne le publie pas.
            $fait = Cache::get(self::MARQUEUR.'.'.$pays);
            if ($reprendre && $fait !== null && ($fait === 0 || DB::table('postal_codes')->where('country_code', $pays)->exists())) {
                continue;
            }

            // Les lignes d'un pays a moitie importe partent avant de recommencer.
            DB::table('postal_codes')->where('country_code', $pays)->delete();

            $this->line("Téléchargement {$pays}…");

            // La liste complete d'abord ; illisible ou vide, la liste habituelle.
            $fichiers = in_array($pays, self::COMPLETS, true) ? ["{$pays}_full.csv.zip", "{$pays}.zip"] : ["{$pays}.zip"];
            $inseres = 0;
            $statut = null;

            foreach ($fichiers as $fichier) {
                [$inseres, $statut] = $this->importer($pays, $fichier);

                if ($inseres > 0) {
                    break;
                }
            }

            if ($inseres === 0) {
                if ($statut === 404 && Geocodeur::couvre($pays)) {
                    $this->line("  {$pays} : GeoNames ne publie pas ce pays ; ses localités se vérifient en ligne (Photon).");
                    Cache::forever(self::MARQUEUR.'.'.$pays, 0);
                } else {
                    $this->warn("  {$pays} : rien d'importé".($statut !== null ? " (HTTP {$statut})" : '').'.');
                    $complet = false;
                }

                continue;
            }

            $total += $inseres;
            Cache::forever(self::MARQUEUR.'.'.$pays, $inseres);
            $this->info("  {$pays} : {$inseres} codes importés.");
        }

        $this->info("Terminé : {$total} codes postaux importés.");

        // Un pays en echec (GeoNames injoignable) : le prochain demarrage
        // reessaie ce pays.
        if ($complet) {
            Cache::forever(self::MARQUEUR, true);
        }

        return self::SUCCESS;
    }

    /**
     * Telecharge une archive GeoNames et insere ses lignes.
     *
     * @return array{0: int, 1: int|null} lignes inserees, statut HTTP
     */
    private function importer(string $pays, string $fichier): array
    {
        // Archive ecrite sur disque et texte lu ligne a ligne : la liste
        // complete du Royaume-Uni (1,7 million de lignes) tiendrait mal en
        // memoire sur un petit serveur.
        $zipPath = storage_path("app/geonames_{$pays}.zip");
        try {
            $reponse = Http::timeout(600)->sink($zipPath)->get("https://download.geonames.org/export/zip/{$fichier}");
        } catch (\Throwable $e) {
            // Reseau coupe : ce pays est retente au prochain passage.
            @unlink($zipPath);
            $this->warn("  {$pays} : {$e->getMessage()}");

            return [0, null];
        }

        if (! $reponse->ok()) {
            @unlink($zipPath);

            return [0, $reponse->status()];
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            @unlink($zipPath);

            return [0, $reponse->status()];
        }

        // Le fichier texte de l'archive (PAYS.txt ou PAYS_full.txt).
        $flux = false;
        for ($i = 0; $i < $zip->numFiles && $flux === false; $i++) {
            $nom = (string) $zip->getNameIndex($i);
            if (str_starts_with($nom, $pays) && str_ends_with($nom, '.txt')) {
                $flux = $zip->getStream($nom);
            }
        }

        if ($flux === false) {
            $zip->close();
            @unlink($zipPath);

            return [0, $reponse->status()];
        }

        $lot = [];
        $inseres = 0;

        while (($ligne = fgets($flux)) !== false) {
            $champs = explode("\t", rtrim($ligne, "\r\n"));
            if (count($champs) < 11 || $champs[1] === '' || $champs[2] === '' || $champs[9] === '' || $champs[10] === '') {
                continue;
            }

            $lot[] = [
                'country_code' => $pays,
                'code' => mb_substr(trim($champs[1]), 0, 16),
                'city' => mb_substr(trim($champs[2]), 0, 180),
                'region' => $champs[3] !== '' ? mb_substr(trim($champs[3]), 0, 100) : null,
                'lat' => (float) $champs[9],
                'lng' => (float) $champs[10],
            ];

            if (count($lot) === 500) {
                DB::table('postal_codes')->insert($lot);
                $inseres += count($lot);
                $lot = [];
            }
        }

        if ($lot !== []) {
            DB::table('postal_codes')->insert($lot);
            $inseres += count($lot);
        }

        fclose($flux);
        $zip->close();
        @unlink($zipPath);

        return [$inseres, $reponse->status()];
    }
}
