<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tchequie, Finlande, Pologne et Roumanie : VIES confirme le numero, le
 * registre national gratuit ajoute la forme juridique et le secteur (et,
 * pour la Roumanie, l'adresse deja decoupee).
 */
class RegistresNationauxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** VIES repond « actif » avec le nom et l'adresse donnes. */
    private function vies(string $nom, string $adresse): array
    {
        return ['isValid' => true, 'name' => $nom, 'address' => $adresse];
    }

    public function test_ares_donne_la_forme_et_le_secteur_tcheques(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response($this->vies('Škoda Auto a.s.', "tř. Václava Klementa 869\n293 01 MLADÁ BOLESLAV 1")),
            'ares.gov.cz/*' => Http::response(['ico' => '00177041', 'pravniForma' => '121', 'czNace' => ['29100', '45110']]),
        ]);

        $this->getJson('/verification-tva?tva=CZ00177041')
            ->assertJsonPath('entreprise.forme_juridique', 'a.s.')
            ->assertJsonPath('entreprise.secteur', 'Industrie automobile')
            ->assertJsonMissingPath('entreprise.forme_deduite');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ekonomicke-subjekty/00177041'));
    }

    public function test_prh_donne_la_forme_et_le_secteur_finlandais(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response($this->vies('Nokia Oyj', "PL 226\n00045 NOKIA GROUP")),
            'avoindata.prh.fi/*' => Http::response(['companies' => [[
                'companyForms' => [['type' => '17', 'descriptions' => [['languageCode' => '1', 'description' => 'Julkinen osakeyhtiö']]]],
                'mainBusinessLine' => ['type' => '26110'],
            ]]]),
        ]);

        $this->getJson('/verification-tva?tva=FI01120389')
            ->assertJsonPath('entreprise.forme_juridique', 'Oyj')
            ->assertJsonPath('entreprise.secteur', 'Électronique et informatique (fabrication)');

        // Le numero de TVA devient l'identifiant finlandais 0112038-9.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'businessId=0112038-9'));
    }

    public function test_la_liste_blanche_et_le_krs_donnent_la_forme_et_le_secteur_polonais(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response($this->vies('ORLEN SPÓŁKA AKCYJNA', "CHEMIKÓW 7\n09-411 PŁOCK")),
            'wl-api.mf.gov.pl/*' => Http::response(['result' => ['subject' => ['name' => 'ORLEN SPÓŁKA AKCYJNA', 'krs' => '0000028860']]]),
            'api-krs.ms.gov.pl/*' => Http::response(['odpis' => ['dane' => [
                'dzial1' => ['danePodmiotu' => ['formaPrawna' => 'SPÓŁKA AKCYJNA']],
                'dzial3' => ['przedmiotDzialalnosci' => ['przedmiotPrzewazajacejDzialalnosci' => [['kodDzial' => '19', 'kodKlasa' => '20', 'kodPodklasa' => 'Z']]]],
            ]]]),
        ]);

        $this->getJson('/verification-tva?tva=PL7740001454')
            ->assertJsonPath('entreprise.forme_juridique', 'S.A.')
            ->assertJsonPath('entreprise.secteur', 'Raffinage du pétrole');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/OdpisAktualny/0000028860'));
    }

    public function test_l_anaf_donne_la_forme_le_secteur_et_l_adresse_roumains(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response($this->vies('AUTOMOBILE-DACIA SA', 'LOC. MIOVENI - ORŞ. MIOVENI 115400 STR. UZINEI Nr. 1')),
            'webservicesp.anaf.ro/*' => Http::response(['found' => [[
                'date_generale' => ['cui' => 160796, 'denumire' => 'AUTOMOBILE-DACIA SA', 'forma_juridica' => 'SOCIETATE COMERCIALĂ PE ACŢIUNI', 'cod_CAEN' => '2910'],
                'adresa_sediu_social' => ['sdenumire_Strada' => 'Str. Uzinei', 'snumar_Strada' => '1', 'sdenumire_Localitate' => 'Oraş Mioveni', 'scod_Postal' => '115400'],
            ]], 'notFound' => []]),
        ]);

        $this->getJson('/verification-tva?tva=RO160796')
            ->assertJsonPath('entreprise.forme_juridique', 'SA')
            ->assertJsonPath('entreprise.secteur', 'Industrie automobile')
            ->assertJsonPath('adresse.rue', 'Str. Uzinei Nr. 1')
            ->assertJsonPath('adresse.code_postal', '115400')
            ->assertJsonPath('adresse.ville', 'Mioveni');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'anaf.ro') && $r->method() === 'POST' && $r->data()[0]['cui'] === 160796);
    }

    public function test_un_registre_national_en_panne_ne_gene_pas_la_verification(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response($this->vies('Škoda Auto a.s.', "tř. Václava Klementa 869\n293 01 MLADÁ BOLESLAV 1")),
            'ares.gov.cz/*' => Http::response('Service Unavailable', 503),
        ]);

        // Le numero reste actif ; la forme se lit alors dans le nom.
        $this->getJson('/verification-tva?tva=CZ00177041')
            ->assertJsonPath('statut', 'valide')
            ->assertJsonPath('adresse.code_postal', '293 01')
            ->assertJsonPath('entreprise.forme_juridique', 'a.s.')
            ->assertJsonPath('entreprise.forme_deduite', true);
    }
}
