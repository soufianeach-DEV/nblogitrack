<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Les correctifs de l'audit du 15 aout, et leur non-retour.
 *
 * Chacun de ces tests correspond a un defaut reel, mesure sur
 * l'application avant correction. Ils ne verifient pas une intention :
 * ils rejouent l'attaque.
 */
class SecuriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le premier des deux constats critiques.
     *
     * Le controle du compte ne se faisait qu'a la connexion. Un employe
     * dont on coupait l'acces continuait a travailler jusqu'a ce qu'il
     * se deconnecte de lui-meme : c'est le scenario du depart
     * conflictuel, et la desactivation ne servait a rien.
     */
    public function test_desactiver_un_compte_coupe_la_session_en_cours(): void
    {
        $planificateur = User::factory()->planificateur()->create();

        $this->actingAs($planificateur)
            ->get(route('planning.index'))
            ->assertOk();

        $planificateur->update(['is_active' => false]);

        $this->get(route('planning.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_un_compte_desactive_ne_se_reconnecte_pas(): void
    {
        $utilisateur = User::factory()->desactive()->create();

        $this->post(route('login'), [
            'email' => $utilisateur->email,
            'password' => 'password',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    }

    /**
     * Deux depots simultanes lisaient le meme plus grand identifiant et
     * fabriquaient le meme numero : le second s'ecrasait sur la
     * contrainte d'unicite, et le client voyait une erreur serveur pour
     * une commande pourtant valable.
     *
     * Le verrou consultatif serialise l'attribution. Un test ne peut pas
     * jouer deux processus, mais il peut verifier que cent numeros
     * d'affilee restent distincts et que la colonne les refuse en double.
     */
    public function test_les_numeros_de_suivi_restent_uniques(): void
    {
        $client = Client::factory()->create();

        $numeros = [];
        foreach (range(1, 30) as $i) {
            $numeros[] = TransportOrder::deposer([
                'client_id' => $client->id,
                'created_date' => now(),
                'pickup_address' => 'Rue Neuve 43, 3500 Hasselt',
                'delivery_address' => 'Avenue Louise 200, 1000 Bruxelles',
                'weight' => 1000,
                'goods_type' => TransportOrder::MARCHANDISES[0],
                'status' => 'PENDING',
                'priority' => 'NORMAL',
            ])->tracking_number;
        }

        $this->assertCount(30, array_unique($numeros));
    }

    public function test_la_base_refuse_deux_fois_le_meme_numero(): void
    {
        $client = Client::factory()->create();
        $ordre = TransportOrder::factory()->create(['client_id' => $client->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        TransportOrder::factory()->create([
            'client_id' => $client->id,
            'tracking_number' => $ordre->tracking_number,
        ]);
    }

    /**
     * Le nom de rue part dans une expression reguliere chez Overpass.
     * Retirer les guillemets empechait de sortir de la chaine mais
     * laissait passer tout le reste du langage : mesure faite, le motif
     * point-etoile repete six fois occupait le service tiers pendant
     * dix-neuf secondes et remontait cent soixante-cinq rues.
     */
    public function test_un_nom_de_rue_ne_devient_pas_une_expression_reguliere(): void
    {
        $filtre = fn (string $rue) => preg_replace('/[^\p{L}\p{N} \'\-]/u', ' ', $rue);

        foreach (['(a+)+$', '.*.*.*.*x', '^Avenue|Boulevard$', 'Rue "test" \\ suite'] as $hostile) {
            $this->assertDoesNotMatchRegularExpression('/[.*+?()\[\]{}|^$\\\\"]/', $filtre($hostile),
                'Le motif « '.$hostile.' » traverse encore le filtre.');
        }

        foreach (['Avenue Louise', 'Rue Jean-Baptiste Colyns', "Rue d'Alsace-Lorraine", 'Grote Markt', "Rue de l'Étuve"] as $vraie) {
            $this->assertSame($vraie, $filtre($vraie), 'Le nom « '.$vraie.' » a ete abime.');
        }
    }

    /**
     * La recherche du bandeau cloisonne dans le serveur, pas dans
     * l'affichage : un client n'y trouve que ses propres expeditions.
     */
    public function test_la_recherche_ne_traverse_pas_les_entreprises(): void
    {
        $sien = Client::factory()->create();
        $autre = Client::factory()->create();

        $aMoi = TransportOrder::factory()->create([
            'client_id' => $sien->id,
            'delivery_address' => 'Quai des Usines 1, 4000 Liège',
        ]);
        TransportOrder::factory()->create([
            'client_id' => $autre->id,
            'delivery_address' => 'Quai des Usines 2, 4000 Liège',
        ]);

        $reponse = $this->actingAs(User::find($sien->id))
            ->getJson(route('recherche.suggestions', ['q' => 'Liège']));

        $reponse->assertOk();

        $texte = json_encode($reponse->json());

        $this->assertStringContainsString($aMoi->tracking_number, $texte);
        $this->assertSame(1, substr_count($texte, 'TRK-'));
    }

    /**
     * Le journal doit repondre « qui a fait quoi, quand, depuis ou » :
     * c'est l'exigence A10, et elle demande les quatre.
     */
    public function test_le_journal_retient_les_quatre_informations(): void
    {
        $client = Client::factory()->create();

        ActivityLog::record('essai.controle', 'Une action de controle');

        $ligne = DB::table('activity_logs')->latest('id')->first();

        $this->assertNotNull($ligne->created_at);
        $this->assertSame('essai.controle', $ligne->action);
        $this->assertArrayHasKey('ip_address', (array) $ligne);
    }
}
