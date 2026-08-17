<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

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

    public function test_le_role_ne_se_choisit_pas_dans_le_formulaire(): void
    {
        $this->registreRepond();

        $this->post(route('register'), $this->formulaire(['role' => 'ADMIN']));

        $this->assertSame('CLIENT', User::where('email', 'contact@transports-essai.be')->value('role'));
    }

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
