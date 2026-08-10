<?php

namespace App\Mail;

use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre expédition '.$this->ordre->tracking_number.' est enregistrée',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ordre-cree',
        );
    }
}
