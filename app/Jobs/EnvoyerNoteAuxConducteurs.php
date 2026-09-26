<?php

namespace App\Jobs;

use App\Mail\NoteInformation;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * La note d'information part a chaque conducteur actif, hors de la
 * requete web : cent envois SMTP d'affilee depassaient le temps maximal
 * d'une page. Un envoi qui echoue n'arrete pas les suivants, et le journal
 * retient qui a recu la note et qui ne l'a pas recue.
 */
class EnvoyerNoteAuxConducteurs implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Page $page) {}

    public function handle(): void
    {
        $conducteurs = User::where('role', 'DRIVER')->where('is_active', true)->get();
        $echecs = [];

        foreach ($conducteurs as $conducteur) {
            try {
                Mail::to($conducteur->email)->send(
                    new NoteInformation($this->page, $conducteur, $conducteur->locale ?? 'fr'),
                );
            } catch (\Throwable $e) {
                report($e);
                $echecs[] = $conducteur->email;
            }
        }

        ActivityLog::record(
            'driver.notice_sent',
            'Note d\'information adressée à '.($conducteurs->count() - count($echecs)).' conducteur(s)',
            $this->page,
            array_filter([
                'version' => $this->page->updated_at?->toIso8601String(),
                'echecs' => $echecs,
            ]),
        );
    }
}
