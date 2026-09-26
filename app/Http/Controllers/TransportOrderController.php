<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Mail\OrdreCree;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Support\Adresse;
use App\Support\Formats;
use App\Support\JoursFeries;
use App\Support\Localite;
use App\Support\OrderWorkflow;
use App\Support\Tarificateur;
use App\Support\Traductions;
use App\Support\TransitionRefusee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransportOrderController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user()->isDriver(), 403);

        $query = TransportOrder::with('client:id,company_name')->orderBy('id', 'desc');

        if ($request->user()->cannot('view-all-orders')) {
            $query->where('client_id', $request->user()->client_id);
        }

        if ($request->filled('tracking')) {
            $query->where('tracking_number', 'ilike', '%'.$request->tracking.'%');
        }
        // La recherche globale (touche Entree) cherche comme ses
        // suggestions : numero, depart ou destination.
        if ($request->filled('q')) {
            $terme = '%'.$request->q.'%';
            $query->where(fn ($q) => $q
                ->where('tracking_number', 'ilike', $terme)
                ->orWhere('pickup_address', 'ilike', $terme)
                ->orWhere('delivery_address', 'ilike', $terme));
        }
        if ($request->filled('destination')) {
            $query->where('delivery_address', 'ilike', '%'.$request->destination.'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client')) {
            $query->whereHas('client', fn ($q) => $q->where('company_name', 'ilike', '%'.$request->client.'%'));
        }

        $orders = $query->with('invoiceLine.invoice:id,status')
            ->paginate(15)
            ->withQueryString();

        $orders->getCollection()->each->append('en_attente_de_paiement');

        return Inertia::render('TransportOrders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['tracking', 'client', 'destination', 'status', 'q']),
        ]);
    }

    public function show(Request $request, TransportOrder $transportOrder): Response
    {
        abort_unless($request->user()->can('view', $transportOrder), 404);

        $transportOrder->load([
            'client:id,company_name,city,country',
            'vehicle:registration,brand,model,vehicle_type,capacity_tonnes',
            'driver.user:id,first_name,last_name',
            'tariffGrid:id,label,zone,service_level,delivery_days', 'invoiceLine:id,invoice_id,transport_order_id',
            'invoiceLine.invoice:id,reference,status,due_on,paid_on,amount_incl_tax',
            'charges',
        ]);

        $facturees = InvoiceLine::whereIn('order_charge_id', $transportOrder->charges->pluck('id'))
            ->pluck('order_charge_id')
            ->all();

        // La relation driver est chargee pour composer le nom ci-dessous,
        // mais elle ne part pas avec l'ordre : elle porterait toute la
        // fiche du chauffeur, numero de permis, date de naissance, examen
        // medical et motif de sortie compris, jusque dans la page du client.
        $transportOrder->makeHidden('driver');

        return Inertia::render('TransportOrders/Show', [
            'order' => $transportOrder,
            'supplements' => $transportOrder->charges->map(fn ($c) => [
                'id' => $c->id,
                'libelle' => $c->label,
                'montant' => (float) $c->amount,
                'date' => $c->created_at->format('d/m/Y'),
                'facture' => in_array($c->id, $facturees, true),
            ])->all(),
            'peutAjouterSupplement' => $request->user()->can('plan-orders'),
            'chauffeur' => $transportOrder->driver?->user
                ? $transportOrder->driver->user->first_name.' '.$transportOrder->driver->user->last_name
                : null,
            'facture' => $transportOrder->invoiceLine?->invoice ? [
                'id' => $transportOrder->invoiceLine->invoice->id,
                'reference' => $transportOrder->invoiceLine->invoice->reference,
                'etat' => $transportOrder->invoiceLine->invoice->estEnRetard()
                    ? Traductions::t('statut.en_retard', 'En retard')
                    : match ($transportOrder->invoiceLine->invoice->status) {
                        'DRAFT' => Traductions::t('facture.brouillon', Invoice::STATUTS['DRAFT']),
                        'SENT' => Traductions::t('statut.envoyee', Invoice::STATUTS['SENT']),
                        'PAID' => Traductions::t('statut.payee', Invoice::STATUTS['PAID']),
                        'OVERDUE' => Traductions::t('statut.en_retard', Invoice::STATUTS['OVERDUE']),
                        'CREDITED' => Traductions::t('facture.annulee_avoir', Invoice::STATUTS['CREDITED']),
                        default => Invoice::STATUTS[$transportOrder->invoiceLine->invoice->status] ?? $transportOrder->invoiceLine->invoice->status,
                    },
                'ttc' => (float) $transportOrder->invoiceLine->invoice->amount_incl_tax,
                'echeance' => $transportOrder->invoiceLine->invoice->due_on->format('d/m/Y'),
                'payee_le' => $transportOrder->invoiceLine->invoice->paid_on?->format('d/m/Y'),
            ] : null,
            // Seul le client de l'expedition l'annule lui-meme ; le personnel
            // passe par la planification, sans indemnite.
            'annulation' => $request->user()->can('cancel', $transportOrder)
                && $transportOrder->fraisAnnulation() !== null ? [
                    'frais' => $transportOrder->fraisAnnulation(),
                    'taux' => TransportOrder::TAUX_ANNULATION,
                    'minimum' => TransportOrder::MINIMUM_ANNULATION,
                ] : null,
        ]);
    }

    public function annuler(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        abort_unless($request->user()->can('cancel', $transportOrder), 404);

        $donnees = $request->validate([
            'frais' => 'required|numeric|min:0',
        ]);

        $frais = $transportOrder->fraisAnnulation();

        if ($frais === null) {
            return back()->with('error', Traductions::t('annulation.trop_tard', 'La marchandise est déjà chargée : l\'expédition ne peut plus être annulée en ligne. Contactez-nous.'));
        }

        // Le client confirme le montant qu'il a vu. Si un camion a ete
        // affecte entre-temps, l'indemnite a change : on ne l'impose pas
        // sans le lui montrer.
        if (abs($frais - (float) $donnees['frais']) > 0.001) {
            return back()->with('error', Traductions::t('annulation.montant_change', 'Un véhicule vient d\'être affecté à cette expédition : son annulation coûte désormais :montant HT. Vérifiez le montant et confirmez à nouveau.', [
                'montant' => Formats::montant($frais),
            ]));
        }

        $ancien = $transportOrder->status;

        // L'indemnite a ete calculee sur ce statut : si le chauffeur a
        // charge entre-temps, l'annulation est refusee au lieu de passer
        // avec un montant faux.
        try {
            OrderWorkflow::annuler($transportOrder, OrderStatus::from($ancien), $request->user()->id, $frais);
        } catch (TransitionRefusee) {
            return back()->with('error', Traductions::t('msg.ordre_etat_change', 'Cet ordre vient de changer d\'état : actualisez la page.'));
        }

        ActivityLog::record(
            'order.cancelled_by_client',
            'Annulation de l\'ordre '.$transportOrder->tracking_number.' par le client'
                .($frais > 0 ? ', indemnité de '.number_format($frais, 2, ',', ' ').' € HT' : ', sans frais'),
            $transportOrder,
            ['avant' => $ancien, 'indemnite' => $frais, 'chauffeur_id' => $transportOrder->driver_id],
        );

        return back()->with('success', $frais > 0
            ? Traductions::t('annulation.faite_payante', 'Expédition annulée. L\'indemnité de :montant HT figurera sur votre prochaine facture.', [
                'montant' => Formats::montant($frais),
            ])
            : Traductions::t('annulation.faite_gratuite', 'Expédition annulée, sans frais.'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{pickup: object, delivery: object}|string
     */
    private function verifierLesPoints(array $data): array|string
    {
        $ecartMax = 30;

        $points = [
            'pickup' => Localite::coordonnees(Adresse::localite($data['pickup_address']), 'BE'),
            'delivery' => Localite::coordonnees(Adresse::localite($data['delivery_address']), $data['delivery_country']),
        ];

        foreach ($points as $cle => $point) {
            if ($point === null) {
                return $cle === 'pickup'
                    ? Traductions::t('msg.adresse_enlevement_inconnue', 'L\'adresse d\'enlèvement ne correspond à aucune localité connue. Choisissez-la dans la liste de suggestions.')
                    : Traductions::t('msg.adresse_livraison_inconnue', 'L\'adresse de livraison ne correspond à aucune localité connue. Choisissez-la dans la liste de suggestions.');
            }

            $ecart = Tarificateur::distanceVol(
                (float) $point->lat,
                (float) $point->lng,
                (float) $data[$cle.'_lat'],
                (float) $data[$cle.'_lng'],
            );

            if ($ecart > $ecartMax) {
                return $cle === 'pickup'
                    ? Traductions::t('msg.adresse_enlevement_ecart', 'L\'adresse d\'enlèvement ne correspond pas au point transmis. Resélectionnez-la dans la liste de suggestions.')
                    : Traductions::t('msg.adresse_livraison_ecart', 'L\'adresse de livraison ne correspond pas au point transmis. Resélectionnez-la dans la liste de suggestions.');
            }
        }

        return ['pickup' => $points['pickup'], 'delivery' => $points['delivery']];
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('create', TransportOrder::class), 403);

        return Inertia::render('TransportOrders/Create', [
            'tariffGrids' => TariffGrid::where('is_active', true)->get(['id', 'label', 'zone', 'delivery_days', 'service_level']),
        ]);
    }

    /**
     * Le prix de chaque formule de la zone, calcule par le serveur : le
     * formulaire affiche exactement le prix qui sera enregistre, sans
     * refaire la formule dans le navigateur.
     */
    public function estimation(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('create', TransportOrder::class), 403);

        $data = $request->validate([
            'delivery_country' => 'required|string|size:2|exists:tariff_grids,zone',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'weight' => 'nullable|numeric|min:1|max:44000',
            'is_hazardous' => 'boolean',
        ]);

        $km = Tarificateur::distanceRoutiere(
            (float) $data['pickup_lat'],
            (float) $data['pickup_lng'],
            (float) $data['delivery_lat'],
            (float) $data['delivery_lng'],
        );

        $grilles = TariffGrid::where('zone', $data['delivery_country'])->where('is_active', true)->get();
        $poids = $data['weight'] ?? null;

        $prix = $poids === null
            ? []
            : Tarificateur::parFormule($grilles, $km, (float) $poids, $data['delivery_country'], $request->boolean('is_hazardous'));

        return response()->json([
            'distance_km' => round($km, 1),
            'prix' => (object) $prix,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', TransportOrder::class), 403);

        $data = $request->validate([
            'pickup_address' => 'required|string|max:255',
            'delivery_address' => 'required|string|max:255',
            'delivery_country' => 'required|string|size:2|exists:tariff_grids,zone',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'weight' => 'required|numeric|min:1|max:44000',
            'goods_type' => 'required|in:'.implode(',', TransportOrder::MARCHANDISES),
            'is_hazardous' => 'boolean',
            'needs_tail_lift' => 'boolean',
            'priority' => 'required|in:LOW,NORMAL,HIGH,URGENT',
            'pickup_date' => 'nullable|date|after_or_equal:now',
            'requested_delivery_date' => 'nullable|date|after_or_equal:today',
            'tariff_grid_id' => ['required', Rule::exists('tariff_grids', 'id')->where('is_active', true)],
            'special_instructions' => 'nullable|string',
        ], [
            'pickup_lat.required' => Traductions::t('msg.choisir_adresse_depart', 'Sélectionne une adresse de départ dans la liste de suggestions.'),
            'pickup_lng.required' => Traductions::t('msg.choisir_adresse_depart', 'Sélectionne une adresse de départ dans la liste de suggestions.'),
            'delivery_lat.required' => Traductions::t('msg.choisir_adresse_destination', 'Sélectionne une adresse de destination dans la liste de suggestions.'),
            'delivery_lng.required' => Traductions::t('msg.choisir_adresse_destination', 'Sélectionne une adresse de destination dans la liste de suggestions.'),
            'delivery_country.required' => Traductions::t('msg.choisir_adresse_destination', 'Sélectionne une adresse de destination dans la liste de suggestions.'),
            'pickup_date.after_or_equal' => Traductions::t('msg.enlevement_passe', 'La date d\'enlèvement ne peut pas être dans le passé.'),
            'requested_delivery_date.after_or_equal' => Traductions::t('msg.livraison_passee', 'La date de livraison souhaitée ne peut pas être dans le passé.'),
        ]);

        $grid = TariffGrid::find($data['tariff_grid_id']);

        if ($grid->zone !== $data['delivery_country']) {
            return back()->withErrors(['tariff_grid_id' => Traductions::t('msg.grille_pays', 'La grille tarifaire ne correspond pas au pays de destination.')]);
        }

        if (! empty($data['pickup_date'])) {
            $enlevement = Carbon::parse($data['pickup_date']);

            if (JoursFeries::chome($enlevement)) {
                return back()->withErrors([
                    'pickup_date' => Traductions::t(
                        'msg.enlevement_jour_chome',
                        'Aucun enlèvement le :jour. Le premier jour ouvrable est le :date.',
                        [
                            'jour' => $enlevement->isSunday()
                                ? Traductions::t('msg.dimanche', 'dimanche')
                                : mb_strtolower((string) JoursFeries::nom($enlevement)),
                            'date' => JoursFeries::prochainJourOuvrable($enlevement)->format('d/m/Y'),
                        ],
                    ),
                ]);
            }
        }

        if (! empty($data['requested_delivery_date'])) {
            $depart = ! empty($data['pickup_date']) ? Carbon::parse($data['pickup_date'])->startOfDay() : now()->startOfDay();
            $delai = $depart->diffInDays(Carbon::parse($data['requested_delivery_date'])->startOfDay(), false);

            if ($delai <= 2) {
                $data['priority'] = 'URGENT';
            }

            if ($grid->delivery_days > $delai) {
                return back()->withErrors(['tariff_grid_id' => Traductions::t('msg.formule_trop_lente', 'La formule choisie ne permet pas de livrer à la date demandée.')]);
            }
        }

        $reference = $this->verifierLesPoints($data);

        if (is_string($reference)) {
            return back()->withErrors(['delivery_address' => $reference])->withInput();
        }

        // Les points transmis ont ete verifies ci-dessus (a moins de 30 km
        // de leur localite) : la distance se calcule entre eux, comme le
        // formulaire l'a fait pour afficher le prix. Calculee entre les
        // centres des localites, elle donnait un prix enregistre different
        // du prix annonce.
        $distanceKm = Tarificateur::distanceRoutiere(
            (float) $data['pickup_lat'],
            (float) $data['pickup_lng'],
            (float) $data['delivery_lat'],
            (float) $data['delivery_lng'],
        );
        $hazardous = $request->boolean('is_hazardous');

        $order = TransportOrder::deposer([
            'client_id' => $request->user()->client_id,
            'pickup_address' => $data['pickup_address'],
            'delivery_address' => $data['delivery_address'],
            'pickup_lat' => $data['pickup_lat'],
            'pickup_lng' => $data['pickup_lng'],
            'delivery_lat' => $data['delivery_lat'],
            'delivery_lng' => $data['delivery_lng'],
            'weight' => $data['weight'],
            'goods_type' => $data['goods_type'],
            'is_hazardous' => $hazardous,
            'needs_tail_lift' => $request->boolean('needs_tail_lift'),
            'priority' => $data['priority'],
            'pickup_date' => $data['pickup_date'] ?? null,
            'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
            'tariff_grid_id' => $data['tariff_grid_id'],
            'special_instructions' => $data['special_instructions'] ?? null,
            'status' => 'PENDING',
            'created_date' => now(),
            'distance_km' => (int) round($distanceKm),
            'estimated_cost' => Tarificateur::cout($grid, $distanceKm, (float) $data['weight'], $data['delivery_country'], $hazardous),
            'tracking_code' => TransportOrder::prochainCode(),
        ]);

        ActivityLog::record(
            'order.created',
            'Création de l\'ordre '.$order->tracking_number,
            $order,
            [
                'trajet' => $order->pickup_address.' → '.$order->delivery_address,
                'poids_kg' => (float) $order->weight,
                'distance_km' => $order->distance_km,
                'formule' => $grid->label,
                'prix' => (float) $order->estimated_cost,
            ],
        );

        try {
            Mail::to($request->user()->email)->send(new OrdreCree($order, $request->user(), $grid));
            $confirmation = Traductions::t('msg.ordre_cree', 'Ordre créé : :numero — le code de suivi vous a été envoyé par e-mail.', ['numero' => $order->tracking_number]);
        } catch (\Throwable $e) {
            report($e);
            $confirmation = Traductions::t('msg.ordre_cree_sans_courriel', 'Ordre créé : :numero — l\'e-mail de confirmation n\'a pas pu être envoyé.', ['numero' => $order->tracking_number]);
        }

        return redirect()->route('transport-orders.index')
            ->with('success', $confirmation);
    }
}
