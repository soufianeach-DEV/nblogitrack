<?php

namespace Tests\Feature;

use App\Console\Commands\ImportPostalCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

/** Import GeoNames : tous les pays desservis, listes completes quand elles existent. */
class ImportCodesPostauxTest extends TestCase
{
    use RefreshDatabase;

    private function archive(string $nom, string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'geo');
        $zip = new ZipArchive;
        $zip->open($chemin, ZipArchive::OVERWRITE);
        $zip->addFromString($nom, $contenu);
        $zip->close();

        return (string) file_get_contents($chemin);
    }

    private function ligne(string $pays, string $cp, string $ville): string
    {
        return implode("\t", [$pays, $cp, $ville, 'Region', '', '', '', '', '', '51.5', '-0.12', '6'])."\n";
    }

    public function test_les_listes_completes_et_tous_les_pays_sont_importes(): void
    {
        Http::fake(function ($requete) {
            $fichier = basename($requete->url());

            return match ($fichier) {
                'GB_full.csv.zip' => Http::response($this->archive('GB_full.txt', $this->ligne('GB', 'SW1A 1AA', 'London').$this->ligne('GB', 'SW1A 2AA', 'London'))),
                // Liste complete absente : la liste habituelle prend le relais.
                'NL_full.csv.zip' => Http::response('', 404),
                'NL.zip' => Http::response($this->archive('NL.txt', $this->ligne('NL', '1012', 'Amsterdam'))),
                'MT.zip' => Http::response($this->archive('MT.txt', $this->ligne('MT', 'VLT 1010', 'Valletta'))),
                default => Http::response('', 404),
            };
        });

        $this->artisan('geo:import-postal-codes')->assertSuccessful();

        $this->assertSame(['SW1A 1AA', 'SW1A 2AA'], DB::table('postal_codes')->where('country_code', 'GB')->orderBy('code')->pluck('code')->all());
        $this->assertSame(1, DB::table('postal_codes')->where('country_code', 'NL')->count());
        $this->assertSame(1, DB::table('postal_codes')->where('country_code', 'MT')->count());
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/CY.zip'));
    }

    // Un code par pays ; GeoNames ne publie pas la Grece.
    private function fauxGeoNames(): void
    {
        Http::fake(function ($requete) {
            $fichier = basename($requete->url());

            return preg_match('/^([A-Z]{2})\.zip$/', $fichier, $m) && $m[1] !== 'GR'
                ? Http::response($this->archive("{$m[1]}.txt", $this->ligne($m[1], '1000', 'Ville')))
                : Http::response('', 404);
        });
    }

    public function test_au_demarrage_l_import_n_a_lieu_qu_une_fois(): void
    {
        $this->fauxGeoNames();

        $this->artisan('geo:import-postal-codes --si-absents')->assertSuccessful();
        $this->assertSame(29, DB::table('postal_codes')->count());
        $this->assertTrue(Cache::get(ImportPostalCodes::MARQUEUR));

        Http::fake(fn () => throw new \RuntimeException('GeoNames ne doit pas etre rappele'));
        $this->artisan('geo:import-postal-codes --si-absents')->assertSuccessful();
        $this->assertSame(29, DB::table('postal_codes')->count());
    }

    public function test_un_import_interrompu_reprend_la_ou_il_s_etait_arrete(): void
    {
        // Autriche faite ; Belgique coupee en plein import (pas de marqueur).
        DB::table('postal_codes')->insert([
            ['country_code' => 'AT', 'code' => '1010', 'city' => 'Wien', 'lat' => 48, 'lng' => 16],
            ['country_code' => 'BE', 'code' => '9999', 'city' => 'Partiel', 'lat' => 50, 'lng' => 4],
        ]);
        Cache::forever(ImportPostalCodes::MARQUEUR.'.AT', 1);
        $this->fauxGeoNames();

        $this->artisan('geo:import-postal-codes --si-absents')->assertSuccessful();

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/AT.zip'));
        $this->assertSame(['1010'], DB::table('postal_codes')->where('country_code', 'AT')->pluck('code')->all());
        $this->assertSame(['1000'], DB::table('postal_codes')->where('country_code', 'BE')->pluck('code')->all());
        $this->assertTrue(Cache::get(ImportPostalCodes::MARQUEUR));
    }

    public function test_une_base_rechargee_reimporte_malgre_le_marqueur(): void
    {
        Cache::forever(ImportPostalCodes::MARQUEUR, true);
        $this->fauxGeoNames();

        $this->artisan('geo:import-postal-codes --si-absents')->assertSuccessful();

        $this->assertSame(29, DB::table('postal_codes')->count());
    }

    public function test_geonames_injoignable_ne_pose_pas_le_marqueur(): void
    {
        Http::fake(fn () => Http::response('', 503));

        $this->artisan('geo:import-postal-codes --si-absents')->assertSuccessful();

        $this->assertNull(Cache::get(ImportPostalCodes::MARQUEUR));
    }

    public function test_sans_listes_completes_la_liste_habituelle_suffit(): void
    {
        $this->fauxGeoNames();

        $this->artisan('geo:import-postal-codes --sans-listes-completes')->assertSuccessful();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '_full'));
        $this->assertSame(1, DB::table('postal_codes')->where('country_code', 'GB')->count());
    }
}
