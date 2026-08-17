<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentPosition extends Model
{
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

        return $precision === null || $precision <= self::PRECISION_MAX_M;
    }

    public function ordre(): BelongsTo
    {
        return $this->belongsTo(TransportOrder::class, 'transport_order_id');
    }
}
