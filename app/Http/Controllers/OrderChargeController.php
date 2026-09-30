<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InvoiceLine;
use App\Models\OrderCharge;
use App\Models\TransportOrder;
use App\Support\Formats;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Les supplements d'une expedition : temps d'attente au quai,
 * manutention, peage ou ferry exceptionnel. Le planificateur les pose,
 * la facture du mois suivant les reprend.
 */
class OrderChargeController extends Controller
{
    /** Au-dela, c'est une faute de frappe plutot qu'un temps d'attente. */
    private const PLAFOND = 100000;

    public function store(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        $donnees = $request->validate([
            'libelle' => 'required|string|min:3|max:200',
            'montant' => 'required|numeric|min:0.01|max:'.self::PLAFOND,
        ], [
            'montant.max' => Traductions::t('msg.supplement_trop_eleve', 'Un supplément ne peut pas dépasser :max HT.', [
                'max' => Formats::montant(self::PLAFOND),
            ]),
        ], [
            // « libelle » et « montant » servent aussi ailleurs : le nom
            // generique de la table des attributs ne dirait pas lequel.
            'libelle' => Traductions::t('champ.libelle_supplement', 'libellé du supplément'),
            'montant' => Traductions::t('champ.montant_supplement', 'montant du supplément'),
        ]);

        // Une expedition annulee sans indemnite ne donne lieu a aucune
        // facture : un supplement n'y aurait pas de sens.
        if ($transportOrder->status === 'CANCELLED' && ! ($transportOrder->cancellation_fee > 0)) {
            return back()->with('error', Traductions::t('msg.supplement_annulee', 'Cette expédition a été annulée sans frais : aucun supplément ne peut y être ajouté.'));
        }

        $supplement = OrderCharge::create([
            'transport_order_id' => $transportOrder->id,
            'label' => trim($donnees['libelle']),
            'amount' => round((float) $donnees['montant'], 2),
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record(
            'order.charge_added',
            'Supplément « '.$supplement->label.' » de '.number_format((float) $supplement->amount, 2, ',', ' ').' € sur '.$transportOrder->tracking_number,
            $transportOrder,
            ['supplement_id' => $supplement->id, 'montant' => (string) $supplement->amount],
        );

        return back()->with('success', Traductions::t('msg.supplement_ajoute', 'Supplément ajouté : il figurera sur la prochaine facture.'));
    }

    public function destroy(Request $request, TransportOrder $transportOrder, OrderCharge $supplement): RedirectResponse
    {
        abort_if($supplement->transport_order_id !== $transportOrder->id, 404);

        if (InvoiceLine::where('order_charge_id', $supplement->id)->exists()) {
            return back()->with('error', Traductions::t('msg.supplement_facture', 'Ce supplément est déjà facturé : annulez la facture par un avoir pour le corriger.'));
        }

        $supplement->delete();

        ActivityLog::record(
            'order.charge_removed',
            'Supplément « '.$supplement->label.' » retiré de '.$transportOrder->tracking_number,
            $transportOrder,
            ['montant' => (string) $supplement->amount],
        );

        return back()->with('success', Traductions::t('msg.supplement_retire', 'Supplément retiré.'));
    }
}
