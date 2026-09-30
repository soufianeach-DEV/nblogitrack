<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Portabilite : chacun telecharge ses donnees, et rien de plus. */
class ExportDesDonneesTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_responsable_exporte_son_compte_et_son_entreprise(): void
    {
        $client = Client::factory()->create(['company_name' => 'Essai SA']);
        TransportOrder::factory()->create(['client_id' => $client->id]);
        TransportOrder::factory()->create();
        $admin = $client->users()->first();

        $reponse = $this->actingAs($admin)->get(route('profile.export'))->assertOk();

        $this->assertStringContainsString('attachment', $reponse->headers->get('Content-Disposition'));
        $this->assertSame($admin->email, $reponse->json('compte.email'));
        $this->assertSame('Essai SA', $reponse->json('entreprise.company_name'));
        $this->assertCount(1, $reponse->json('expeditions'));
        $this->assertIsArray($reponse->json('factures'));
    }

    public function test_un_membre_du_personnel_n_exporte_que_son_compte(): void
    {
        $reponse = $this->actingAs(User::factory()->planificateur()->create())->get(route('profile.export'))->assertOk();

        $this->assertNotNull($reponse->json('compte'));
        $this->assertNull($reponse->json('entreprise'));
        $this->assertNull($reponse->json('expeditions'));
    }
}
