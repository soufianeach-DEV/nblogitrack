<?php

namespace App\Support;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Le PDF d'une facture, tel que le client le telecharge et tel qu'il part
 * en piece jointe : les deux passent par ici pour rester identiques.
 */
class FacturePdf
{
    public static function contenu(Invoice $facture): string
    {
        $facture->loadMissing([
            'lines.transportOrder:id,tracking_number',
            'creditedInvoice:id,reference',
        ]);

        return Pdf::loadView('pdf.facture', [
            'facture' => $facture,
            'qr' => self::qr($facture),
        ])
            ->setPaper('a4')
            ->output();
    }

    /**
     * Le code QR de virement du solde, absent d'une facture reglee ou d'un avoir.
     */
    public static function qr(Invoice $facture): ?string
    {
        if (! $facture->estAPayer() || $facture->solde() <= 0) {
            return null;
        }

        return QrPaiement::epc(
            config('entreprise.nom'),
            config('entreprise.iban'),
            $facture->solde(),
            $facture->payment_reference,
        );
    }
}
