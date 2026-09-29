<?php

namespace App\Mail;

use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Previent le client qu'un incident peut retarder son expedition. Le
 * detail (type, commentaire du chauffeur) reste interne : le client sait
 * qu'il se passe quelque chose et qui s'en occupe.
 */
class IncidentExpedition extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TransportOrder $ordre,
        public User $destinataire,
    ) {
        $this->locale($destinataire->locale ?: 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Traductions::t('courriel.incident_sujet', 'Un incident peut retarder votre expédition :numero', [
                'numero' => $this->ordre->tracking_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incident-expedition',
        );
    }
}
