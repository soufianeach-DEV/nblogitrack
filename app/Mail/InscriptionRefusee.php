<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InscriptionRefusee extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public User $destinataire,
        public string $motif,
    ) {
        $this->locale($destinataire->locale ?: 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Traductions::t('courriel.refus_sujet', 'Votre demande d\'inscription NBLogiTrack'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inscription-refusee',
        );
    }
}
