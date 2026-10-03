<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Une cle d'API se rattache a une entreprise choisie dans la liste des
 * entreprises inscrites, pas a un nom tape au hasard.
 */
class CleApiEntrepriseInscriteTest extends TestCase
{
    use RefreshDatabase;

    private function administrateur(): User
    {
        return User::factory()->administrateur()->create();
    }

    private function generer(array $donnees)
    {
        return $this->actingAs($this->administrateur())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-keys.store'), $donnees);
    }

    public function test_la_liste_ne_propose_que_les_entreprises_que_l_api_accepte(): void
    {
        $validee = Client::factory()->create(['company_name' => 'Agro Goossens SC', 'city' => 'Gand']);
        $enAttente = Client::factory()->enAttente()->create();
        $refusee = Client::factory()->enAttente()->create(['rejection_reason' => 'Numéro de TVA inactif']);
        $sansCompteActif = Client::factory()->create();
        $sansCompteActif->users()->update(['is_active' => false]);
        $supprimee = Client::factory()->create();
        $supprimee->delete();

        $this->actingAs($this->administrateur())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('api-keys.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('entreprises', 1)
                ->where('entreprises.0.valeur', $validee->id)
                ->where('entreprises.0.libelle', 'Agro Goossens SC')
                ->where('entreprises.0.detail', $validee->vat_number)
                ->where('entreprises.0.ville', 'Gand')
                ->where('entreprises.0.contact', $validee->compte()->email));

        foreach ([$enAttente, $refusee, $sansCompteActif, $supprimee] as $exclue) {
            $this->generer(['client_id' => $exclue->id, 'permissions' => ['lecture']])
                ->assertSessionHasErrors('client_id');
        }

        $this->assertSame(0, ApiKey::count());
    }

    public function test_sans_libelle_la_cle_prend_le_nom_de_l_entreprise(): void
    {
        $entreprise = Client::factory()->create(['company_name' => 'Pharma Simon SC']);

        $this->generer(['client_id' => $entreprise->id, 'permissions' => ['lecture', 'ecriture']])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('cle_en_clair.nom', 'Pharma Simon SC');

        $cle = ApiKey::sole();
        $this->assertSame('Pharma Simon SC', $cle->name);
        $this->assertSame($entreprise->id, $cle->client_id);
    }

    public function test_un_libelle_saisi_est_garde(): void
    {
        $entreprise = Client::factory()->create();

        $this->generer(['nom' => 'ERP SAP', 'client_id' => $entreprise->id, 'permissions' => ['lecture']])
            ->assertSessionHasNoErrors();

        $this->assertSame('ERP SAP', ApiKey::sole()->name);
    }

    public function test_une_cle_interne_sans_libelle_s_appelle_cle_interne(): void
    {
        $this->generer(['permissions' => ['lecture']])->assertSessionHasNoErrors();

        $cle = ApiKey::sole();
        $this->assertSame('Clé interne', $cle->name);
        $this->assertNull($cle->client_id);
    }
}
