<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * Les factures d'une entreprise se lisent et se reglent par ses comptes
 * « administrateur » et « comptabilite » ; le personnel les voit toutes.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-all-orders') || $user->voitFacturesEntreprise();
    }

    public function view(User $user, Invoice $facture): bool
    {
        return $user->can('view-all-orders')
            || ($user->voitFacturesEntreprise() && $facture->client_id === $user->client_id);
    }

    public function pay(User $user, Invoice $facture): bool
    {
        return $user->voitFacturesEntreprise() && $facture->client_id === $user->client_id;
    }
}
