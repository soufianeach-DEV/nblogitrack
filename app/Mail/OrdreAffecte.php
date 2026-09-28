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
 * Previent le client qu'un camion et un chauffeur sont reserves pour son
 * expedition, avec la date d'enlevement retenue.
 */
class OrdreAffecte extends Mailable
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
            subject: Traductions::t('courriel.affecte_sujet', 'Votre expédition :numero est prise en charge', [
                'numero' => $this->ordre->tracking_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ordre-affecte',
        );
    }
}
