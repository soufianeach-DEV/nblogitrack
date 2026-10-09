<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Encaissement;
use App\Support\Facturier;
use App\Support\PaiementStripe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * La notification signee de Stripe : paiement immediat, paiement differe
 * (virement SEPA), session encore impayee, montant ou facture incoherents.
 */
class PaiementStripeTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $facture;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.secret' => 'sk_test_essai',
            'services.stripe.webhook_secret' => 'whsec_essai',
        ]);

        $this->travelTo('2026-04-10 10:00');
        $client = Client::factory()->create();
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id, 'actual_delivery_date' => '2026-03-10', 'estimated_cost' => 1000,
        ]);
        $this->facture = app(Facturier::class)->facturer()->first();
    }

    private function notifier(string $type, array $session): TestResponse
    {
        $corps = json_encode([
            'id' => 'evt_'.uniqid(), 'object' => 'event', 'type' => $type, 'livemode' => false,
            'data' => ['object' => array_merge([
                'id' => 'cs_test_1', 'object' => 'checkout.session', 'livemode' => false,
                'client_reference_id' => (string) $this->facture->id,
                'amount_total' => (int) round((float) $this->facture->amount_incl_tax * 100),
                'currency' => 'eur', 'payment_status' => 'paid',
            ], $session)],
        ]);
        $t = time();

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$corps, 'whsec_essai'),
        ], $corps);
    }

    public function test_un_paiement_par_carte_solde_la_facture(): void
    {
        $this->notifier('checkout.session.completed', [])->assertOk();

        $this->assertSame('PAID', $this->facture->fresh()->status);
        $this->assertSame('STRIPE', Payment::first()->method);
        $this->assertSame('cs_test_1', Payment::first()->reference);
    }

    public function test_un_virement_sepa_n_est_compte_qu_a_son_arrivee(): void
    {
        $this->notifier('checkout.session.completed', ['payment_status' => 'unpaid'])->assertOk();
        $this->assertSame('SENT', $this->facture->fresh()->status);
        $this->assertSame(0, Payment::count());

        $this->notifier('checkout.session.async_payment_succeeded', [])->assertOk();
        $this->assertSame('PAID', $this->facture->fresh()->status);
    }

    public function test_la_meme_notification_recue_deux_fois_ne_compte_qu_une_fois(): void
    {
        $this->notifier('checkout.session.completed', [])->assertOk();
        $this->notifier('checkout.session.completed', [])->assertOk();

        $this->assertSame(1, Payment::count());
    }

    public function test_un_paiement_partiel_en_ligne_laisse_le_solde(): void
    {
        $this->notifier('checkout.session.completed', ['amount_total' => 50000])->assertOk();

        $this->assertSame('SENT', $this->facture->fresh()->status);
        $this->assertEqualsWithDelta(710.0, $this->facture->fresh()->solde(), 0.001);
    }

    public function test_une_session_d_une_autre_facture_ou_en_dollars_est_refusee(): void
    {
        $this->notifier('checkout.session.completed', ['currency' => 'usd'])->assertOk();
        $this->notifier('checkout.session.completed', ['id' => 'cs_test_2', 'amount_total' => 999999999])->assertOk();

        $this->assertSame(0, Payment::count());
    }

    public function test_une_signature_fausse_est_refusee(): void
    {
        $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=1,v1=faux',
        ], '{}')->assertStatus(400);
    }

    /** Un double de l'API Stripe : sessions creees, relues, expirees. */
    private function fauxStripe(): object
    {
        $sessions = new class
        {
            public array $store = [];

            public array $expirees = [];

            public function create(array $p): object
            {
                $id = 'cs_test_'.(count($this->store) + 1);

                return $this->store[$id] = (object) [
                    'id' => $id, 'status' => 'open', 'payment_status' => 'unpaid', 'livemode' => false,
                    'client_reference_id' => $p['client_reference_id'], 'currency' => 'eur',
                    'amount_total' => $p['line_items'][0]['price_data']['unit_amount'],
                    'url' => 'https://checkout.test/'.$id,
                ];
            }

            public function retrieve(string $id): object
            {
                return $this->store[$id];
            }

            public function expire(string $id): object
            {
                $this->expirees[] = $id;
                $this->store[$id]->status = 'expired';

                return $this->store[$id];
            }
        };

        $client = (object) ['checkout' => (object) ['sessions' => $sessions]];
        $this->app->instance('stripe.client', $client);

        return $sessions;
    }

    public function test_deux_onglets_reprennent_la_meme_session(): void
    {
        $sessions = $this->fauxStripe();
        $compte = $this->facture->client->compte();

        $premier = $this->actingAs($compte)->post(route('payments.payer', $this->facture), [], ['X-Inertia' => 'true']);
        $second = $this->actingAs($compte)->post(route('payments.payer', $this->facture), [], ['X-Inertia' => 'true']);

        $this->assertCount(1, $sessions->store);
        $this->assertSame($premier->headers->get('X-Inertia-Location'), $second->headers->get('X-Inertia-Location'));
    }

    public function test_un_paiement_partiel_entre_temps_ferme_l_ancienne_session(): void
    {
        $sessions = $this->fauxStripe();
        $compte = $this->facture->client->compte();

        $this->actingAs($compte)->post(route('payments.payer', $this->facture), [], ['X-Inertia' => 'true']);
        Encaissement::enregistrer($this->facture, 100, now(), 'TRANSFER');
        $this->actingAs($compte)->post(route('payments.payer', $this->facture->fresh()), [], ['X-Inertia' => 'true']);

        $this->assertSame(['cs_test_1'], $sessions->expirees);
        $this->assertSame(111000, $sessions->store['cs_test_2']->amount_total);
    }

    public function test_un_virement_en_cours_bloque_un_second_paiement(): void
    {
        $this->fauxStripe();
        $this->notifier('checkout.session.completed', ['payment_status' => 'unpaid', 'status' => 'complete'])->assertOk();

        $this->assertNotNull($this->facture->fresh()->online_payment_pending_at);

        $this->actingAs($this->facture->client->compte())
            ->post(route('payments.payer', $this->facture))
            ->assertSessionHas('error');

        $this->notifier('checkout.session.async_payment_failed', ['payment_status' => 'unpaid', 'status' => 'complete'])->assertOk();
        $this->assertNull($this->facture->fresh()->online_payment_pending_at);
    }

    public function test_un_paiement_en_trop_n_est_journalise_qu_une_fois(): void
    {
        Encaissement::enregistrer($this->facture, (float) $this->facture->amount_incl_tax, now(), 'TRANSFER');

        $this->notifier('checkout.session.completed', ['id' => 'cs_test_9'])->assertOk();
        $this->notifier('checkout.session.completed', ['id' => 'cs_test_9'])->assertOk();

        $this->assertCount(1, PaiementStripe::excedentsARembourser($this->facture));
    }

    /** Un double de l'API Stripe pour les remboursements : sessions relues, remboursements crees. */
    private function fauxRemboursements(): object
    {
        $remboursements = new class
        {
            public array $crees = [];

            public string $statut = 'succeeded';

            public function create(array $p, array $options = []): object
            {
                $this->crees[] = ['params' => $p, 'options' => $options];

                return (object) ['id' => 're_test_'.count($this->crees), 'status' => $this->statut];
            }
        };

        $sessions = new class((string) $this->facture->id)
        {
            public function __construct(private string $facture) {}

            public function retrieve(string $id): object
            {
                return (object) ['id' => $id, 'client_reference_id' => $this->facture, 'payment_intent' => 'pi_'.$id];
            }
        };

        $this->app->instance('stripe.client', (object) [
            'checkout' => (object) ['sessions' => $sessions],
            'refunds' => $remboursements,
        ]);

        return $remboursements;
    }

    /** La facture reglee par virement, puis payee une seconde fois en ligne. */
    private function paiementEnTrop(): void
    {
        Encaissement::enregistrer($this->facture, (float) $this->facture->amount_incl_tax, now(), 'TRANSFER');
        $this->notifier('checkout.session.completed', ['id' => 'cs_test_9'])->assertOk();
    }

    public function test_l_administrateur_rembourse_un_paiement_en_trop(): void
    {
        $this->paiementEnTrop();
        $stripe = $this->fauxRemboursements();
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)
            ->post(route('payments.rembourser', $this->facture), ['session' => 'cs_test_9'])
            ->assertSessionHas('success');

        $this->assertCount(1, $stripe->crees);
        $this->assertSame('pi_cs_test_9', $stripe->crees[0]['params']['payment_intent']);
        $this->assertSame((int) round((float) $this->facture->amount_incl_tax * 100), $stripe->crees[0]['params']['amount']);
        $this->assertSame('remboursement-cs_test_9', $stripe->crees[0]['options']['idempotency_key']);

        $this->assertCount(0, PaiementStripe::excedentsARembourser($this->facture));
        $this->assertCount(1, PaiementStripe::remboursements($this->facture));
        // Seul l'excedent est rendu : la facture reste payee par le virement.
        $this->assertSame('PAID', $this->facture->fresh()->status);

        $this->actingAs($admin)
            ->get(route('invoices.show', $this->facture))
            ->assertInertia(fn ($page) => $page
                ->where('peutRembourser', true)
                ->has('aRembourser', 0)
                ->has('rembourses', 1)
                ->where('rembourses.0.remboursement', 're_test_1'));
    }

    public function test_un_second_clic_ne_rembourse_pas_deux_fois(): void
    {
        $this->paiementEnTrop();
        $stripe = $this->fauxRemboursements();
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)->post(route('payments.rembourser', $this->facture), ['session' => 'cs_test_9']);
        $this->actingAs($admin)
            ->post(route('payments.rembourser', $this->facture), ['session' => 'cs_test_9'])
            ->assertSessionHas('error');

        $this->assertCount(1, $stripe->crees);
    }

    public function test_une_session_qui_n_est_pas_en_trop_n_est_pas_remboursee(): void
    {
        $this->paiementEnTrop();
        $stripe = $this->fauxRemboursements();

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('payments.rembourser', $this->facture), ['session' => 'cs_test_autre'])
            ->assertSessionHas('error');

        $this->assertCount(0, $stripe->crees);
        $this->assertCount(1, PaiementStripe::excedentsARembourser($this->facture));
    }

    public function test_seul_l_administrateur_rembourse(): void
    {
        $this->paiementEnTrop();
        $stripe = $this->fauxRemboursements();

        foreach ([$this->facture->client->compte(), User::factory()->planificateur()->create()] as $compte) {
            $this->actingAs($compte)
                ->post(route('payments.rembourser', $this->facture), ['session' => 'cs_test_9'])
                ->assertForbidden();
        }

        $this->assertCount(0, $stripe->crees);
    }

    public function test_un_remboursement_refuse_par_stripe_reste_a_rembourser(): void
    {
        $this->paiementEnTrop();
        $stripe = $this->fauxRemboursements();
        $stripe->statut = 'failed';

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('payments.rembourser', $this->facture), ['session' => 'cs_test_9'])
            ->assertSessionHas('error');

        $this->assertCount(1, PaiementStripe::excedentsARembourser($this->facture));
        $this->assertCount(0, PaiementStripe::remboursements($this->facture));
    }
}
