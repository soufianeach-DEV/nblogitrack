<?php

namespace App\Mail;

use App\Models\User;
use App\Support\Traductions;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Une inscription a ete tentee avec une adresse deja inscrite. Le
 * formulaire repond comme pour une inscription reussie (il ne dit pas
 * quelles adresses ont un compte) : c'est le titulaire qui est prevenu.
 */
class AdresseDejaInscrite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $destinataire)
    {
        $this->locale($destinataire->locale ?: 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Traductions::t('courriel.deja_inscrit_sujet', 'Votre adresse a déjà un compte NBLogiTrack'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.adresse-deja-inscrite');
    }
}
