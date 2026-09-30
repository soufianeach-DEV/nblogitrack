<?php

namespace Tests\Feature;

use App\Mail\AlerteIncident;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Accident ou panne : l'administration et la planification recoivent le
 * detail par courriel et voient le camion immobilise au tableau de bord.
 */
class AlerteIncidentPersonnelTest extends TestCase
{
    use RefreshDatabase;

    private Driver $chauffeur;

    private TransportOrder $ordre;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $utilisateur = User::factory()->chauffeur()->create(['first_name' => 'Sofie', 'last_name' => 'Declercq', 'phone' => '+32 471 76 30 10']);
        $this->chauffeur = Driver::create([
            'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(),
            'user_id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
        ]);
        $this->ordre = TransportOrder::factory()->enRoute()->create(['driver_id' => $this->chauffeur->id]);
    }

    private function signaler(string $type, bool $endommagee = false): void
    {
        $this->actingAs($this->chauffeur->user)
            ->post(route('missions.incident', $this->ordre), [
                'type' => $type,
                'marchandise_endommagee' => $endommagee,
                'commentaire' => 'Moteur en fumée sur l\'E40',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_une_panne_previent_administration_et_planification_en_un_envoi(): void
    {
        $admin = User::factory()->administrateur()->create(['locale' => 'fr']);
        $planificateur = User::factory()->planificateur()->create(['locale' => 'fr']);
        User::factory()->planificateur()->create(['is_active' => false]);

        $this->signaler('PANNE', endommagee: true);

        Mail::assertSent(AlerteIncident::class, 1);
        Mail::assertSent(AlerteIncident::class, function (AlerteIncident $m) use ($admin, $planificateur) {
            $tous = [...array_column($m->to, 'address'), ...array_column($m->bcc, 'address')];
            sort($tous);
            $attendus = [$admin->email, $planificateur->email];
            sort($attendus);

            return $tous === $attendus;
        });

        $html = (new AlerteIncident($this->ordre, $this->chauffeur->user, [
            'type' => 'PANNE', 'commentaire' => 'Moteur en fumée sur l\'E40', 'marchandise_endommagee' => true,
        ]))->render();
        $this->assertStringContainsString('Moteur en fumée', $html);
        $this->assertStringContainsString('Sofie Declercq', $html);
        $this->assertStringContainsString($this->ordre->tracking_number, $html);
        $this->assertStringContainsString('endommagée', $html);
    }

    public function test_une_marchandise_endommagee_seule_n_alerte_pas_le_personnel(): void
    {
        User::factory()->administrateur()->create();

        $this->signaler('DOMMAGE');

        Mail::assertNotSent(AlerteIncident::class);
    }

    public function test_le_tableau_de_bord_signale_le_camion_immobilise(): void
    {
        $admin = User::factory()->administrateur()->create();

        $this->signaler('ACCIDENT');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alertes', fn ($alertes) => collect($alertes)->contains(
                    fn ($a) => str_contains($a['titre'], 'immobilisé') && str_contains($a['lien'], $this->ordre->tracking_number)
                )));
    }
}
