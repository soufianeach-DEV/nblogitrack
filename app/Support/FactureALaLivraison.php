<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\TransportOrder;

/**
 * Une mission livree se facture tout de suite : le transport et les
 * supplements deja poses sur l'expedition partent sur une facture datee du
 * jour, envoyee au client avec le PDF et le XML. Ce qui arrive apres la
 * livraison (un supplement pose plus tard) et les indemnites d'annulation
 * restent factures le 1er du mois, ou par « Facturer maintenant ».
 */
class FactureALaLivraison
{
    public static function emettre(TransportOrder $ordre): ?Invoice
    {
        $facturier = app(Facturier::class);

        // La livraison est deja enregistree : une facture qui echoue ne la
        // defait pas. L'expedition reste a facturer, et la facturation du
        // 1er du mois la reprendra.
        try {
            $elements = $facturier->aFacturer(null, $ordre->client_id, true)
                ->flatten(1)
                ->filter(fn (array $e) => $e['transport_order_id'] === $ordre->id)
                ->values();

            $client = Client::withTrashed()->find($ordre->client_id);

            if ($elements->isEmpty() || $client === null) {
                return null;
            }

            $facture = $facturier->emettre($client, $elements->first()['date']->format('Y-m'), $elements, today());
        } catch (\Throwable $e) {
            report($e);
            ActivityLog::record(
                'invoice.auto_failed',
                'Facture non émise à la livraison de '.$ordre->tracking_number,
                $ordre,
            );

            return null;
        }

        ActivityLog::record(
            'invoices.generated',
            'Facture '.$facture->reference.' émise à la livraison de '.$ordre->tracking_number,
            $facture,
            ['factures' => [$facture->reference], 'a_la_livraison' => true],
        );

        if ($facture->status === 'SENT') {
            EnvoiFacture::envoyer($facture);
        }

        return $facture;
    }
}
