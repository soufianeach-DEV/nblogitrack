<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
