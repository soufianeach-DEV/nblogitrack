<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Mail\OrdreCree;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\Adresse;
use App\Support\Chronologie;
use App\Support\Formats;
use App\Support\FretRetour;
use App\Support\GeocodageIndisponible;
use App\Support\JoursFeries;
use App\Support\Localite;
use App\Support\OrderWorkflow;
use App\Support\Tarificateur;
use App\Support\Traductions;
use App\Support\Trajet;
use App\Support\TransitionRefusee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $query->whereContient('tracking_number', (string) $request->tracking);
        }
        // La recherche globale (touche Entree) cherche comme ses
        // suggestions : numero, depart ou destination.
        if ($request->filled('q')) {
            $terme = (string) $request->q;
            $query->where(fn ($q) => $q
                ->whereContient('tracking_number', $terme)
                ->orWhereContient('pickup_address', $terme)
                ->orWhereContient('delivery_address', $terme)
                // Les suggestions proposent aussi les entreprises, par nom ou
                // par numero de TVA : la touche Entree les cherche de meme.
                ->orWhereHas('client', fn ($c) => $c
                    ->whereContient('company_name', $terme)
                    ->orWhereContient('vat_number', $terme)));
        }
        if ($request->filled('destination')) {
            $query->whereContient('delivery_address', (string) $request->destination);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client')) {
            $query->whereHas('client', fn ($q) => $q->whereContient('company_name', (string) $request->client));
        }
        // L'alerte du tableau de bord renvoie ici : meme critere, meme nombre.
        if ($request->boolean('retard')) {
            $query->where(DashboardController::expeditionsEnRetard());
        }

        $orders = $query->with('invoiceLine.invoice:id,status')
            ->paginate(15)
            ->withQueryString();

        $orders->getCollection()->each->append('en_attente_de_paiement');

        return Inertia::render('TransportOrders/Index', [
            'orders' => $orders,
            'filters' => [
                ...$request->only(['tracking', 'client', 'destination', 'status', 'q']),
                'retard' => $request->boolean('retard') ? '1' : null,
            ],
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

        $transportOrder->setAttribute('formule', $transportOrder->formule());
        $transportOrder->setAttribute('delai_promis', $transportOrder->delaiPromis());

        return Inertia::render('TransportOrders/Show', [
            'order' => $transportOrder,
            'supplements' => $transportOrder->charges->map(fn ($c) => [
                'id' => $c->id,
                'libelle' => $c->label,
                'montant' => (float) $c->amount,
                'date' => $c->created_at->format('d/m/Y'),
                'facture' => in_array($c->id, $facturees, true),
            ])->all(),
            // Une expedition annulee sans indemnite ne sera jamais facturee :
            // on n'y ajoute plus rien, mais on peut toujours retirer un
            // supplement pose avant l'annulation.
            'peutAjouterSupplement' => $request->user()->can('plan-orders')
                && ! ($transportOrder->status === 'CANCELLED' && ! ($transportOrder->cancellation_fee > 0)),
            'peutRetirerSupplement' => $request->user()->can('plan-orders'),
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
     * Chaque point transmis doit etre a moins de 30 km de sa localite, dans
     * le pays declare, et ce pays doit etre celui ecrit dans l'adresse : on
     * ne change pas de grille en mentant sur le pays.
     *
     * @param  array<string, mixed>  $data
     * @return array{champ: string, message: string}|null
     */
    private function verifierLesPoints(array $data): ?array
    {
        $ecartMax = 30;
        $paysEnlevement = $data['pickup_country'] ?? 'BE';

        if (! Adresse::paysCoherent($data['pickup_address'], $paysEnlevement, $paysEnlevement !== 'BE')) {
            return ['champ' => 'pickup_address', 'message' => Traductions::t('msg.pays_adresse_incoherent', 'Le pays de l\'adresse ne correspond pas au pays choisi. Resélectionnez l\'adresse dans la liste.')];
        }

        if (! Adresse::paysCoherent($data['delivery_address'], $data['delivery_country'], false)) {
            return ['champ' => 'delivery_address', 'message' => Traductions::t('msg.pays_adresse_incoherent', 'Le pays de l\'adresse ne correspond pas au pays choisi. Resélectionnez l\'adresse dans la liste.')];
        }

        foreach (['pickup' => $paysEnlevement, 'delivery' => $data['delivery_country']] as $cle => $pays) {
            $champ = $cle.'_address';

            try {
                $point = Localite::coordonnees(
                    Adresse::localite($data[$champ]),
                    $pays,
                    (float) $data[$cle.'_lat'],
                    (float) $data[$cle.'_lng'],
                );
            } catch (GeocodageIndisponible) {
                return ['champ' => $champ, 'message' => Traductions::t('msg.verification_adresse_indisponible', 'La vérification de l\'adresse est momentanément indisponible. Réessayez dans quelques minutes.')];
            }

            if ($point === null) {
                return ['champ' => $champ, 'message' => $cle === 'pickup'
                    ? Traductions::t('msg.adresse_enlevement_inconnue', 'L\'adresse d\'enlèvement ne correspond à aucune localité connue. Choisissez-la dans la liste de suggestions.')
                    : Traductions::t('msg.adresse_livraison_inconnue', 'L\'adresse de livraison ne correspond à aucune localité connue. Choisissez-la dans la liste de suggestions.')];
            }

            $ecart = Tarificateur::distanceVol((float) $point->lat, (float) $point->lng, (float) $data[$cle.'_lat'], (float) $data[$cle.'_lng']);

            if ($ecart > $ecartMax) {
                return ['champ' => $champ, 'message' => $cle === 'pickup'
                    ? Traductions::t('msg.adresse_enlevement_ecart', 'L\'adresse d\'enlèvement ne correspond pas au point transmis. Resélectionnez-la dans la liste de suggestions.')
                    : Traductions::t('msg.adresse_livraison_ecart', 'L\'adresse de livraison ne correspond pas au point transmis. Resélectionnez-la dans la liste de suggestions.')];
            }
        }

        return null;
    }

    /**
     * Pourquoi ce trajet ne se commande pas en ligne : pays d'enlevement
     * sur devis ou non desservi, trajet entre deux pays etrangers, ile.
     *
     * @return array{champ: string, message: string}|null
     */
    public static function refusDuTrajet(Trajet $trajet, ?string $adresseEnlevement): ?array
    {
        if (! Trajet::enLigne($trajet->depart)) {
            return ['champ' => 'pickup_address', 'message' => (string) $trajet->refus()];
        }

        if ($trajet->zone() === null) {
            return ['champ' => 'delivery_address', 'message' => (string) $trajet->refus()];
        }

        if ($trajet->depart !== 'BE' && $adresseEnlevement !== null
            && Trajet::codePostalExclu($trajet->depart, Adresse::codePostal($adresseEnlevement))) {
            return ['champ' => 'pickup_address', 'message' => Traductions::t('msg.enlevement_ile', 'Les îles et territoires hors TVA (Corse, Baléares, Canaries, Sardaigne, Sicile, Madère…) se traitent sur devis.')];
        }

        return null;
    }

    /**
     * La date d'enlevement saisie est a l'heure locale du pays
     * d'enlevement ; l'application la garde a l'heure de Bruxelles.
     */
    public static function enlevementABruxelles(?string $saisie, string $pays): ?Carbon
    {
        if ($saisie === null || $saisie === '') {
            return null;
        }

        return Carbon::parse($saisie, Trajet::fuseau($pays))->setTimezone(config('app.timezone'));
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('create', TransportOrder::class), 403);

        return Inertia::render('TransportOrders/Create', [
            'tariffGrids' => TariffGrid::where('is_active', true)->get(['id', 'label', 'zone', 'delivery_days', 'service_level']),
            'marchandisesAdr' => TransportOrder::MARCHANDISES_ADR,
            'poidsMax' => Vehicle::chargeUtileMaxKg(),
            'volumeMax' => Vehicle::volumeMaxM3(),
            'flotte' => Vehicle::profils(),
            'paysEnlevement' => config('fret.pays_enlevement'),
            'paysEnlevementDevis' => config('fret.pays_enlevement_devis'),
            'remiseFretRetour' => (int) round(Tarificateur::remise() * 100),
        ]);
    }

    /**
     * Le prix de chaque formule du trajet, calcule par le serveur : le
     * formulaire affiche exactement le prix qui sera enregistre, sans
     * refaire la formule dans le navigateur. Ne revele jamais le camion
     * qui justifie un tarif fret retour.
     */
    public function estimation(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('create', TransportOrder::class), 403);

        $data = $request->validate([
            'pickup_country' => 'nullable|string|size:2',
            'pickup_address' => 'nullable|string|max:255',
            'delivery_country' => 'required|string|size:2|exists:tariff_grids,zone',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'pickup_date' => 'nullable|date',
            'weight' => 'nullable|numeric|min:1|max:44000',
            'volume' => 'nullable|numeric|min:0',
            'is_hazardous' => 'boolean',
            'needs_tail_lift' => 'boolean',
        ]);

        $pays = strtoupper($data['pickup_country'] ?? 'BE');
        $trajet = new Trajet($pays, $data['delivery_country']);

        if ($refus = self::refusDuTrajet($trajet, $data['pickup_address'] ?? null)) {
            return response()->json(['erreur' => $refus['message'], 'champ' => $refus['champ']], 422);
        }

        $km = Tarificateur::distanceRoutiere(
            (float) $data['pickup_lat'],
            (float) $data['pickup_lng'],
            (float) $data['delivery_lat'],
            (float) $data['delivery_lng'],
        );

        $poids = $data['weight'] ?? null;
        $premier = $pays === 'BE' ? null
            : FretRetour::premierEnlevement($pays, (float) $data['pickup_lat'], (float) $data['pickup_lng'], $data['pickup_address'] ?? null);
        $offre = null;

        // Au-dela de la charge utile de la flotte, la commande sera
        // refusee : pas de prix pour un envoi qu'on ne peut pas prendre.
        if ($poids !== null && (float) $poids <= Vehicle::chargeUtileMaxKg()) {
            $demande = new TransportOrder([
                'client_id' => $request->user()->client_id,
                'pickup_country' => $pays,
                'delivery_country' => strtoupper($data['delivery_country']),
                'pickup_lat' => $data['pickup_lat'],
                'pickup_lng' => $data['pickup_lng'],
                'delivery_lat' => $data['delivery_lat'],
                'delivery_lng' => $data['delivery_lng'],
                'pickup_address' => $data['pickup_address'] ?? '',
                'pickup_date' => self::enlevementABruxelles($data['pickup_date'] ?? null, $pays),
                'weight' => $poids,
                'volume' => $data['volume'] ?? null,
                'is_hazardous' => $request->boolean('is_hazardous'),
                'needs_tail_lift' => $request->boolean('needs_tail_lift'),
                'distance_km' => (int) round($km),
            ]);

            // Un enlevement avant le premier possible ne peut pas porter
            // de tarif fret retour : la commande le refusera.
            if ($premier !== null && $demande->pickup_date !== null && $demande->pickup_date->lt($premier)) {
                $demande->pickup_date = null;
            }

            $offre = Tarificateur::offre($trajet, $demande, $km);
        }

        return response()->json([
            'distance_km' => round($km, 1),
            'zone' => $trajet->zone(),
            'type' => $trajet->type(),
            'trajet' => $trajet->fleche(),
            'prix' => (object) ($offre['prix'] ?? []),
            'prix_ligne' => (object) ($offre['prix_ligne'] ?? []),
            'remises' => (object) ($offre['remises'] ?? []),
            'tarif' => $offre['base'] ?? 'LANE',
            'delais' => (object) ($offre['delais'] ?? $trajet->grilles()->mapWithKeys(fn (TariffGrid $g) => [$g->id => Tarificateur::delai($g, $km)])->all()),
            'premier_enlevement' => $premier === null ? null : [
                'local' => $premier->copy()->setTimezone(Trajet::fuseau($pays))->format('Y-m-d\TH:i'),
                'bruxelles' => $premier->format('Y-m-d\TH:i'),
                'fuseau_different' => Trajet::fuseau($pays) !== config('app.timezone')
                    && $premier->copy()->setTimezone(Trajet::fuseau($pays))->format('H:i') !== $premier->format('H:i'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', TransportOrder::class), 403);

        $data = $request->validate([
            'pickup_address' => 'required|string|max:255',
            'pickup_country' => 'nullable|string|size:2',
            'delivery_address' => 'required|string|max:255',
            'delivery_country' => 'required|string|size:2|exists:tariff_grids,zone',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'weight' => 'required|numeric|min:1|max:'.Vehicle::chargeUtileMaxKg(),
            'volume' => 'nullable|numeric|min:0.1|max:'.Vehicle::volumeMaxM3(),
            'goods_type' => 'required|in:'.implode(',', TransportOrder::MARCHANDISES),
            'is_hazardous' => [Rule::requiredIf(fn () => in_array($request->input('goods_type'), TransportOrder::MARCHANDISES_ADR, true)), 'nullable', 'boolean'],
            'needs_tail_lift' => 'boolean',
            'priority' => 'required|in:LOW,NORMAL,HIGH,URGENT',
            'pickup_date' => 'nullable|date|after_or_equal:now',
            'requested_delivery_date' => 'nullable|date|after_or_equal:today',
            'tariff_grid_id' => ['required', Rule::exists('tariff_grids', 'id')->where('is_active', true)],
            'special_instructions' => 'nullable|string',
            'shipper_name' => ['nullable', 'string', 'max:150', Rule::requiredIf(fn () => strtoupper((string) $request->input('pickup_country', 'BE')) !== 'BE')],
            'shipper_phone' => ['nullable', 'string', 'max:30', Rule::requiredIf(fn () => strtoupper((string) $request->input('pickup_country', 'BE')) !== 'BE')],
            'loading_reference' => 'nullable|string|max:60',
            'prix_annonce' => 'nullable|numeric|min:0',
        ], [
            'pickup_lat.required' => Traductions::t('msg.choisir_adresse_depart', 'Sélectionne une adresse de départ dans la liste de suggestions.'),
            'pickup_lng.required' => Traductions::t('msg.choisir_adresse_depart', 'Sélectionne une adresse de départ dans la liste de suggestions.'),
            'delivery_lat.required' => Traductions::t('msg.choisir_adresse_destination', 'Sélectionne une adresse de destination dans la liste de suggestions.'),
            'delivery_lng.required' => Traductions::t('msg.choisir_adresse_destination', 'Sélectionne une adresse de destination dans la liste de suggestions.'),
            'delivery_country.required' => Traductions::t('msg.choisir_adresse_destination', 'Sélectionne une adresse de destination dans la liste de suggestions.'),
            'pickup_date.after_or_equal' => Traductions::t('msg.enlevement_passe', 'La date d\'enlèvement ne peut pas être dans le passé.'),
            'requested_delivery_date.after_or_equal' => Traductions::t('msg.livraison_passee', 'La date de livraison souhaitée ne peut pas être dans le passé.'),
            'is_hazardous.required' => Traductions::t('msg.declaration_adr_requise', 'Pour ce type de marchandise, indiquez si l\'envoi est soumis à l\'ADR (matière dangereuse) ou non.'),
            'shipper_name.required' => Traductions::t('msg.expediteur_requis', 'Indiquez le nom et le téléphone de l\'expéditeur qui charge à l\'étranger.'),
            'shipper_phone.required' => Traductions::t('msg.expediteur_requis', 'Indiquez le nom et le téléphone de l\'expéditeur qui charge à l\'étranger.'),
            'weight.max' => Traductions::t('msg.poids_flotte', 'Aucun camion de notre flotte ne charge plus de :max t : demandez un devis.', [
                'max' => Formats::nombre(Vehicle::chargeUtileMaxKg() / 1000, 1),
            ]),
            'volume.max' => Traductions::t('msg.volume_flotte', 'Aucun camion de notre flotte ne charge plus de :max m³ : demandez un devis.', [
                'max' => Formats::nombre(Vehicle::volumeMaxM3(), 1),
            ]),
        ]);

        $data['pickup_country'] = strtoupper($data['pickup_country'] ?? 'BE');
        $data['delivery_country'] = strtoupper($data['delivery_country']);
        $trajet = new Trajet($data['pickup_country'], $data['delivery_country']);

        if ($refus = self::refusDuTrajet($trajet, $data['pickup_address'])) {
            return back()->withErrors([$refus['champ'] => $refus['message']])->withInput();
        }

        // Chaque limite prise a part ne suffit pas : un envoi ADR de 20 t
        // avec hayon doit trouver un camion qui reunit les trois.
        $volume = isset($data['volume']) ? (float) $data['volume'] : null;

        if (! Vehicle::peutPorter((float) $data['weight'], $volume, (bool) ($data['is_hazardous'] ?? false), (bool) ($data['needs_tail_lift'] ?? false))) {
            return back()->withErrors([
                'flotte' => Vehicle::refusFlotte((float) $data['weight'], $volume, (bool) ($data['is_hazardous'] ?? false), (bool) ($data['needs_tail_lift'] ?? false)),
            ]);
        }

        $grid = TariffGrid::find($data['tariff_grid_id']);

        if ($grid->zone !== $trajet->zone()) {
            return back()->withErrors(['tariff_grid_id' => Traductions::t('msg.grille_pays', 'La grille tarifaire ne correspond pas au trajet.')]);
        }

        $enlevement = self::enlevementABruxelles($data['pickup_date'] ?? null, $data['pickup_country']);
        $etranger = $data['pickup_country'] !== 'BE';
        $region = $etranger ? FretRetour::regionDe($data['pickup_country'], $data['pickup_address']) : null;

        if ($etranger) {
            // Un camion part du depot : il faut le temps d'y aller, repos
            // compris. La date sert aussi au tarif fret retour.
            $premier = FretRetour::premierEnlevement($data['pickup_country'], (float) $data['pickup_lat'], (float) $data['pickup_lng'], $data['pickup_address']);
            $local = $premier->copy()->setTimezone(Trajet::fuseau($data['pickup_country']));

            if ($enlevement === null) {
                return back()->withErrors(['pickup_date' => Traductions::t('msg.enlevement_etranger_date_requise', 'Hors de Belgique, la date d\'enlèvement est obligatoire (au plus tôt le :date).', ['date' => $local->format('d/m/Y H:i')])]);
            }

            if ($enlevement->lt($premier)) {
                return back()->withErrors(['pickup_date' => Traductions::t('msg.enlevement_etranger_trop_tot', 'Un camion ne peut pas être sur place avant le :date (heure locale) : route depuis Bruxelles et repos du chauffeur compris.', ['date' => $local->format('d/m/Y H:i')])]);
            }
        }

        if ($enlevement !== null && JoursFeries::chome($enlevement->copy()->setTimezone(Trajet::fuseau($data['pickup_country'])), $data['pickup_country'], $region)) {
            $jourLocal = $enlevement->copy()->setTimezone(Trajet::fuseau($data['pickup_country']));

            return back()->withErrors([
                'pickup_date' => Traductions::t(
                    'msg.enlevement_jour_chome',
                    'Aucun enlèvement le :jour. Le premier jour ouvrable est le :date.',
                    [
                        'jour' => $jourLocal->isSunday()
                            ? Traductions::t('msg.dimanche', 'dimanche')
                            : mb_strtolower((string) JoursFeries::nom($jourLocal, $data['pickup_country'], $region)),
                        'date' => JoursFeries::prochainJourOuvrable($jourLocal, $data['pickup_country'], $region)->format('d/m/Y'),
                    ],
                ),
            ]);
        }

        if ($erreur = $this->verifierLesPoints($data)) {
            return back()->withErrors([$erreur['champ'] => $erreur['message']])->withInput();
        }

        // Les points transmis ont ete verifies ci-dessus (a moins de 30 km
        // de leur localite) : la distance se calcule entre eux, comme le
        // formulaire l'a fait pour afficher le prix.
        $distanceKm = Tarificateur::distanceRoutiere(
            (float) $data['pickup_lat'],
            (float) $data['pickup_lng'],
            (float) $data['delivery_lat'],
            (float) $data['delivery_lng'],
        );

        if (! empty($data['requested_delivery_date'])) {
            $depart = $enlevement !== null ? $enlevement->copy()->startOfDay() : now()->startOfDay();
            $delai = $depart->diffInDays(Carbon::parse($data['requested_delivery_date'])->startOfDay(), false);

            if ($delai <= 2) {
                $data['priority'] = 'URGENT';
            }

            // Jamais moins que la route : 9 h de conduite par jour.
            if (Tarificateur::delai($grid, $distanceKm) > $delai) {
                return back()->withErrors(['tariff_grid_id' => Traductions::t('msg.formule_trop_lente', 'La formule choisie ne permet pas de livrer à la date demandée.')]);
            }
        }

        $hazardous = $request->boolean('is_hazardous');

        $demande = new TransportOrder([
            'client_id' => $request->user()->client_id,
            'pickup_country' => $data['pickup_country'],
            'delivery_country' => $data['delivery_country'],
            'pickup_address' => $data['pickup_address'],
            'pickup_lat' => $data['pickup_lat'],
            'pickup_lng' => $data['pickup_lng'],
            'delivery_lat' => $data['delivery_lat'],
            'delivery_lng' => $data['delivery_lng'],
            'pickup_date' => $enlevement,
            'weight' => $data['weight'],
            'volume' => $data['volume'] ?? null,
            'is_hazardous' => $hazardous,
            'needs_tail_lift' => $request->boolean('needs_tail_lift'),
            'distance_km' => (int) round($distanceKm),
        ]);

        // Sous verrou : deux clients ne consomment pas la meme place sur le
        // retour d'un camion, et le client ne paie jamais un prix qu'il n'a
        // pas vu.
        $resultat = DB::transaction(function () use ($trajet, $demande, $distanceKm, $grid, $data, $request, $hazardous, $enlevement) {
            DB::statement('select pg_advisory_xact_lock(?)', [FretRetour::VERROU]);

            $offre = Tarificateur::offre($trajet, $demande, $distanceKm);
            $prix = $offre['prix'][$grid->id];

            if (isset($data['prix_annonce']) && abs((float) $data['prix_annonce'] - $prix) > 0.01) {
                return ['erreur' => Traductions::t('msg.tarif_change', 'Le tarif vient de changer (:nouveau HT au lieu de :ancien). Vérifiez-le et confirmez à nouveau.', [
                    'nouveau' => Formats::montant($prix),
                    'ancien' => Formats::montant((float) $data['prix_annonce']),
                ])];
            }

            $approche = $data['pickup_country'] !== 'BE'
                ? (int) round($offre['porteuse'] !== null
                    ? FretRetour::approche($offre['porteuse'], (float) $data['pickup_lat'], (float) $data['pickup_lng'])
                    : Chronologie::kmDepuisDepot((float) $data['pickup_lat'], (float) $data['pickup_lng']))
                : null;

            $order = TransportOrder::deposer([
                'client_id' => $request->user()->client_id,
                'pickup_address' => $data['pickup_address'],
                'pickup_country' => $data['pickup_country'],
                'delivery_address' => $data['delivery_address'],
                'delivery_country' => $data['delivery_country'],
                'pickup_lat' => $data['pickup_lat'],
                'pickup_lng' => $data['pickup_lng'],
                'delivery_lat' => $data['delivery_lat'],
                'delivery_lng' => $data['delivery_lng'],
                'weight' => $data['weight'],
                'volume' => $data['volume'] ?? null,
                'goods_type' => $data['goods_type'],
                'is_hazardous' => $hazardous,
                'needs_tail_lift' => $request->boolean('needs_tail_lift'),
                'priority' => $data['priority'],
                'pickup_date' => $enlevement,
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'tariff_grid_id' => $data['tariff_grid_id'],
                'special_instructions' => $data['special_instructions'] ?? null,
                'shipper_name' => $data['shipper_name'] ?? null,
                'shipper_phone' => $data['shipper_phone'] ?? null,
                'loading_reference' => $data['loading_reference'] ?? null,
                'status' => 'PENDING',
                'created_date' => now(),
                'distance_km' => (int) round($distanceKm),
                'approche_km' => $approche,
                'estimated_cost' => $prix,
                'pricing_basis' => $offre['base'],
                'backhaul_order_id' => $offre['porteuse']?->id,
                'tracking_code' => TransportOrder::prochainCode(),
            ]);

            return ['ordre' => $order];
        });

        if (isset($resultat['erreur'])) {
            return back()->withErrors(['tariff_grid_id' => $resultat['erreur']])->withInput();
        }

        $order = $resultat['ordre'];

        ActivityLog::record(
            'order.created',
            'Création de l\'ordre '.$order->tracking_number,
            $order,
            [
                'trajet' => $order->pickup_address.' → '.$order->delivery_address,
                'trajet_pays' => $trajet->court(),
                'poids_kg' => (float) $order->weight,
                'distance_km' => $order->distance_km,
                'formule' => $grid->label,
                'prix' => (float) $order->estimated_cost,
                'tarif' => $order->pricing_basis,
                'fret_retour_de' => $order->porteuse?->tracking_number,
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
