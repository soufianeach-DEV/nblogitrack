<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrPaiement
{
    /**
     * Le QR de virement au format EPC, celui que les applications bancaires
     * scannent pour preremplir un paiement : beneficiaire, IBAN, montant et
     * communication. Genere localement — un service de QR en ligne recevrait
     * l'IBAN et le montant de chaque facture.
     *
     * Rendu vectoriel en donnee incorporee, utilisable tel quel par la page
     * comme par le PDF : aucune extension serveur exigee, et le code reste
     * net a l'impression quelle que soit l'echelle. Null si la bibliotheque
     * n'est pas installee : la facture reste consultable sans son QR.
     */
    public static function epc(string $beneficiaire, string $iban, float $montant, string $communication): ?string
    {
        if (! class_exists(Writer::class)) {
            return null;
        }

        $charge = implode("\n", [
            'BCD',
            '002',
            '1',
            'SCT',
            '',
            $beneficiaire,
            str_replace(' ', '', $iban),
            'EUR'.number_format($montant, 2, '.', ''),
            '',
            '',
            $communication,
        ]);

        $svg = (new Writer(new ImageRenderer(new RendererStyle(300, 2), new SvgImageBackEnd)))
            ->writeString($charge);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
