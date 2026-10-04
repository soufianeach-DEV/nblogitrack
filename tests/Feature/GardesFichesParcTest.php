<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Fiches chauffeur et vehicule : une echeance de permis effacee faisait une
 * erreur 500 (colonne obligatoire), et une case decochee envoyee en « 0 »
 * plutot qu'en vrai booleen passait a cote des gardes « engage ».
 */
class GardesFichesParcTest extends TestCase
{
    use RefreshDatabase;

    private function chauffeur(array $champs = []): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create();

        return Driver::create([
            'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(),
            'user_id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
            ...$champs,
        ]);
    }

    private function camion(array $champs = []): Vehicle
    {
        return Vehicle::create([
            'registration' => '1-GRD-001',
            'vin' => 'VF1GARDES000000001',
            'vehicle_type' => 'Porteur',
            'brand' => 'Volvo',
            'model' => 'FH',
            'capacity_tonnes' => 12,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
            ...$champs,
        ]);
    }

    /** @return array<string, mixed> */
    private function fiche(array $champs = []): array
    {
        return [
            'is_available' => true,
            'adr_certified' => false,
            'employment_status' => array_key_first(Driver::STATUTS),
            ...$champs,
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function casesDecochees(): array
    {
        return [
            'chaine' => ['0'],
            'entier' => [0],
        ];
    }

    public function test_effacer_l_echeance_du_permis_est_refuse_sans_erreur_500(): void
    {
        $chauffeur = $this->chauffeur();
        $echeance = $chauffeur->license_expiry->toDateString();

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $chauffeur), $this->fiche([
                'license_expiry' => '',
                'cpc_expiry' => now()->addYears(4)->toDateString(),
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors(['license_expiry' => 'Indiquez l\'échéance du permis.']);

        $frais = $chauffeur->fresh();
        $this->assertSame($echeance, $frais->license_expiry->toDateString());
        $this->assertSame(now()->addYears(2)->toDateString(), $frais->cpc_expiry->toDateString());
    }

    #[DataProvider('casesDecochees')]
    public function test_un_chauffeur_engage_ne_quitte_pas_le_service_par_une_case_decochee(mixed $decochee): void
    {
        $chauffeur = $this->chauffeur();
        TransportOrder::factory()->affectee()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $chauffeur), $this->fiche(['is_available' => $decochee]))
            ->assertSessionHasErrors('is_available');

        $this->assertTrue($chauffeur->fresh()->is_available);
    }

    #[DataProvider('casesDecochees')]
    public function test_un_chauffeur_en_matiere_dangereuse_garde_son_adr_par_une_case_decochee(mixed $decochee): void
    {
        $chauffeur = $this->chauffeur(['adr_certified' => true, 'adr_expiry' => now()->addYears(2)->toDateString()]);
        TransportOrder::factory()->affectee()->dangereuse()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $chauffeur), $this->fiche(['adr_certified' => $decochee]))
            ->assertSessionHasErrors('adr_certified');

        $this->assertTrue($chauffeur->fresh()->adr_certified);
    }

    #[DataProvider('casesDecochees')]
    public function test_un_vehicule_engage_ne_quitte_pas_le_service_par_une_case_decochee(mixed $decochee): void
    {
        $camion = $this->camion();
        TransportOrder::factory()->affectee()->create(['vehicle_registration' => $camion->registration]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('vehicles.update', $camion->registration), ['is_available' => $decochee])
            ->assertSessionHasErrors('is_available');

        $this->assertTrue($camion->fresh()->is_available);
    }

    #[DataProvider('casesDecochees')]
    public function test_un_vehicule_en_matiere_dangereuse_garde_son_equipement_adr_par_une_case_decochee(mixed $decochee): void
    {
        $camion = $this->camion(['adr_equipe' => true]);
        TransportOrder::factory()->affectee()->dangereuse()->create(['vehicle_registration' => $camion->registration]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('vehicles.update', $camion->registration), ['is_available' => true, 'adr_equipe' => $decochee])
            ->assertSessionHasErrors('adr_equipe');

        $this->assertTrue($camion->fresh()->adr_equipe);
    }

    public function test_une_case_decochee_retire_bien_du_service_un_vehicule_libre(): void
    {
        $camion = $this->camion();

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('vehicles.update', $camion->registration), ['is_available' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($camion->fresh()->is_available);
    }
}
