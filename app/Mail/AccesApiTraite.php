<?php

namespace App\Mail;

use App\Models\ApiKeyRequest;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Repond au client : acces accorde (la cle s'affiche dans son espace, jamais
 * dans l'e-mail) ou refuse, avec le motif.
 */
class AccesApiTraite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ApiKeyRequest $demande, public User $destinataire)
    {
        $this->locale($destinataire->locale ?: 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->demande->status === ApiKeyRequest::ACCORDEE
                ? Traductions::t('courriel.acces_api_accorde_sujet', 'Votre accès à l\'API NBLogiTrack est prêt')
                : Traductions::t('courriel.acces_api_refuse_sujet', 'Votre demande d\'accès à l\'API NBLogiTrack'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.acces-api-traite');
    }
}
