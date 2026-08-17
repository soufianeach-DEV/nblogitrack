<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le prix ne se calcule pas sur ce que dit le navigateur.
 *
 * C'etait la faille la plus couteuse des dix-huit que mon audit a
 * trouvees. Le formulaire envoyait les coordonnees des deux points et
 * le serveur tarifait dessus : en modifiant deux champs caches depuis
 * la console, un Bruxelles-Marseille se payait au prix d'une course
 * intra-urbaine. Sur une application de transport, le devis engage
 * l'entreprise.
 *
 * Le serveur relocalise desormais les deux localites lui-meme, dans la
 * table des codes postaux, et c'est cette distance-la qui tarife.
 */
class TarificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Les quelques localites dont ces tests ont besoin. La table
        // complete vient de GeoNames et compte six cent mille lignes :
        // la charger ici couterait des minutes pour rien.
        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '3500', 'city' => 'Hasselt', 'lat' => 50.9311, 'lng' => 5.3378],
            ['country_code' => 'BE', 'code' => '1000', 'city' => 'Bruxelles', 'lat' => 50.8504, 'lng' => 4.3488],
            ['country_code' => 'FR', 'code' => '75001', 'city' => 'Paris', 'lat' => 48.8534, 'lng' => 2.3488],
        ]);
    }

    /** @return array<string, mixed> */
    private function commande(array $remplace = []): array
    {
        return array_merge([
            'pickup_address' => 'Rue Neuve 43, 3500 Hasselt, Belgique',
            'delivery_address' => 'Avenue Louise 200, 1000 Bruxelles, Belgique',
            'delivery_country' => 'BE',
            'pickup_lat' => 50.9311,
            'pickup_lng' => 5.3378,
            'delivery_lat' => 50.8504,
            'delivery_lng' => 4.3488,
            'weight' => 1200,
            'goods_type' => TransportOrder::MARCHANDISES[0],
            'priority' => 'NORMAL',
            'requested_delivery_date' => now()->addDays(7)->toDateString(),
        ], $remplace);
    }

    private function grilleBelge(): TariffGrid
    {
        return TariffGrid::factory()->create();
    }

    public function test_un_depot_honnete_est_tarife(): void
    {
        $client = Client::factory()->create();
        $grille = $this->grilleBelge();

        $this->actingAs(User::find($client->id))
            ->post(route('transport-orders.store'), $this->commande(['tariff_grid_id' => $grille->id]))
            ->assertSessionHasNoErrors();

        $ordre = TransportOrder::where('client_id', $client->id)->first();

        $this->assertNotNull($ordre);
        $this->assertGreaterThan(0, $ordre->distance_km);
        $this->assertGreaterThan(0, $ordre->estimated_cost);
        $this->assertNotNull($ordre->tracking_code);
    }

    /**
     * Le coeur du correctif : des coordonnees qui ne correspondent pas a
     * l'adresse ecrite font refuser le depot. Ici on annonce Hasselt et
     * on transmet le point de Paris.
     */
    public function test_des_coordonnees_qui_mentent_font_refuser_le_depot(): void
    {
        $client = Client::factory()->create();
        $grille = $this->grilleBelge();

        $this->actingAs(User::find($client->id))
            ->post(route('transport-orders.store'), $this->commande([
                'tariff_grid_id' => $grille->id,
                'pickup_lat' => 48.8534,
                'pickup_lng' => 2.3488,
            ]))
            ->assertSessionHasErrors();

        $this->assertSame(0, TransportOrder::count());
    }

    /**
     * Le trajet raccourci : on garde les adresses mais on rapproche les
     * deux points pour payer moins. Le serveur retrouve les vraies
     * localites et voit l'ecart.
     */
    public function test_un_trajet_raccourci_est_refuse(): void
    {
        $client = Client::factory()->create();
        $grille = $this->grilleBelge();

        $this->actingAs(User::find($client->id))
            ->post(route('transport-orders.store'), $this->commande([
                'tariff_grid_id' => $grille->id,
                'delivery_lat' => 50.9311,
                'delivery_lng' => 5.3378,
            ]))
            ->assertSessionHasErrors();

        $this->assertSame(0, TransportOrder::count());
    }

    /**
     * On ne peut pas annoncer la France et se faire tarifer la Belgique :
     * le pays de destination determine la grille.
     */
    public function test_la_grille_doit_correspondre_au_pays_de_destination(): void
    {
        $client = Client::factory()->create();

        // Les deux zones existent : sans la grille belge, la validation
        // refuserait le pays lui-meme et l'on ne verifierait pas la
        // correspondance, qui est le sujet du test.
        $this->grilleBelge();
        $grilleFrancaise = TariffGrid::factory()->zone('FR', 'France')->create();

        $this->actingAs(User::find($client->id))
            ->post(route('transport-orders.store'), $this->commande([
                'tariff_grid_id' => $grilleFrancaise->id,
                'delivery_country' => 'BE',
            ]))
            ->assertSessionHasErrors('tariff_grid_id');

        $this->assertSame(0, TransportOrder::count());
    }

    /** Une localite qui n'existe pas ne se tarife pas. */
    public function test_une_localite_inconnue_est_refusee(): void
    {
        $client = Client::factory()->create();
        $grille = $this->grilleBelge();

        $this->actingAs(User::find($client->id))
            ->post(route('transport-orders.store'), $this->commande([
                'tariff_grid_id' => $grille->id,
                'delivery_address' => 'Rue Machin 1, 9999 Zzzzville, Belgique',
            ]))
            ->assertSessionHasErrors();

        $this->assertSame(0, TransportOrder::count());
    }

    /**
     * Le prix vient de la base, pas du formulaire : un champ « prix »
     * ajoute a la requete ne doit rien changer.
     */
    public function test_un_prix_transmis_par_le_formulaire_est_ignore(): void
    {
        $client = Client::factory()->create();
        $grille = $this->grilleBelge();

        $this->actingAs(User::find($client->id))
            ->post(route('transport-orders.store'), $this->commande([
                'tariff_grid_id' => $grille->id,
                'estimated_cost' => 1,
                'distance_km' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $ordre = TransportOrder::where('client_id', $client->id)->firstOrFail();

        $this->assertGreaterThan(1, $ordre->estimated_cost);
        $this->assertGreaterThan(1, $ordre->distance_km);
    }
}
