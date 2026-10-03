<?php

namespace App\Mail;

use App\Models\ApiKeyRequest;
use App\Support\Traductions;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Previent l'administration qu'une entreprise demande un acces a l'API. */
class DemandeAccesApi extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ApiKeyRequest $demande, string $langue = 'fr')
    {
        $this->locale($langue);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Traductions::t('courriel.demande_api_sujet', 'Demande d\'accès à l\'API : :entreprise', [
                'entreprise' => $this->demande->client?->company_name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demande-acces-api');
    }
}
