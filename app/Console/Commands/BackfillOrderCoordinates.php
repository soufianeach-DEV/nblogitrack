<?php

namespace App\Console\Commands;

use App\Models\TransportOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderCoordinates extends Command
{
    protected $signature = 'geo:backfill-coordinates';

    protected $description = 'Retrouve les coordonnées des expéditions anciennes à partir de leur code postal';

    private const PAYS = [
        'belgique' => 'BE', 'france' => 'FR', 'pays-bas' => 'NL', 'allemagne' => 'DE',
        'luxembourg' => 'LU', 'italie' => 'IT', 'espagne' => 'ES', 'portugal' => 'PT',
        'autriche' => 'AT', 'pologne' => 'PL', 'tchéquie' => 'CZ', 'danemark' => 'DK',
        'suède' => 'SE', 'finlande' => 'FI', 'irlande' => 'IE', 'grèce' => 'GR',
        'hongrie' => 'HU', 'roumanie' => 'RO', 'bulgarie' => 'BG', 'croatie' => 'HR',
        'slovénie' => 'SI', 'slovaquie' => 'SK', 'lituanie' => 'LT', 'lettonie' => 'LV',
        'estonie' => 'EE', 'malte' => 'MT', 'chypre' => 'CY',
    ];

    public function handle(): int
    {
        $aTraiter = TransportOrder::whereNull('pickup_lat')->orWhereNull('delivery_lat');
        $total = $aTraiter->count();

        if ($total === 0) {
            $this->info('Toutes les expéditions ont déjà leurs coordonnées.');

            return self::SUCCESS;
        }

        $this->line("{$total} expédition(s) à compléter…");

        $complets = 0;
        $partiels = 0;
        $echecs = 0;

        $aTraiter->chunkById(200, function ($ordres) use (&$complets, &$partiels, &$echecs) {
            foreach ($ordres as $ordre) {
                $depart = $this->localiser($ordre->pickup_address);
                $arrivee = $this->localiser($ordre->delivery_address);

                if ($depart === null && $arrivee === null) {
                    $echecs++;

                    continue;
                }

                $ordre->update(array_filter([
                    'pickup_lat' => $depart['lat'] ?? null,
                    'pickup_lng' => $depart['lng'] ?? null,
                    'delivery_lat' => $arrivee['lat'] ?? null,
                    'delivery_lng' => $arrivee['lng'] ?? null,
                ], fn ($v) => $v !== null));

                $depart !== null && $arrivee !== null ? $complets++ : $partiels++;
            }
        });

        $this->info("Terminé : {$complets} complètes, {$partiels} partielles, {$echecs} sans correspondance.");

        return self::SUCCESS;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function localiser(?string $adresse): ?array
    {
        if ($adresse === null || $adresse === '') {
            return null;
        }

        $pays = 'BE';

        foreach (self::PAYS as $nom => $code) {
            if (str_contains(mb_strtolower($adresse), $nom)) {
                $pays = $code;
                break;
            }
        }

        if (! preg_match_all('/\b(\d{4,6})\b/', $adresse, $trouves)) {
            return null;
        }

        foreach (array_reverse($trouves[1]) as $candidat) {
            $point = DB::table('postal_codes')
                ->where('country_code', $pays)
                ->where('code', $candidat)
                ->first(['lat', 'lng']);

            if ($point) {
                return ['lat' => (float) $point->lat, 'lng' => (float) $point->lng];
            }
        }

        return null;
    }
}
