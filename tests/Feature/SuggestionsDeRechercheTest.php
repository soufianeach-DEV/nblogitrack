<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Les champs de recherche du parc et du personnel proposent les valeurs
 * existantes qui contiennent le texte tape, sans accents ni casse.
 */
class SuggestionsDeRechercheTest extends TestCase
{
    use RefreshDatabase;

    private function camion(string $immatriculation, string $marque): Vehicle
    {
        return Vehicle::create([
            'registration' => $immatriculation,
            'vin' => 'VF1'.str_pad((string) crc32($immatriculation), 14, '0'),
            'vehicle_type' => 'Porteur',
            'brand' => $marque,
            'model' => 'FH',
            'capacity_tonnes' => 12,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
        ]);
    }

    public function test_le_parc_propose_immatriculations_et_marques(): void
    {
        $this->camion('1-ABC-123', 'Volvo');
        $this->camion('1-ABD-456', 'Renault');
        $this->camion('2-XYZ-789', 'Scania');

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('vehicles.index', ['q' => 'ab']))
            ->assertInertia(fn (Assert $page) => $page->where('suggestions', ['1-ABC-123', '1-ABD-456']));

        // Moins de deux caracteres : rien a proposer.
        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('vehicles.index', ['q' => 'v']))
            ->assertInertia(fn (Assert $page) => $page->where('suggestions', []));
    }

    public function test_le_personnel_est_propose_par_prenom_et_nom_sans_accents(): void
    {
        User::factory()->planificateur()->create(['first_name' => 'Hélène', 'last_name' => 'Dubois']);
        User::factory()->planificateur()->create(['first_name' => 'Marc', 'last_name' => 'Janssens']);

        // L'ecran du personnel redemande le mot de passe.
        $this->actingAs(User::factory()->administrateur()->create(['first_name' => 'Admin', 'last_name' => 'Test']))
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('staff.index', ['q' => 'helene']))
            ->assertInertia(fn (Assert $page) => $page->where('suggestions', ['Hélène Dubois']));
    }
}
