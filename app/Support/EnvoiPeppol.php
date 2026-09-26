<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;

/**
 * Transmission d'une facture sur le reseau Peppol, obligatoire entre
 * entreprises belges depuis le 1er janvier 2026. Le fichier UBL est remis
 * au point d'acces du prestataire configure (services.peppol), qui le
 * livre au point d'acces du client.
 */
class EnvoiPeppol
{
    public static function actif(): bool
    {
        return filled(config('services.peppol.url')) && filled(config('services.peppol.cle'));
    }

    /**
     * Vrai si le point d'acces a accepte la facture, faux en cas d'echec,
     * null si aucun point d'acces n'est configure. Un echec ne leve rien :
     * il est journalise, la facture reste envoyee par courriel.
     */
    public static function envoyer(Invoice $facture): ?bool
    {
        if (! self::actif()) {
            return null;
        }

        try {
            $reponse = Http::withToken((string) config('services.peppol.cle'))
                ->connectTimeout(5)
                ->timeout(20)
                ->withBody(FactureUbl::pour($facture), 'application/xml')
                ->post((string) config('services.peppol.url'));
        } catch (\Throwable $e) {
            report($e);
            $reponse = null;
        }

        $reussi = $reponse?->successful() ?? false;

        ActivityLog::record(
            $reussi ? 'invoice.peppol_sent' : 'invoice.peppol_failed',
            ($reussi ? 'Facture ' : 'Échec Peppol de la facture ').$facture->reference,
            $facture,
            ['statut' => $reponse?->status()],
        );

        return $reussi;
    }
}
