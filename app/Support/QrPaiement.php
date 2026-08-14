<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrPaiement
{
    /**
     * Le QR de virement au format EPC 069-12 : beneficiaire, IBAN, montant
     * et communication, preremplis dans l'application bancaire du payeur.
     * Genere localement — un service de QR en ligne recevrait l'IBAN et le
     * montant de chaque facture.
     *
     * La charge fait 80 octets, soit un code de version 5 : 37 modules de
     * cote, 45 avec la marge obligatoire. Cette taille commande l'affichage.
     * En dessous de trois pixels par module a l'ecran, ou de 25 mm de cote
     * sur papier, l'appareil photo d'un telephone ne lit plus rien. La page
     * et le PDF dimensionnent le code en consequence.
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
