<?php

namespace Tests\Feature;

use App\Support\IdentifiantEntreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Chaque pays de l'Union a son format de numero et envoie a VIES son
 * adresse a sa facon : code postal avant ou apres la localite, sur une ou
 * plusieurs lignes, en cyrillique ou en grec.
 */
class RegistreTvaParPaysTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Numero, nom et adresse tels que VIES les renvoie, puis ce que le
     * formulaire doit recevoir : [raison sociale, rue, code postal, localite].
     */
    private const CAS = [
        'AT' => ['ATU12345678', 'Muster GmbH', "Stephansplatz 1\nAT-1010 Wien", ['Muster GmbH', 'Stephansplatz 1', 'AT-1010', 'Wien']],
        'BE' => ['BE0202239951', 'Proximus SA', "Boulevard du Roi Albert II 27\n1030 Schaerbeek", ['Proximus SA', 'Boulevard du Roi Albert II 27', '1030', 'Schaerbeek']],
        'BG' => ['BG131468980', 'А1 България - ЕАД', 'ул. КУКУШ №1 обл.СОФИЯ, гр.СОФИЯ 1309', ['A1 Balgaria - EAD (А1 България - ЕАД)', 'ul. KUKUSH №1', '1309', 'SOFIA']],
        'CY' => ['CY12345678L', 'EXAMPLE LTD', "ARCH. MAKARIOU III 1\n1065 LEFKOSIA", ['EXAMPLE LTD', 'ARCH. MAKARIOU III 1', '1065', 'LEFKOSIA']],
        'CZ' => ['CZ12345678', 'Příklad s.r.o.', "Václavské náměstí 1\nPRAHA 1 - NOVÉ MĚSTO\n110 00 PRAHA 1", ['Příklad s.r.o.', 'Václavské náměstí 1', '110 00', 'PRAHA 1']],
        'DK' => ['DK12345678', 'EKSEMPEL A/S', "Vesterbrogade 1\n1620 København V", ['EKSEMPEL A/S', 'Vesterbrogade 1', '1620', 'København V']],
        'EE' => ['EE123456789', 'NÄIDIS OÜ', 'Narva mnt 1, Kesklinna linnaosa, Tallinn, Harju maakond, 10117', ['NÄIDIS OÜ', 'Narva mnt 1', '10117', 'Tallinn']],
        'EL' => ['EL123456789', 'ΠΑΡΑΔΕΙΓΜΑ Α.Ε.', "ΕΡΜΟΥ 10 \n10563 - ΑΘΗΝΑ", ['PARADEIGMA A.E. (ΠΑΡΑΔΕΙΓΜΑ Α.Ε.)', 'ERMOU 10', '10563', 'ATHINA']],
        'FI' => ['FI12345678', 'Esimerkki Oy', "Mannerheimintie 1\n00100 HELSINKI", ['Esimerkki Oy', 'Mannerheimintie 1', '00100', 'HELSINKI']],
        'FR' => ['FR40303265045', 'SA EXEMPLE', "12 RUE DE LA PAIX\n75002 PARIS", ['SA EXEMPLE', '12 RUE DE LA PAIX', '75002', 'PARIS']],
        'HR' => ['HR12345678901', 'PRIMJER D.O.O.', "ILICA 1\n10000 ZAGREB", ['PRIMJER D.O.O.', 'ILICA 1', '10000', 'ZAGREB']],
        'HU' => ['HU12345678', 'PÉLDA KFT.', '1051 BUDAPEST, NÁDOR UTCA 1.', ['PÉLDA KFT.', 'NÁDOR UTCA 1.', '1051', 'BUDAPEST']],
        'IE' => ['IE1234567T', 'EXAMPLE LIMITED', "1 GRAFTON STREET\nDUBLIN 2\nD02 X285", ['EXAMPLE LIMITED', '1 GRAFTON STREET', 'D02 X285', 'DUBLIN 2']],
        'IT' => ['IT12345678901', 'ESEMPIO SRL', "VIA ROMA 1 \n00184 ROMA RM\n", ['ESEMPIO SRL', 'VIA ROMA 1', '00184', 'ROMA RM']],
        'LT' => ['LT123456789', 'UAB PAVYZDYS', 'Gedimino pr. 1, LT-01103 Vilnius', ['UAB PAVYZDYS', 'Gedimino pr. 1', 'LT-01103', 'Vilnius']],
        'LU' => ['LU12345678', 'EXEMPLE SARL', "12, RUE DU FORT\nL-1234 LUXEMBOURG", ['EXEMPLE SARL', '12, RUE DU FORT', 'L-1234', 'LUXEMBOURG']],
        'LV' => ['LV12345678901', 'SIA PIEMĒRS', 'Brīvības iela 1, Rīga, LV-1050', ['SIA PIEMĒRS', 'Brīvības iela 1', 'LV-1050', 'Rīga']],
        'MT' => ['MT12345678', 'EXAMPLE LTD', "1, REPUBLIC STREET\nVALLETTA\nVLT 1117", ['EXAMPLE LTD', '1, REPUBLIC STREET', 'VLT 1117', 'VALLETTA']],
        'NL' => ['NL123456789B01', 'VOORBEELD B.V.', "DAMRAK 00001\n1012LG AMSTERDAM", ['VOORBEELD B.V.', 'DAMRAK 00001', '1012LG', 'AMSTERDAM']],
        'PL' => ['PL1234567890', 'PRZYKŁAD SP. Z O.O.', 'MARSZAŁKOWSKA 1, 00-950 WARSZAWA', ['PRZYKŁAD SP. Z O.O.', 'MARSZAŁKOWSKA 1', '00-950', 'WARSZAWA']],
        'PT' => ['PT123456789', 'EXEMPLO LDA', "RUA AUGUSTA N 1\nLISBOA\n1100-048 LISBOA", ['EXEMPLO LDA', 'RUA AUGUSTA N 1', '1100-048', 'LISBOA']],
        'RO' => ['RO1234567', 'EXEMPLU SRL', 'MUNICIPIUL BUCUREŞTI, SECTOR 1, STR. VICTORIEI, NR. 1', ['EXEMPLU SRL', 'STR. VICTORIEI NR. 1', '', 'BUCUREŞTI']],
        'SE' => ['SE123456789001', 'EXEMPEL AB', "BOX 1\n111 20 STOCKHOLM", ['EXEMPEL AB', 'BOX 1', '111 20', 'STOCKHOLM']],
        'SI' => ['SI12345678', 'PRIMER D.O.O.', "SLOVENSKA CESTA 1\n1000 LJUBLJANA", ['PRIMER D.O.O.', 'SLOVENSKA CESTA 1', '1000', 'LJUBLJANA']],
        'SK' => ['SK1234567890', 'Príklad s.r.o.', "Hlavná 1\n04001 Košice", ['Príklad s.r.o.', 'Hlavná 1', '04001', 'Košice']],
    ];

    public function test_chaque_pays_de_l_union_est_verifie_et_son_adresse_decoupee(): void
    {
        // 25 verifications : au-dela de la limite de debit du formulaire.
        $this->withoutMiddleware(ThrottleRequests::class);

        // VIES repond selon le pays demande dans l'adresse de l'appel.
        Http::fake(function ($requete) {
            preg_match('#/ms/([A-Z]{2})/vat/#', $requete->url(), $m);
            [, $nom, $adresse] = self::CAS[$m[1]];

            return Http::response(['isValid' => true, 'name' => $nom, 'address' => $adresse]);
        });

        foreach (self::CAS as $pays => [$numero, , , [$attenduNom, $rue, $cp, $ville]]) {
            $reponse = $this->getJson('/verification-tva?tva='.$numero)->assertOk();

            $this->assertSame('valide', $reponse->json('statut'), $pays);
            $this->assertSame([$attenduNom, $rue, $cp, $ville], [
                $reponse->json('nom'), $reponse->json('adresse.rue'), $reponse->json('adresse.code_postal'), $reponse->json('adresse.ville'),
            ], $pays);
        }
    }

    public function test_allemagne_et_espagne_ne_donnent_ni_nom_ni_adresse(): void
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['isValid' => true, 'name' => '---', 'address' => '---'])]);

        foreach (['DE123456789', 'ESB12345678'] as $numero) {
            $this->getJson('/verification-tva?tva='.$numero)
                ->assertJsonPath('statut', 'valide')
                ->assertJsonPath('nom', '')
                ->assertJsonPath('adresse.rue', '');
        }
    }

    public function test_chaque_exemple_affiche_respecte_son_format_et_une_faute_est_refusee(): void
    {
        Http::fake();

        foreach (IdentifiantEntreprise::EXEMPLES as $pays => $exemple) {
            $this->assertTrue(IdentifiantEntreprise::controleLocal($exemple), $pays.' '.$exemple);

            // Une lettre a la place d'un chiffre : refuse avant tout appel.
            $faux = substr_replace(preg_replace('/[^0-9A-Z]/', '', $exemple), 'Z', 4, 1);
            $this->assertFalse(IdentifiantEntreprise::controleLocal($faux), $pays.' '.$faux);
        }

        Http::assertNothingSent();
    }
}
