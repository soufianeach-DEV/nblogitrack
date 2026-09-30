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
 * Previent le client que son expedition est livree : quand, a qui, et les
 * reserves notees a la livraison s'il y en a.
 */
class OrdreLivre extends Mailable
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
            subject: Traductions::t('courriel.livre_sujet', 'Votre expédition :numero a été livrée', [
                'numero' => $this->ordre->tracking_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ordre-livre',
        );
    }
}
