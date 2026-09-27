<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentPosition extends Model
{
    use DatesHeureDeBruxelles;

    public const UPDATED_AT = null;

    public const JALON = 'JALON';

    public const ROUTE = 'ROUTE';

    public const EVENEMENTS = [
        'PICKED_UP' => 'Prise en charge',
        'DELIVERED' => 'Livraison',
    ];

    public const PRECISION_MAX_M = 5000;

    protected $fillable = [
        'transport_order_id', 'driver_id', 'type', 'evenement',
        'lat', 'lng', 'precision_m', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public static function utilisable(?float $lat, ?float $lng, ?int $precision): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false;
        }

        // Le point (0, 0), dans le golfe de Guinee, est la valeur d'un
        // capteur qui n'a pas de position : le client voyait le camion en
        // plein ocean. Les camions roulent en Europe (Canaries et Acores
        // comprises).
        if (($lat === 0.0 && $lng === 0.0) || $lat < 27 || $lat > 72 || $lng < -32 || $lng > 45) {
            return false;
        }

        return $precision === null || $precision <= self::PRECISION_MAX_M;
    }

    /**
     * Un camion ne parcourt pas 800 km en une minute : un point qui
     * supposerait plus de 200 km/h depuis le precedent est une erreur de
     * capteur, et n'est pas montre au client.
     */
    public static function vraisemblable(?self $precedent, float $lat, float $lng): bool
    {
        if ($precedent === null) {
            return true;
        }

        $km = self::distanceKm($precedent->lat, $precedent->lng, $lat, $lng);
        $heures = max($precedent->recorded_at->diffInSeconds(now()), 60) / 3600;

        return $km / $heures <= 200;
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function ordre(): BelongsTo
    {
        return $this->belongsTo(TransportOrder::class, 'transport_order_id');
    }
}
