<?php

namespace App\Support;

use App\Mail\FactureEmise;
use App\Models\ActivityLog;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie une facture a son client, par courriel, avec le PDF et le XML.
 */
class EnvoiFacture
{
    /**
     * Renvoie l'adresse du destinataire, ou null si l'envoi a echoue. Un
     * echec ne leve rien : la facture existe deja, et une panne du serveur
     * de courriel ne doit ni l'annuler ni arreter l'envoi des suivantes.
     */
    public static function envoyer(Invoice $facture): ?string
    {
        [$adresse, $prenom] = self::destinataire($facture);

        if ($adresse === null) {
            ActivityLog::record(
                'invoice.send_failed',
                'Facture '.$facture->reference.' non envoyée : aucune adresse',
                $facture,
            );

            return null;
        }

        $facture->loadMissing([
            'client:id,company_name,vat_number,peppol_id,billing_address,postal_code,city,country',
            'lines.transportOrder:id,tracking_number',
        ]);

        try {
            Mail::to($adresse)->send(new FactureEmise($facture, $prenom));
        } catch (\Throwable $e) {
            report($e);

            ActivityLog::record(
                'invoice.send_failed',
                'Échec de l\'envoi de la facture '.$facture->reference.' à '.$adresse,
                $facture,
                ['destinataire' => $adresse],
            );

            return null;
        }

        $facture->update(['sent_at' => now()]);

        ActivityLog::record(
            'invoice.sent',
            'Facture '.$facture->reference.' envoyée à '.$adresse,
            $facture,
            ['destinataire' => $adresse],
        );

        return $adresse;
    }

    /**
     * Le contact principal de l'entreprise, a defaut l'adresse du compte.
     *
     * @return array{0: ?string, 1: string}
     */
    private static function destinataire(Invoice $facture): array
    {
        $contact = ClientContact::where('client_id', $facture->client_id)
            ->whereNotNull('email')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        if ($contact !== null) {
            return [$contact->email, (string) $contact->first_name];
        }

        $compte = User::find($facture->client_id);

        return [$compte?->email, (string) $compte?->first_name];
    }
}
