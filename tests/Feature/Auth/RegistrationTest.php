<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * L'inscription interroge le registre europeen des assujettis, puis le
 * registre national. Ces tests simulent les deux : appeler les vrais
 * services rendrait la suite dependante de leur disponibilite, et le
 * seize aout le noeud belge de VIES etait justement en panne.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function formulaire(array $remplace = []): array
    {
        return array_merge([
            'company_name' => 'Transports Essai SRL',
            'vat_number' => 'BE0203201340',
            'billing_address' => 'Avenue Louise 100',
            'postal_code' => '1050',
            'city' => 'Bruxelles',
            'country' => 'Belgique',
            'business_sector' => 'Transport',
            'first_name' => 'Soufiane',
            'last_name' => 'Achraa',
            'phone' => '+32 470 00 00 00',
            'email' => 'contact@transports-essai.be',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
            'marque_declaree' => true,
        ], $remplace);
    }

    /** Le registre repond que le numero est valide et l'entreprise saine. */
    private function registreRepond(array $corps = []): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response(array_merge([
                'isValid' => true,
                'name' => 'TRANSPORTS ESSAI SRL',
                'address' => 'AVENUE LOUISE 100, 1050 BRUXELLES',
            ], $corps)),
            'kbopub.economie.fgov.be/*' => Http::response('<html><body></body></html>'),
            '*' => Http::response([], 200),
        ]);
    }

    public function test_l_ecran_d_inscription_s_affiche(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_une_entreprise_s_inscrit(): void
    {
        $this->registreRepond();

        $this->post(route('register'), $this->formulaire())
            ->assertSessionHasNoErrors();

        $utilisateur = User::where('email', 'contact@transports-essai.be')->first();

        $this->assertNotNull($utilisateur);
        $this->assertSame('CLIENT', $utilisateur->role);
        $this->assertNotNull(Client::find($utilisateur->id));
    }

    /**
     * Le role ne se lit jamais dans le formulaire. Personne ne peut se
     * declarer chauffeur en ajoutant un champ a sa requete.
     */
    public function test_le_role_ne_se_choisit_pas_dans_le_formulaire(): void
    {
        $this->registreRepond();

        $this->post(route('register'), $this->formulaire(['role' => 'ADMIN']));

        $this->assertSame('CLIENT', User::where('email', 'contact@transports-essai.be')->value('role'));
    }

    /**
     * Le point que l'audit avait releve comme le mieux defendu : sans
     * reponse du registre, pas d'inscription. Sinon le controle de
     * faillite se contourne en attendant, ou en provoquant, une panne.
     */
    public function test_pas_d_inscription_quand_le_registre_est_injoignable(): void
    {
        $this->registreRepond(['isValid' => false, 'userError' => 'MS_UNAVAILABLE']);

        $this->post(route('register'), $this->formulaire())
            ->assertSessionHasErrors('vat_number');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'contact@transports-essai.be']);
    }

    public function test_un_numero_inconnu_du_registre_est_refuse(): void
    {
        $this->registreRepond(['isValid' => false, 'userError' => 'INVALID']);

        $this->post(route('register'), $this->formulaire())
            ->assertSessionHasErrors('vat_number');

        $this->assertDatabaseMissing('users', ['email' => 'contact@transports-essai.be']);
    }

    public function test_la_declaration_de_marque_est_obligatoire(): void
    {
        $this->registreRepond();

        $this->post(route('register'), $this->formulaire(['marque_declaree' => false]))
            ->assertSessionHasErrors('marque_declaree');
    }
}
