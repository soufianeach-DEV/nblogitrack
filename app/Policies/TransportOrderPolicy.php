<?php

namespace App\Policies;

use App\Models\TransportOrder;
use App\Models\User;

/**
 * Qui voit et qui agit sur une expedition. Le personnel voit tout ; un
 * compte client ne voit que les expeditions de son entreprise, et seuls
 * les roles « administrateur » et « commandes » commandent ou annulent.
 */
class TransportOrderPolicy
{
    public function view(User $user, TransportOrder $ordre): bool
    {
        return $user->can('view-all-orders')
            || ($user->isClient() && $user->client_id !== null && $ordre->client_id === $user->client_id);
    }

    public function create(User $user): bool
    {
        return $user->peutCommander();
    }

    public function cancel(User $user, TransportOrder $ordre): bool
    {
        return $user->peutCommander() && $ordre->client_id === $user->client_id;
    }
}
