<?php

namespace App\Enums;

/*
 * Le cycle de vie d'un ordre de transport, en un seul endroit :
 *
 *   PENDING ──affectation──▶ ASSIGNED ──enlevement──▶ IN_PROGRESS ──▶ DELIVERED
 *      ▲                        │                          │
 *      └─────desaffectation─────┘                          │
 *   (et, depuis chacun des trois premiers) ──annulation──▶ CANCELLED
 *
 * Une marchandise chargee ne revient jamais « en attente » : si le camion
 * ou le chauffeur change en route, c'est un transbordement, et l'ordre
 * reste en cours.
 */
enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case ASSIGNED = 'ASSIGNED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';

    /** @return list<self> */
    public function suivants(): array
    {
        return match ($this) {
            self::PENDING => [self::ASSIGNED, self::CANCELLED],
            self::ASSIGNED => [self::IN_PROGRESS, self::PENDING, self::CANCELLED],
            self::IN_PROGRESS => [self::DELIVERED, self::CANCELLED],
            self::DELIVERED, self::CANCELLED => [],
        };
    }

    public function peutPasserA(self $vers): bool
    {
        return in_array($vers, $this->suivants(), true);
    }

    /** Un ordre qui mobilise encore, ou mobilisera, un chauffeur et un camion. */
    public function estActif(): bool
    {
        return in_array($this, [self::PENDING, self::ASSIGNED, self::IN_PROGRESS], true);
    }

    /** @return list<string> */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> */
    public static function actifs(): array
    {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->estActif()),
        ));
    }
}
