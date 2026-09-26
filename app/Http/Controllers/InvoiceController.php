<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Avoirs;
use App\Support\Encaissement;
use App\Support\EnvoiFacture;
use App\Support\FacturePdf;
use App\Support\FactureUbl;
use App\Support\Formats;
use App\Support\LigneFacture;
use App\Support\Pays;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $request->user();

        abort_if($utilisateur->isDriver(), 403);

        $estClient = $utilisateur->cannot('view-all-orders');

        $perimetre = fn () => Invoice::query()
            ->when($estClient, fn ($q) => $q->where('client_id', $utilisateur->id));
        $aPayer = fn () => $perimetre()->where('type', Invoice::FACTURE)->where('status', 'SENT');

        return Inertia::render('Factures/Index', [
            'factures' => $perimetre()
                ->with('client:id,company_name')
                ->orderByDesc('issued_on')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Invoice $facture) => [
                    'id' => $facture->id,
                    'reference' => $facture->reference,
                    'client' => $facture->client?->company_name,
                    'periode' => $facture->period_start->locale(app()->getLocale())->isoFormat('MMMM YYYY'),
                    'emise_le' => $facture->issued_on->format('d/m/Y'),
                    'echeance' => $facture->due_on->format('d/m/Y'),
                    'ttc' => (float) $facture->amount_incl_tax,
                    'avoir' => $facture->estAvoir(),
                    'autoliquidation' => (bool) $facture->reverse_charge,
                    'etat' => $this->etat($facture),
                    'payee_le' => $facture->paid_on?->format('d/m/Y'),
                    'peut_payer' => $estClient && $facture->estAPayer(),
                ]),
            'cartes' => [
                'du' => round((float) $aPayer()->sum('amount_incl_tax')
                    - (float) Payment::whereIn('invoice_id', $aPayer()->select('id'))->sum('amount'), 2),
                'paye' => (float) $perimetre()->where('type', Invoice::FACTURE)->where('status', 'PAID')->sum('amount_incl_tax'),
                'en_retard' => $aPayer()->where('due_on', '<', now())->count(),
            ],
            'colonnePaiement' => $estClient && $aPayer()->exists(),
            'peutGererAchats' => $utilisateur->can('control-payments'),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $utilisateur = $request->user();

        $this->autoriserLecture($utilisateur, $invoice);

        $invoice->load([
            'lines.transportOrder:id,tracking_number',
            'payments',
            'creditedInvoice:id,reference',
            'creditNote:id,reference,credited_invoice_id',
        ]);

        $gestion = $utilisateur->can('control-payments');
        $solde = $invoice->solde();

        return Inertia::render('Factures/Show', [
            'facture' => [
                'id' => $invoice->id,
                'reference' => $invoice->reference,
                'avoir' => $invoice->estAvoir(),
                'motif_avoir' => $invoice->credit_reason,
                'facture_annulee' => $invoice->creditedInvoice ? ['id' => $invoice->creditedInvoice->id, 'reference' => $invoice->creditedInvoice->reference] : null,
                'annulee_par' => $invoice->creditNote ? ['id' => $invoice->creditNote->id, 'reference' => $invoice->creditNote->reference] : null,
                'etat' => $this->etat($invoice),
                'periode_debut' => $invoice->period_start->format('d/m/Y'),
                'periode_fin' => $invoice->period_end->format('d/m/Y'),
                'emise_le' => $invoice->issued_on->format('d/m/Y'),
                'echeance' => $invoice->due_on->format('d/m/Y'),
                'payee_le' => $invoice->paid_on?->format('d/m/Y'),
                'envoyee_le' => $invoice->sent_at?->format(Traductions::t('msg.format_date_heure', 'd/m/Y à H\hi')),
                'ht' => (float) $invoice->amount_excl_tax,
                'taux' => (float) $invoice->vat_rate,
                'tva' => (float) $invoice->vat_amount,
                'ttc' => (float) $invoice->amount_incl_tax,
                'paye' => $invoice->montantPaye(),
                'solde' => $solde,
                'autoliquidation' => (bool) $invoice->reverse_charge,
                'categorie_tva' => $invoice->vat_category,
                'communication' => $invoice->payment_reference,
                'iban' => config('entreprise.iban'),
                'qr' => FacturePdf::qr($invoice),
                'client' => [
                    'nom' => $invoice->buyer_name,
                    'tva' => $invoice->buyer_vat_number,
                    'adresse' => $invoice->buyer_address,
                    'localite' => trim($invoice->buyer_postal_code.' '.$invoice->buyer_city),
                    'pays' => $invoice->buyer_country ? Pays::libelle($invoice->buyer_country) : null,
                ],
                'lignes' => $invoice->lines->map(fn ($ligne) => [
                    'id' => $ligne->id,
                    'ordre_id' => $ligne->transport_order_id,
                    'numero' => $ligne->transportOrder?->tracking_number,
                    'nature' => $ligne->kind,
                    'description' => LigneFacture::libelle($ligne->description),
                    'ht' => (float) $ligne->amount_excl_tax,
                ])->all(),
                'paiements' => $invoice->payments->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'date' => $p->paid_on->format('d/m/Y'),
                    'montant' => (float) $p->amount,
                    'methode' => $p->method,
                ])->all(),
            ],
            'peutMarquerPayee' => $gestion && $invoice->estAPayer(),
            'peutEmettreAvoir' => $gestion && $invoice->estAPayer() && $invoice->montantPaye() == 0.0,
            'peutEnvoyer' => $gestion && $invoice->status !== 'DRAFT',
            'peutPayerEnLigne' => $invoice->estAPayer()
                && ! empty(config('services.stripe.secret'))
                && $utilisateur->cannot('view-all-orders')
                && $invoice->client_id === $utilisateur->id,
        ]);
    }

    private function etat(Invoice $facture): string
    {
        return $facture->estEnRetard() ? 'OVERDUE' : $facture->status;
    }

    public function envoyer(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === 'DRAFT') {
            return back()->with('error', Traductions::t('msg.brouillon_non_envoyable', 'Un brouillon ne s\'envoie pas.'));
        }

        $destinataire = EnvoiFacture::envoyer($invoice);

        return $destinataire === null
            ? back()->with('error', Traductions::t('msg.courriel_echec', 'Le courriel n\'a pas pu partir. Réessayez dans quelques minutes.'))
            : back()->with('success', Traductions::t('msg.facture_envoyee', 'Facture :reference envoyée à :destinataire.', [
                'reference' => $invoice->reference,
                'destinataire' => $destinataire,
            ]));
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        if (! $invoice->estAPayer()) {
            return back()->with('error', Traductions::t('msg.facture_pas_en_attente', 'Cette facture n\'est pas en attente de paiement.'));
        }

        $donnees = $request->validate([
            'montant' => 'required|numeric|min:0.01|max:'.$invoice->solde(),
            'date' => 'required|date|before_or_equal:today|after_or_equal:'.$invoice->issued_on->toDateString(),
            'methode' => 'required|in:TRANSFER,CASH,OTHER',
        ], [
            'montant.max' => Traductions::t('msg.paiement_trop_eleve', 'Le montant dépasse le solde dû (:solde).', ['solde' => Formats::montant($invoice->solde())]),
        ]);

        $paiement = Encaissement::enregistrer(
            $invoice,
            (float) $donnees['montant'],
            Carbon::parse($donnees['date']),
            $donnees['methode'],
            auteur: $request->user()->id,
        );

        if ($paiement === null) {
            return back()->with('error', Traductions::t('msg.facture_pas_en_attente', 'Cette facture n\'est pas en attente de paiement.'));
        }

        ActivityLog::record(
            'invoice.paid',
            'Paiement de '.number_format((float) $paiement->amount, 2, ',', ' ').' € enregistré pour '.$invoice->reference,
            $invoice,
            [
                'montant' => (string) $paiement->amount,
                'methode' => $paiement->method,
                'solde' => (string) $invoice->solde(),
                'client_id' => $invoice->client_id,
            ],
        );

        return back()->with('success', $invoice->status === 'PAID'
            ? Traductions::t('msg.paiement_enregistre', 'Paiement enregistré.')
            : Traductions::t('msg.paiement_partiel', 'Paiement partiel enregistré : reste :solde à payer.', ['solde' => Formats::montant($invoice->solde())]));
    }

    public function avoir(Request $request, Invoice $invoice): RedirectResponse
    {
        $donnees = $request->validate([
            'motif' => 'required|string|min:5|max:500',
            'refacturer' => 'boolean',
        ]);

        try {
            ['avoir' => $avoir, 'nouvelle' => $nouvelle] = Avoirs::emettre(
                $invoice,
                $donnees['motif'],
                $request->boolean('refacturer'),
                $request->user()->id,
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::record(
            'invoice.credited',
            'Avoir '.$avoir->reference.' émis pour '.$invoice->reference.' : '.$donnees['motif'],
            $invoice,
            ['avoir' => $avoir->reference, 'nouvelle_facture' => $nouvelle?->reference, 'motif' => $donnees['motif']],
        );

        return redirect()->route('invoices.show', $avoir)->with('success', $nouvelle
            ? Traductions::t('msg.avoir_refacture', 'Avoir :avoir émis ; les prestations sont refacturées sur :facture.', ['avoir' => $avoir->reference, 'facture' => $nouvelle->reference])
            : Traductions::t('msg.avoir_emis', 'Avoir :avoir émis.', ['avoir' => $avoir->reference]));
    }

    public function pdf(Request $request, Invoice $invoice): \Illuminate\Http\Response
    {
        $this->autoriserLecture($request->user(), $invoice);

        return response(FacturePdf::contenu($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->reference.'.pdf"',
        ]);
    }

    public function ubl(Request $request, Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $this->autoriserLecture($request->user(), $invoice);

        $invoice->load(['lines', 'creditedInvoice:id,reference,issued_on']);

        return response(FactureUbl::pour($invoice), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$invoice->reference.'.xml"',
        ]);
    }

    private function autoriserLecture(User $utilisateur, Invoice $invoice): void
    {
        abort_if($utilisateur->isDriver(), 403);
        abort_if(
            $utilisateur->cannot('view-all-orders') && $invoice->client_id !== $utilisateur->id,
            404,
        );
    }
}
