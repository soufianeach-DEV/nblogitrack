<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Qui prevenir chez le client quand son expedition avance ou rencontre un
 * probleme : le compte qui l'a commandee dans l'application, s'il est
 * encore actif dans l'entreprise ; a defaut (commande par l'API ou a
 * partir d'un devis), les comptes qui passent les commandes.
 */
class ClientsAPrevenir
{
    /** @return Collection<int, User> */
    public static function pour(TransportOrder $ordre): Collection
    {
        $commanditaire = ActivityLog::where('subject_type', 'TransportOrder')
            ->where('subject_id', (string) $ordre->id)
            ->where('action', 'order.created')
            ->oldest('id')
            ->first()?->user;

        return $commanditaire?->is_active && $commanditaire->client_id === $ordre->client_id
            ? collect([$commanditaire])
            : ($ordre->client?->commanditaires() ?? collect());
    }
}
