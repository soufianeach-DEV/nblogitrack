<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Driver;
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

    public function test_la_suggestion_choisie_retrouve_la_personne_par_son_nom_complet(): void
    {
        $wim = User::factory()->chauffeur()->create(['first_name' => 'Wim', 'last_name' => 'Peeters']);
        User::factory()->chauffeur()->create(['first_name' => 'Wim', 'last_name' => 'De Vos']);
        Driver::create([
            'user_id' => $wim->id,
            'license_number' => 'PERMIS-'.$wim->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(),
            'is_available' => true,
        ]);
        $admin = User::factory()->administrateur()->create(['first_name' => 'Admin', 'last_name' => 'Test']);

        // La suggestion « Wim Peeters », cliquee, devient la recherche :
        // elle doit retrouver le compte, dans un sens comme dans l'autre,
        // sans accents ni casse et malgre un double espace.
        foreach (['Wim Peeters', 'peeters wim', 'WIM  PEETERS'] as $terme) {
            $this->actingAs($admin)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->get(route('staff.index', ['q' => $terme]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('comptes', 1)
                    ->where('comptes.0.nom', 'Wim Peeters')
                    ->where('suggestions', ['Wim Peeters']));
        }

        $this->actingAs($admin)
            ->get(route('drivers.index', ['q' => 'Wim Peeters']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('chauffeurs', 1)
                ->where('chauffeurs.0.nom', 'Wim Peeters'));

        ActivityLog::create(['user_id' => $wim->id, 'action' => 'auth.login', 'description' => 'Connexion', 'ip_address' => '127.0.0.1', 'created_at' => now()]);

        $this->actingAs($admin)
            ->get(route('activity-logs.index', ['utilisateur' => 'Wim Peeters']))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));
    }
}
