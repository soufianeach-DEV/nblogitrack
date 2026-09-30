<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\FacturePdf;
use App\Support\FactureUbl;
use App\Support\Traductions;
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
        string $langue = 'fr',
    ) {
        $this->locale($langue);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->facture->estAvoir()
                ? Traductions::t('courriel.avoir_sujet', 'Votre avoir :reference - NBLogiTrack', ['reference' => $this->facture->reference])
                : Traductions::t('courriel.facture_sujet', 'Votre facture :reference - NBLogiTrack', [
                    'reference' => $this->facture->reference,
                ]),
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
