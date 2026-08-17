<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrPaiement
{
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

        $svg = (new Writer(new ImageRenderer(new RendererStyle(300, 4), new SvgImageBackEnd)))
            ->writeString($charge, 'UTF-8', ErrorCorrectionLevel::M());

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
