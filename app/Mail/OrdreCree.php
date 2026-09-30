<?php

namespace App\Mail;

use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrdreCree extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TransportOrder $ordre,
        public User $destinataire,
        public ?TariffGrid $grille = null,
    ) {
        $this->locale($destinataire->locale ?: 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Traductions::t('courriel.ordre_sujet', 'Votre expédition :numero est enregistrée', [
                'numero' => $this->ordre->tracking_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ordre-cree',
        );
    }
}
