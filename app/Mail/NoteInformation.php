<?php

namespace App\Mail;

use App\Models\Page;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * La note d'information adressee aux conducteurs.
 *
 * Le courriel remet le texte ; l'accuse de prise de connaissance se
 * donne dans l'application. Les deux se completent : un envoi prouve la
 * remise, il ne prouve pas la lecture.
 */
class NoteInformation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Page $note,
        public User $destinataire,
        public string $langue,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->note->titre($this->langue),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.note-information',
            with: [
                'titre' => $this->note->titre($this->langue),
                'corps' => $this->note->corps($this->langue),
                'version' => $this->note->updated_at?->format('d/m/Y'),
            ],
        );
    }
}
