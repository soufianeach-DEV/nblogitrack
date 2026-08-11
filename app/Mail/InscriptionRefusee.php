<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\User;
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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre demande d\'inscription NBLogiTrack',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inscription-refusee',
        );
    }
}
