<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PurchaseInvoice;
use App\Models\Vehicle;
use App\Support\Traductions;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filtres = $request->validate([
            'q' => 'nullable|string|max:60',
            'categorie' => 'nullable|in:'.implode(',', array_keys(PurchaseInvoice::CATEGORIES)),
            'etat' => 'nullable|in:a_payer,retard,payees',
        ]);

        $requete = PurchaseInvoice::with('vehicle:registration,brand,model');

        if (! empty($filtres['q'])) {
            $terme = '%'.$filtres['q'].'%';
            $requete->where(fn ($q) => $q
                ->where('supplier_name', 'ilike', $terme)
                ->orWhere('reference', 'ilike', $terme)
                ->orWhere('vehicle_registration', 'ilike', $terme));
        }

        if (! empty($filtres['categorie'])) {
            $requete->where('category', $filtres['categorie']);
        }

        match ($filtres['etat'] ?? null) {
            'a_payer' => $requete->where('status', 'TO_PAY'),
            'retard' => $requete->where('status', 'TO_PAY')->where('due_on', '<', now()->toDateString()),
            'payees' => $requete->where('status', 'PAID'),
            default => null,
        };

        $achats = $requete->orderByDesc('issued_on')->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (PurchaseInvoice $a) => [
                'id' => $a->id,
                'fournisseur' => $a->supplier_name,
                'reference' => $a->reference,
                'categorie' => Traductions::vocabulaire('achat', PurchaseInvoice::CATEGORIES[$a->category] ?? $a->category),
                'vehicule' => $a->vehicle_registration,
                'vehicule_detail' => trim(($a->vehicle?->brand ?? '').' '.($a->vehicle?->model ?? '')),
                'periode' => $a->period_start->locale('fr')->isoFormat('MMMM YYYY'),
                'echeance' => $a->due_on->format('d/m/Y'),
                'ht' => (float) $a->amount_excl_tax,
                'tva' => (float) $a->vat_amount,
                'ttc' => (float) $a->amount_incl_tax,
                'deductible' => (bool) $a->vat_deductible,
                'litres' => $a->liters !== null ? (float) $a->liters : null,
                'km_taxes' => $a->taxed_km !== null ? (float) $a->taxed_km : null,
                'etat' => $a->status === 'TO_PAY' && $a->due_on->lt(now()->startOfDay()) ? 'OVERDUE' : $a->status,
                'payee_le' => $a->paid_on?->format('d/m/Y'),
            ]);

        return Inertia::render('Factures/Achats', [
            'achats' => $achats,
            'compteurs' => [
                'total' => PurchaseInvoice::count(),
                'a_payer' => PurchaseInvoice::where('status', 'TO_PAY')->count(),
                'retard' => PurchaseInvoice::where('status', 'TO_PAY')->where('due_on', '<', now()->toDateString())->count(),
                'payees' => PurchaseInvoice::where('status', 'PAID')->count(),
            ],
            'cartes' => [
                'a_payer_ttc' => (float) PurchaseInvoice::where('status', 'TO_PAY')->sum('amount_incl_tax'),
                'deductible' => (float) PurchaseInvoice::where('vat_deductible', true)->sum('vat_amount'),
            ],
            // La constante porte le francais : elle est evaluee au
            // chargement de la classe, avant que la langue soit connue.
            'categories' => collect(PurchaseInvoice::CATEGORIES)
                ->map(fn (string $libelle) => Traductions::vocabulaire('achat', $libelle))
                ->all(),
            'vehicules' => Vehicle::orderBy('registration')->get(['registration', 'brand', 'model'])
                ->map(fn (Vehicle $v) => ['valeur' => $v->registration, 'libelle' => $v->registration.' — '.$v->brand.' '.$v->model]),
            'fournisseurs' => PurchaseInvoice::distinct()->orderBy('supplier_name')->pluck('supplier_name'),
            'filtres' => $filtres,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'supplier_name' => 'required|string|max:100',
            'reference' => 'required|string|max:50',
            'category' => 'required|in:'.implode(',', array_keys(PurchaseInvoice::CATEGORIES)),
            'vehicle_registration' => 'required|exists:vehicles,registration',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'issued_on' => 'required|date',
            'due_on' => 'required|date|after_or_equal:issued_on',
            'liters' => 'nullable|numeric|min:0|max:99999',
            'taxed_km' => 'nullable|numeric|min:0|max:999999',
            'amount_excl_tax' => 'required|numeric|min:0|max:9999999',
            'vat_rate' => 'required|in:0,6,12,21',
            'vat_deductible' => 'boolean',
        ], [
            'due_on.after_or_equal' => 'L\'échéance ne peut pas précéder l\'émission.',
            'period_end.after_or_equal' => 'La fin de période ne peut pas précéder son début.',
        ]);

        $existe = PurchaseInvoice::where('supplier_name', $donnees['supplier_name'])
            ->where('reference', $donnees['reference'])
            ->exists();

        if ($existe) {
            return back()->withErrors([
                'reference' => 'Cette référence existe déjà pour ce fournisseur : la facture est probablement déjà encodée.',
            ]);
        }

        // La TVA se calcule au serveur ; rien a deduire quand le taux est nul.
        $ht = round((float) $donnees['amount_excl_tax'], 2);
        $taux = (float) $donnees['vat_rate'];
        $tva = round($ht * $taux / 100, 2);

        $achat = PurchaseInvoice::create([
            ...$donnees,
            'amount_excl_tax' => $ht,
            'vat_amount' => $tva,
            'amount_incl_tax' => round($ht + $tva, 2),
            'vat_deductible' => $taux > 0 && $request->boolean('vat_deductible'),
            'status' => 'TO_PAY',
        ]);

        ActivityLog::record(
            'purchase.created',
            'Facture fournisseur '.$achat->supplier_name.' '.$achat->reference.' encodée',
            $achat,
            ['vehicule' => $achat->vehicle_registration, 'ttc' => (float) $achat->amount_incl_tax],
        );

        return back()->with('success', 'Facture fournisseur encodée.');
    }

    public function markPaid(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        if ($purchaseInvoice->status !== 'TO_PAY') {
            return back()->with('error', 'Cette facture est déjà payée.');
        }

        $purchaseInvoice->update(['status' => 'PAID', 'paid_on' => now()->toDateString()]);

        ActivityLog::record(
            'purchase.paid',
            'Facture fournisseur '.$purchaseInvoice->supplier_name.' '.$purchaseInvoice->reference.' payée',
            $purchaseInvoice,
        );

        return back()->with('success', 'Facture marquée payée.');
    }

    /**
     * TVA collectee sur les ventes moins TVA deductible sur les achats,
     * mois par mois : la mecanique de la declaration periodique.
     */
    public function tva(): Response
    {
        $ventes = DB::table('invoices')
            ->where('status', '!=', 'DRAFT')
            ->selectRaw("to_char(issued_on, 'YYYY-MM') AS mois, sum(amount_excl_tax) AS ht, sum(vat_amount) AS collectee")
            ->groupBy('mois')
            ->get()
            ->keyBy('mois');

        $achats = DB::table('purchase_invoices')
            ->selectRaw("to_char(issued_on, 'YYYY-MM') AS mois, sum(amount_excl_tax) AS ht,
                sum(CASE WHEN vat_deductible THEN vat_amount ELSE 0 END) AS deductible")
            ->groupBy('mois')
            ->get()
            ->keyBy('mois');

        $lignes = collect($ventes->keys())
            ->merge($achats->keys())
            ->unique()
            ->sortDesc()
            ->values()
            ->map(function (string $mois) use ($ventes, $achats) {
                $date = Carbon::createFromFormat('Y-m', $mois);
                $collectee = (float) ($ventes[$mois]->collectee ?? 0);
                $deductible = (float) ($achats[$mois]->deductible ?? 0);

                return [
                    'mois' => $mois,
                    'libelle' => $date->locale('fr')->isoFormat('MMMM YYYY'),
                    'trimestre' => 'T'.$date->quarter.' '.$date->year,
                    'ventes_ht' => (float) ($ventes[$mois]->ht ?? 0),
                    'collectee' => $collectee,
                    'achats_ht' => (float) ($achats[$mois]->ht ?? 0),
                    'deductible' => $deductible,
                    'solde' => round($collectee - $deductible, 2),
                ];
            });

        return Inertia::render('Factures/Tva', [
            'lignes' => $lignes->all(),
            'totaux' => [
                'collectee' => round($lignes->sum('collectee'), 2),
                'deductible' => round($lignes->sum('deductible'), 2),
                'solde' => round($lignes->sum('solde'), 2),
            ],
        ]);
    }
}
