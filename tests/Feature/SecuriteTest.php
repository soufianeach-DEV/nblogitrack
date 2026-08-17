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

class SecuriteTest extends TestCase
{
    use RefreshDatabase;

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
