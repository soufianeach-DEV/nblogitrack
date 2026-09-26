<?php

namespace App\Console\Commands;

use App\Support\Geocodeur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class ImportPostalCodes extends Command
{
    protected $signature = 'geo:import-postal-codes';

    protected $description = 'Importe les codes postaux européens depuis GeoNames (licence CC-BY)';

    // Tous les pays desservis (App\Support\Pays).
    private const PAYS = ['AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

    // GeoNames publie a part la liste complete de certains pays : tous les
    // codes du Royaume-Uni (SW1A 1AA, pas seulement SW1A) et des Pays-Bas
    // (1012 AB, pas seulement 1012).
    private const COMPLETS = ['GB', 'NL'];

    public function handle(): int
    {
        DB::table('postal_codes')->truncate();

        $total = 0;

        foreach (self::PAYS as $pays) {
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
                } else {
                    $this->warn("  {$pays} : rien d'importé (HTTP {$statut}).");
                }

                continue;
            }

            $total += $inseres;
            $this->info("  {$pays} : {$inseres} codes importés.");
        }

        $this->info("Terminé : {$total} codes postaux importés.");

        return self::SUCCESS;
    }

    /**
     * Telecharge une archive GeoNames et insere ses lignes.
     *
     * @return array{0: int, 1: int|null} lignes inserees, statut HTTP
     */
    private function importer(string $pays, string $fichier): array
    {
        $reponse = Http::timeout(600)->get("https://download.geonames.org/export/zip/{$fichier}");

        if (! $reponse->ok()) {
            return [0, $reponse->status()];
        }

        $zipPath = storage_path("app/geonames_{$pays}.zip");
        file_put_contents($zipPath, $reponse->body());

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            @unlink($zipPath);

            return [0, $reponse->status()];
        }

        // Le fichier texte de l'archive (PAYS.txt ou PAYS_full.txt).
        $contenu = false;
        for ($i = 0; $i < $zip->numFiles && $contenu === false; $i++) {
            $nom = (string) $zip->getNameIndex($i);
            if (str_starts_with($nom, $pays) && str_ends_with($nom, '.txt')) {
                $contenu = $zip->getFromIndex($i);
            }
        }
        $zip->close();
        @unlink($zipPath);

        if ($contenu === false) {
            return [0, $reponse->status()];
        }

        $lot = [];
        $inseres = 0;

        foreach (explode("\n", $contenu) as $ligne) {
            $champs = explode("\t", rtrim($ligne, "\r"));
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

        return [$inseres, $reponse->status()];
    }
}
