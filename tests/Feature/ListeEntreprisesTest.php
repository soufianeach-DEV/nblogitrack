<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ListeEntreprisesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_demande_la_plus_recente_s_affiche_en_premier(): void
    {
        // L'inscription date du compte cree avec l'entreprise.
        foreach (['Ancienne SRL' => 3, 'Zeta Récente SRL' => 0, 'Moyenne SRL' => 1] as $nom => $jours) {
            Client::factory()->enAttente()->create(['company_name' => $nom])
                ->compte()->forceFill(['created_at' => now()->subDays($jours)])->save();
        }

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('clients.data.0.company_name', 'Zeta Récente SRL')
                ->has('clients.data.0.inscrit_le')
                ->where('clients.data.1.company_name', 'Moyenne SRL')
                ->where('clients.data.2.company_name', 'Ancienne SRL'));
    }

    public function test_les_validees_suivent_la_date_de_validation(): void
    {
        Client::factory()->create(['company_name' => 'Validée hier SRL', 'validated_at' => now()->subDay()]);
        Client::factory()->create(['company_name' => 'Validée ce matin SRL', 'validated_at' => now()]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('clients.index', ['etat' => 'validees']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('clients.data.0.company_name', 'Validée ce matin SRL')
                ->where('clients.data.1.company_name', 'Validée hier SRL'));
    }
}
