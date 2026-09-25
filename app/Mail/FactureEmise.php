<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\FacturePdf;
use App\Support\FactureUbl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FactureEmise extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $facture,
        public string $prenom,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre facture '.$this->facture->reference.' - NBLogiTrack',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.facture-emise',
        );
    }

    /**
     * Le PDF pour le lire, le XML pour le logiciel comptable du client :
     * le meme fichier que le bouton « XML Peppol » de la facture.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => FacturePdf::contenu($this->facture), $this->facture->reference.'.pdf')
                ->withMime('application/pdf'),
            Attachment::fromData(fn () => FactureUbl::pour($this->facture), $this->facture->reference.'.xml')
                ->withMime('application/xml'),
        ];
    }
}
