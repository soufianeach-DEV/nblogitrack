<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class ImportPostalCodes extends Command
{
    protected $signature = 'geo:import-postal-codes';

    protected $description = 'Importe les codes postaux européens depuis GeoNames (licence CC-BY)';

    private const PAYS = ['AT', 'BE', 'BG', 'CH', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

    public function handle(): int
    {
        DB::table('postal_codes')->truncate();

        $total = 0;

        foreach (self::PAYS as $pays) {
            $this->line("Téléchargement {$pays}…");

            $reponse = Http::timeout(120)->get("https://download.geonames.org/export/zip/{$pays}.zip");
            if (! $reponse->ok()) {
                $this->warn("  {$pays} : téléchargement impossible (HTTP {$reponse->status()}), ignoré.");

                continue;
            }

            $zipPath = storage_path("app/geonames_{$pays}.zip");
            file_put_contents($zipPath, $reponse->body());

            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                $this->warn("  {$pays} : archive illisible, ignoré.");
                @unlink($zipPath);

                continue;
            }

            $contenu = $zip->getFromName("{$pays}.txt");
            $zip->close();
            @unlink($zipPath);

            if ($contenu === false) {
                $this->warn("  {$pays} : fichier absent de l'archive, ignoré.");

                continue;
            }

            $lot = [];
            $inseres = 0;

            foreach (explode("\n", $contenu) as $ligne) {
                $champs = explode("\t", $ligne);
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

            $total += $inseres;
            $this->info("  {$pays} : {$inseres} codes importés.");
        }

        $this->info("Terminé : {$total} codes postaux importés.");

        return self::SUCCESS;
    }
}
