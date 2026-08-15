<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une position relevee pour un envoi.
 *
 * Voir la migration pour la difference entre un jalon et un point de
 * route : elle commande la duree de conservation.
 */
class ShipmentPosition extends Model
{
    public const UPDATED_AT = null;

    /** Un fait de gestion, conserve comme une mention de lettre de voiture. */
    public const JALON = 'JALON';

    /** Une position intermediaire, effacee peu apres la livraison. */
    public const ROUTE = 'ROUTE';

    /** Les deux seuls moments ou le chauffeur fait avancer sa mission. */
    public const EVENEMENTS = [
        'PICKED_UP' => 'Prise en charge',
        'DELIVERED' => 'Livraison',
    ];

    /** Au-dela, on ne parle plus d'une position mais d'une region. */
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

    /**
     * Valide un couple de coordonnees venu du navigateur.
     *
     * Un telephone rend parfois une position a plusieurs kilometres
     * pres, en interieur ou sous un pont. L'enregistrer donnerait une
     * fausse certitude au client : mieux vaut ne rien montrer.
     */
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
