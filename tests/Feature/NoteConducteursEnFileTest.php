<?php

namespace Tests\Feature;

use App\Jobs\EnvoyerNoteAuxConducteurs;
use App\Mail\NoteInformation;
use App\Models\ActivityLog;
use App\Models\DriverAcknowledgement;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** La note aux conducteurs part hors de la requete web. */
class NoteConducteursEnFileTest extends TestCase
{
    use RefreshDatabase;

    private function note(): Page
    {
        return Page::create([
            'slug' => DriverAcknowledgement::NOTE, 'titre_fr' => 'Note', 'corps_fr' => 'Texte',
            'publiee' => true, 'publiee_le' => now(),
        ]);
    }

    public function test_l_envoi_est_mis_en_file(): void
    {
        Queue::fake();
        $page = $this->note();

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('pages.notice.send', $page))
            ->assertSessionHas('success');

        Queue::assertPushed(EnvoyerNoteAuxConducteurs::class, fn ($job) => $job->page->is($page));
    }

    public function test_la_tache_ecrit_a_chaque_conducteur_actif_et_journalise(): void
    {
        Mail::fake();
        User::factory()->chauffeur()->count(3)->create();
        User::factory()->chauffeur()->desactive()->create();

        (new EnvoyerNoteAuxConducteurs($this->note()))->handle();

        Mail::assertSent(NoteInformation::class, 3);
        $this->assertSame(1, ActivityLog::where('action', 'driver.notice_sent')->count());
    }
}
