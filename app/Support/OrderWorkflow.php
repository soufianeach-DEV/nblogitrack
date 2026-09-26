<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use Carbon\CarbonInterface;

/**
 * Toutes les transitions d'un ordre de transport passent par ici.
 *
 * Chaque transition est une mise a jour conditionnelle : elle ne
 * s'applique que si l'ordre est encore dans l'etat attendu. Deux
 * utilisateurs qui agissent en meme temps (le client qui annule pendant
 * que le chauffeur confirme l'enlevement, deux planificateurs qui
 * affectent le meme ordre) ne peuvent donc pas s'ecraser : le second
 * recoit une TransitionRefusee.
 */
class OrderWorkflow
{
    public static function affecter(TransportOrder $ordre, Vehicle $camion, Driver $chauffeur, CarbonInterface $enlevement): void
    {
        self::passer($ordre, OrderStatus::PENDING, OrderStatus::ASSIGNED, [
            'vehicle_registration' => $camion->registration,
            'driver_id' => $chauffeur->id,
            'assigned_at' => now(),
            'pickup_date' => $enlevement,
        ]);
    }

    /**
     * Changer de camion ou de chauffeur sans changer d'etat : avant le
     * depart, ou en route (transbordement, panne, relais de chauffeur).
     */
    public static function reaffecter(TransportOrder $ordre, Vehicle $camion, Driver $chauffeur): void
    {
        $etat = OrderStatus::from($ordre->status);

        if (! in_array($etat, [OrderStatus::ASSIGNED, OrderStatus::IN_PROGRESS], true)) {
            throw new TransitionRefusee(self::message());
        }

        self::appliquer($ordre, $etat, [
            'vehicle_registration' => $camion->registration,
            'driver_id' => $chauffeur->id,
            'assigned_at' => now(),
        ]);
    }

    /** Retour en attente : seulement avant l'enlevement. */
    public static function desaffecter(TransportOrder $ordre): void
    {
        self::passer($ordre, OrderStatus::ASSIGNED, OrderStatus::PENDING, [
            'vehicle_registration' => null,
            'driver_id' => null,
            'assigned_at' => null,
            'suivi_direct' => false,
        ]);
    }

    public static function enlever(TransportOrder $ordre): void
    {
        self::passer($ordre, OrderStatus::ASSIGNED, OrderStatus::IN_PROGRESS, [
            'picked_up_at' => now(),
        ]);
    }

    public static function livrer(TransportOrder $ordre, ?string $receptionnaire = null, ?string $reserves = null): void
    {
        self::passer($ordre, OrderStatus::IN_PROGRESS, OrderStatus::DELIVERED, [
            'delivered_at' => now(),
            'actual_delivery_date' => now()->toDateString(),
            'received_by' => $receptionnaire !== null && trim($receptionnaire) !== '' ? trim($receptionnaire) : null,
            'delivery_reserves' => $reserves !== null && trim($reserves) !== '' ? trim($reserves) : null,
            'suivi_direct' => false,
        ]);
    }

    /**
     * @param  OrderStatus  $depuis  l'etat sur lequel l'annulation a ete decidee (et l'indemnite calculee)
     */
    public static function annuler(TransportOrder $ordre, OrderStatus $depuis, int $auteur, ?float $indemnite = null): void
    {
        self::passer($ordre, $depuis, OrderStatus::CANCELLED, [
            'cancelled_at' => now(),
            'cancelled_by' => $auteur,
            'cancellation_fee' => $indemnite !== null && $indemnite > 0 ? $indemnite : null,
            'suivi_direct' => false,
        ]);
    }

    /** @param  array<string, mixed>  $champs */
    private static function passer(TransportOrder $ordre, OrderStatus $de, OrderStatus $vers, array $champs): void
    {
        if (! $de->peutPasserA($vers) || $ordre->status !== $de->value) {
            throw new TransitionRefusee(self::message());
        }

        self::appliquer($ordre, $de, ['status' => $vers->value] + $champs);
    }

    /** @param  array<string, mixed>  $champs */
    private static function appliquer(TransportOrder $ordre, OrderStatus $attendu, array $champs): void
    {
        $modifies = TransportOrder::whereKey($ordre->getKey())
            ->where('status', $attendu->value)
            ->update($champs);

        if ($modifies === 0) {
            throw new TransitionRefusee(self::message());
        }

        $ordre->refresh();
    }

    private static function message(): string
    {
        return Traductions::t('msg.ordre_etat_change', 'Cet ordre vient de changer d\'état : actualisez la page.');
    }
}
