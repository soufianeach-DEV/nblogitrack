<?php

namespace App\Mail;

use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Incidents;
use App\Support\Traductions;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Previent l'administration et la planification d'un accident ou d'une
 * panne, avec tout le detail interne : chauffeur, camion, commentaire,
 * etat de la marchandise et position.
 */
class AlerteIncident extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $incident  type, commentaire, marchandise_endommagee, lat, lng
     */
    public function __construct(
        public TransportOrder $ordre,
        public User $chauffeur,
        public array $incident,
        string $langue = 'fr',
    ) {
        $this->locale($langue);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Traductions::t('courriel.alerte_incident_sujet', ':type sur la mission :numero', [
                'type' => Incidents::libelle($this->incident['type'] ?? null),
                'numero' => $this->ordre->tracking_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alerte-incident',
        );
    }
}
