<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ecran_de_verification_s_affiche(): void
    {
        $utilisateur = User::factory()->unverified()->create();

        $this->actingAs($utilisateur)->get(route('verification.notice'))->assertOk();
    }

    public function test_l_adresse_se_verifie_par_le_lien_signe(): void
    {
        Event::fake();

        $utilisateur = User::factory()->unverified()->create();

        $lien = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $utilisateur->id,
            'hash' => sha1($utilisateur->email),
        ]);

        $this->actingAs($utilisateur)->get($lien);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($utilisateur->fresh()->hasVerifiedEmail());
    }

    public function test_un_lien_falsifie_ne_verifie_rien(): void
    {
        $utilisateur = User::factory()->unverified()->create();

        $lien = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $utilisateur->id,
            'hash' => sha1('une-autre-adresse@exemple.be'),
        ]);

        $this->actingAs($utilisateur)->get($lien);

        $this->assertFalse($utilisateur->fresh()->hasVerifiedEmail());
    }
}
