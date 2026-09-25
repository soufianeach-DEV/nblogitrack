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
            'client:id,company_name,vat_number,billing_address,postal_code,city,country',
            'lines.transportOrder:id,tracking_number',
        ]);

        return Pdf::loadView('pdf.facture', [
            'facture' => $facture,
            'qr' => self::qr($facture),
        ])
            ->setPaper('a4')
            ->output();
    }

    /**
     * Le code QR de virement, absent d'une facture deja reglee.
     */
    public static function qr(Invoice $facture): ?string
    {
        if ($facture->status === 'PAID') {
            return null;
        }

        return QrPaiement::epc(
            config('entreprise.nom'),
            config('entreprise.iban'),
            (float) $facture->amount_incl_tax,
            $facture->payment_reference,
        );
    }
}
