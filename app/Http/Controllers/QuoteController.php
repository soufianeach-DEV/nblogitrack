<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\QuoteRequest;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Audience;
use App\Support\Chronologie;
use App\Support\FretRetour;
use App\Support\IdentifiantEntreprise;
use App\Support\Secteurs;
use App\Support\Tarificateur;
use App\Support\Traductions;
use App\Support\Trajet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteController extends Controller
{
    private const CHOIX = [
        // Valeurs rangees en base ; le libelle affiche vient des
        // traductions (devis.choix_...). Le cas le plus courant d'abord.
        'clients' => [
            'Nouvelle entreprise',
            'Client régulier / entreprise',
            'Intermédiaire / commissionnaire',
        ],
        'trajets' => [
            'National (Belgique)',
            'International (Union européenne)',
            'Import vers la Belgique',
            'Entre deux pays hors Belgique',
        ],
        'frequences' => [
            'Transport ponctuel',
            'Transport récurrent',
            'Contrat annuel',
        ],
        'flexibilites' => [
            'Date fixe',
            'Plus ou moins 1 jour',
            'Plus ou moins 3 jours',
            'Flexible',
        ],
        'vehicules' => [
            'À conseiller selon la marchandise',
            'Camionnette',
            'Porteur',
            'Semi-remorque',
            'Frigorifique',
        ],
        'assurances' => [
            'Standard',
            'Moins de 10 000 €',
            'De 10 000 à 50 000 €',
            'Plus de 50 000 €',
        ],
        'volumes' => [
            'Envoi ponctuel',
            '1 à 5 envois par mois',
            '6 à 20 envois par mois',
            '21 à 50 envois par mois',
            'Plus de 50 envois par mois',
        ],
        'marchandises' => [
            'Palettes', 'Mobilier', 'Matériel', 'Alimentaire', 'Frigorifique',
            'Textile', 'Électronique', 'Matériaux de construction', 'Chimie',
            'Automobile', 'Vrac', 'Colis',
        ],
    ];

    /** Le demandeur agit pour une autre entreprise. */
    public const POUR_UN_TIERS = 'Intermédiaire / commissionnaire';

    private const CHAMPS_CHOIX = [
        'customer_type' => 'clients',
        'trip_type' => 'trajets',
        'frequency' => 'frequences',
        'date_flexibility' => 'flexibilites',
        'vehicle_type' => 'vehicules',
        'insurance_value' => 'assurances',
        'monthly_volume' => 'volumes',
    ];

    /** Codes des listes fermees du formulaire (libelles traduits a l'ecran). */
    public const COLIS = ['palette_europe', 'palette_industrielle', 'demi_palette', 'colis', 'caisse', 'rouleau', 'vrac', 'autre'];

    public const ACCES = ['centre_ville', 'zone_basses_emissions', 'limite_tonnage', 'rue_etroite', 'sans_stationnement'];

    public const CRENEAUX = ['matin', 'apres_midi', 'journee'];

    public const CLASSES_ADR = ['1', '2', '3', '4.1', '4.2', '4.3', '5.1', '5.2', '6.1', '6.2', '7', '8', '9'];

    private const OPTIONS = [
        'Hayon élévateur' => 'devis.hayon',
        'Marchandise dangereuse (ADR)' => 'devis.adr',
        'Livraison express' => 'devis.express',
        'Preuve de livraison (e-CMR)' => 'devis.ecmr',
    ];

    public function create(): Response
    {
        return Inertia::render('Devis/Create', [
            'choix' => self::choixTraduits(),
            'listes' => [
                'colis' => self::COLIS,
                'acces' => self::ACCES,
                'creneaux' => self::CRENEAUX,
                'classesAdr' => self::CLASSES_ADR,
                'paysDouane' => QuoteRequest::PAYS_DOUANE,
                'secteurs' => Secteurs::groupes(app()->getLocale()),
            ],
        ]);
    }

    /**
     * Les listes dans la langue de l'utilisateur. Les valeurs sont
     * rangees en francais : store() fait le chemin inverse.
     *
     * @return array<string, list<string>>
     */
    private static function choixTraduits(): array
    {
        return collect(self::CHOIX)
            ->map(fn (array $valeurs, string $groupe) => array_map(
                fn (string $valeur) => $groupe === 'marchandises'
                    ? (string) Traductions::vocabulaire('marchandise', $valeur)
                    : Traductions::t('devis.choix_'.Traductions::cleDepuis($valeur), $valeur),
                $valeurs,
            ))
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $traduits = self::choixTraduits();

        foreach (self::CHAMPS_CHOIX as $champ => $groupe) {
            $rang = array_search($request->input($champ), $traduits[$groupe], true);

            if ($rang !== false) {
                $request->merge([$champ => self::CHOIX[$groupe][$rang]]);
            }
        }

        $douane = in_array(strtoupper((string) $request->input('pickup_country', 'BE')), QuoteRequest::PAYS_DOUANE, true)
            || in_array(strtoupper((string) $request->input('delivery_country', 'BE')), QuoteRequest::PAYS_DOUANE, true);

        $data = $request->validate([
            // Societe et facturation
            'company_name' => 'required|string|max:150',
            'contact_name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:20',
            'vat_number' => 'nullable|string|max:30',
            'customer_type' => 'required|in:'.implode(',', self::CHOIX['clients']),
            // Commissionnaire ou transitaire : l'entreprise pour qui il demande.
            'end_client_name' => [Rule::requiredIf(fn () => $request->input('customer_type') === self::POUR_UN_TIERS), 'nullable', 'string', 'max:150'],
            // Ce que le registre ne donne pas, le client le renseigne.
            'legal_form' => 'required|string|max:100',
            'sector' => ['required', Rule::in(Secteurs::valeurs())],
            // EORI : 2 lettres puis 15 caracteres au plus ; exige par la
            // douane pour la Suisse, le Royaume-Uni et la Norvege.
            'eori_number' => [$douane ? 'required' : 'nullable', 'string', 'regex:/^[A-Z]{2}[A-Z0-9]{1,15}$/'],
            'billing_street' => 'required|string|max:255',
            // L'Irlande n'impose pas l'Eircode dans une adresse.
            'billing_postal_code' => [strtoupper((string) $request->input('billing_country')) === 'IE' ? 'nullable' : 'required', 'string', 'max:20'],
            'billing_city' => 'required|string|max:120',
            'billing_country' => 'required|string|size:2',
            'correspondence_language' => 'required|in:fr,nl,en',

            // Contact
            'contact_function' => 'nullable|string|max:100',
            'mobile_phone' => 'nullable|string|max:30',
            'billing_email' => 'nullable|email|max:150',
            'preferred_channel' => 'required|in:phone,email',
            'callback_slot' => 'nullable|in:matin,apres_midi,indifferent',

            // Trajet
            'pickup_address' => 'required|string|max:255',
            'pickup_country' => 'nullable|string|size:2',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'delivery_address' => 'required|string|max:255',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'delivery_country' => 'required|string|size:2',

            'pickup_date' => 'required|date|after_or_equal:today',
            'delivery_date' => 'nullable|date|after_or_equal:pickup_date',
            'trip_type' => 'required|in:'.implode(',', self::CHOIX['trajets']),
            'frequency' => 'required|in:'.implode(',', self::CHOIX['frequences']),
            'date_flexibility' => 'required|in:'.implode(',', self::CHOIX['flexibilites']),

            // Sur place, a l'enlevement et a la livraison
            'pickup_contact_name' => 'nullable|string|max:150',
            'pickup_contact_phone' => 'nullable|string|max:30',
            'pickup_opening_hours' => 'nullable|string|max:100',
            'pickup_time_slot' => 'nullable|in:'.implode(',', self::CRENEAUX),
            'pickup_has_dock' => 'nullable|boolean',
            'pickup_appointment' => 'boolean',
            'pickup_access' => 'nullable|array',
            'pickup_access.*' => 'in:'.implode(',', self::ACCES),
            'pickup_access_notes' => 'nullable|string|max:255',
            'delivery_contact_name' => 'nullable|string|max:150',
            'delivery_contact_phone' => 'nullable|string|max:30',
            'delivery_opening_hours' => 'nullable|string|max:100',
            'delivery_time_slot' => 'nullable|in:'.implode(',', self::CRENEAUX),
            'delivery_has_dock' => 'nullable|boolean',
            'delivery_appointment' => 'boolean',
            'delivery_access' => 'nullable|array',
            'delivery_access.*' => 'in:'.implode(',', self::ACCES),
            'delivery_access_notes' => 'nullable|string|max:255',

            // Marchandise
            'goods_type' => 'required|string|max:100',
            'weight' => 'nullable|integer|min:0|max:44000',
            'volume' => 'nullable|string|max:60',
            'vehicle_type' => 'required|in:'.implode(',', self::CHOIX['vehicules']),
            'insurance_value' => 'required|in:'.implode(',', self::CHOIX['assurances']),
            'packages' => 'nullable|array|max:20',
            'packages.*.type' => 'required|in:'.implode(',', self::COLIS),
            'packages.*.quantite' => 'required|integer|min:1|max:999',
            'packages.*.longueur' => 'nullable|integer|min:1|max:1400',
            'packages.*.largeur' => 'nullable|integer|min:1|max:260',
            'packages.*.hauteur' => 'nullable|integer|min:1|max:300',
            'packages.*.poids_unitaire' => 'nullable|numeric|min:0|max:44000',
            'packages.*.empilable' => 'boolean',
            'declared_value' => 'nullable|numeric|min:0|max:10000000',

            'needs_tail_lift' => 'boolean',
            'is_hazardous' => 'boolean',
            'needs_express' => 'boolean',
            'needs_ecmr' => 'boolean',
            'needs_temperature' => 'boolean',
            'temperature_min' => ['nullable', Rule::requiredIf(fn () => $request->boolean('needs_temperature')), 'numeric', 'between:-30,30'],
            'temperature_max' => ['nullable', Rule::requiredIf(fn () => $request->boolean('needs_temperature')), 'numeric', 'between:-30,30', 'gte:temperature_min'],
            // ADR : le numero ONU identifie la matiere, la classe et le
            // groupe d'emballage decident du vehicule et du chauffeur.
            'un_number' => ['nullable', Rule::requiredIf(fn () => $request->boolean('is_hazardous')), 'digits:4'],
            'adr_class' => ['nullable', Rule::requiredIf(fn () => $request->boolean('is_hazardous')), 'in:'.implode(',', self::CLASSES_ADR)],
            'packing_group' => 'nullable|in:I,II,III',

            // Commercial
            'monthly_volume' => 'nullable|in:'.implode(',', self::CHOIX['volumes']),
            'budget' => 'nullable|numeric|min:0|max:10000000',
            'response_deadline' => 'nullable|date|after_or_equal:today',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx,xls,docx,doc,csv',
            'privacy' => 'accepted',

            'special_instructions' => 'nullable|string|max:2000',
        ], [
            'pickup_address.required' => Traductions::t('msg.devis_adresse_enlevement', 'Sélectionne l\'adresse d\'enlèvement dans les listes proposées.'),
            'pickup_lat.required' => Traductions::t('msg.devis_adresse_enlevement', 'Sélectionne l\'adresse d\'enlèvement dans les listes proposées.'),
            'delivery_address.required' => Traductions::t('msg.devis_adresse_livraison', 'Sélectionne l\'adresse de livraison dans les listes proposées.'),
            'delivery_lat.required' => Traductions::t('msg.devis_adresse_livraison', 'Sélectionne l\'adresse de livraison dans les listes proposées.'),
            'pickup_date.after_or_equal' => Traductions::t('msg.enlevement_passe', 'La date d\'enlèvement ne peut pas être dans le passé.'),
            'delivery_date.after_or_equal' => Traductions::t('msg.devis_livraison_avant', 'La livraison ne peut pas précéder l\'enlèvement.'),
            'weight.max' => Traductions::t('msg.poids_max_devis', 'Au-delà de 44 tonnes, contactez-nous par téléphone.'),
            'eori_number.required' => Traductions::t('msg.devis_eori_requis', 'Pour la Suisse, le Royaume-Uni et la Norvège, la douane exige votre numéro EORI.'),
            'eori_number.regex' => Traductions::t('msg.devis_eori_format', 'Le numéro EORI commence par le code du pays (ex. BE0123456749).'),
            'un_number.required' => Traductions::t('msg.devis_onu_requis', 'Pour une marchandise dangereuse, indiquez le numéro ONU (4 chiffres) et la classe ADR.'),
            'adr_class.required' => Traductions::t('msg.devis_onu_requis', 'Pour une marchandise dangereuse, indiquez le numéro ONU (4 chiffres) et la classe ADR.'),
            'temperature_min.required' => Traductions::t('msg.devis_temperature_requise', 'Indiquez la plage de température à respecter.'),
            'temperature_max.required' => Traductions::t('msg.devis_temperature_requise', 'Indiquez la plage de température à respecter.'),
            'temperature_max.gte' => Traductions::t('msg.devis_temperature_ordre', 'La température maximale ne peut pas être inférieure à la minimale.'),
            'attachments.*.max' => Traductions::t('msg.devis_piece_trop_lourde', 'Chaque pièce jointe fait 10 Mo au plus.'),
            'attachments.*.mimes' => Traductions::t('msg.devis_piece_format', 'Pièces jointes acceptées : PDF, images, tableurs et documents Word.'),
            'privacy.accepted' => Traductions::t('msg.devis_confidentialite', 'Acceptez la politique de confidentialité pour envoyer votre demande.'),
        ]);

        $data['goods_type'] = Traductions::vocabulaireEnFrancais('marchandise', trim($data['goods_type']));

        // Le type de trajet se deduit des deux pays : un Lille -> Bruxelles
        // s'enregistrait « National (Belgique) ».
        $data['pickup_country'] = strtoupper($data['pickup_country'] ?? 'BE');
        $data['trip_type'] = self::CHOIX['trajets'][match ((new Trajet($data['pickup_country'], $data['delivery_country']))->type()) {
            Trajet::NATIONAL => 0,
            Trajet::EXPORT => 1,
            Trajet::IMPORT => 2,
            default => 3,
        }];

        // Les colis pesent et mesurent : ils completent le poids et le
        // volume laisses vides.
        $colis = array_values($data['packages'] ?? []);
        $data['packages'] = $colis === [] ? null : $colis;
        $data['weight'] ??= ($poids = QuoteRequest::poidsDesColis($colis)) === null ? null : (int) round($poids);

        if (($data['volume'] ?? null) === null && ($m3 = QuoteRequest::volumeDesColis($colis)) !== null) {
            $data['volume'] = str_replace('.', ',', (string) $m3).' m³';
        }

        if (! ($data['needs_temperature'] ?? false)) {
            $data['temperature_min'] = $data['temperature_max'] = null;
        }

        if (! ($data['is_hazardous'] ?? false)) {
            $data['un_number'] = $data['adr_class'] = $data['packing_group'] = null;
        }

        $fichiers = $data['attachments'] ?? [];
        unset($data['attachments'], $data['privacy']);
        $data['privacy_accepted_at'] = now();
        $data['billing_country'] = isset($data['billing_country']) ? strtoupper($data['billing_country']) : null;

        $devis = QuoteRequest::create($data);

        $devis->update([
            'reference' => 'DEV-'.now()->year.'-'.str_pad($devis->id, 4, '0', STR_PAD_LEFT),
        ]);

        // Pieces jointes : disque prive, jamais servies sans controle.
        if ($fichiers !== []) {
            $devis->update(['attachments' => collect($fichiers)->map(fn (UploadedFile $f) => [
                'nom' => mb_substr($f->getClientOriginalName(), 0, 150),
                'chemin' => $f->store('devis/'.$devis->reference, 'local'),
                'taille' => $f->getSize(),
                'type' => $f->getMimeType(),
            ])->all()]);
        }

        Audience::noterEvenement($request, 'devis');

        return redirect()->route('devis.confirmation')->with('devis', $devis->reference);
    }

    public function confirmation(Request $request): Response|RedirectResponse
    {
        $reference = $request->session()->get('devis');
        $devis = $reference ? QuoteRequest::where('reference', $reference)->first() : null;

        if (! $devis) {
            return redirect()->route('devis.create');
        }

        $marchandise = array_filter([
            $devis->volume,
            $devis->weight ? number_format($devis->weight, 0, ',', ' ').' kg' : null,
            Traductions::vocabulaire('marchandise', $devis->goods_type),
        ]);

        return Inertia::render('Devis/Confirmation', [
            'devis' => [
                'reference' => $devis->reference,
                'trajet' => $devis->pickup_address.' → '.$devis->delivery_address,
                'enlevement' => $devis->pickup_date->format('d/m/Y'),
                'marchandise' => implode(' · ', $marchandise),
                'options' => array_map(
                    fn (string $option) => isset(self::OPTIONS[$option]) ? Traductions::t(self::OPTIONS[$option], $option) : $option,
                    $devis->options(),
                ),
            ],
        ]);
    }

    public function index(Request $request): Response
    {
        $statut = $request->query('statut', 'PENDING');
        $recherche = trim((string) $request->query('q', ''));

        $requete = QuoteRequest::with(['handler:id,first_name,last_name', 'commande:id,tracking_number'])
            ->latest('created_at');

        if (array_key_exists($statut, QuoteRequest::STATUTS)) {
            $requete->where('status', $statut);
        }

        if ($recherche !== '') {
            $requete->where(function ($q) use ($recherche) {
                foreach (['reference', 'company_name', 'contact_name', 'email', 'vat_number'] as $colonne) {
                    $q->orWhereContient($colonne, (string) $recherche);
                }
            });
        }

        return Inertia::render('Devis/Index', [
            'demandes' => $requete->paginate(10)->withQueryString(),
            'statut' => $statut,
            'recherche' => $recherche,
            'statuts' => collect(QuoteRequest::STATUTS)
                ->map(fn (string $libelle, string $cle) => Traductions::t(
                    'demandes.statut_'.strtolower($cle),
                    $libelle,
                ))
                ->all(),
            'compteurs' => QuoteRequest::selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'libelles' => self::libellesChoix(),
        ]);
    }

    /**
     * Les choix d'une demande sont ranges en francais. L'ecran de
     * traitement les montre dans la langue de l'utilisateur, avec les
     * memes traductions que le formulaire : valeur francaise => libelle.
     * Une valeur absente de la liste (ancienne saisie) reste telle quelle.
     *
     * @return array<string, string>
     */
    private static function libellesChoix(): array
    {
        $traduits = self::choixTraduits();
        $libelles = [];

        foreach (array_unique(self::CHAMPS_CHOIX) as $groupe) {
            $libelles += array_combine(self::CHOIX[$groupe], $traduits[$groupe]);
        }

        return $libelles;
    }

    public function updateStatus(Request $request, QuoteRequest $quoteRequest): RedirectResponse
    {
        $data = $request->validate([
            // « Transformee en commande » passe par commander(), jamais par ici.
            'status' => 'required|in:PENDING,PROCESSING,QUOTED,CLOSED',
            'internal_note' => 'nullable|string|max:2000',
        ]);

        if (! in_array($data['status'], QuoteRequest::TRANSITIONS[$quoteRequest->status] ?? [], true)) {
            return back()->with('error', Traductions::t('msg.devis_transition_impossible', 'Cette demande est déjà traitée : son statut ne peut plus revenir en arrière.'));
        }

        $quoteRequest->update([
            'status' => $data['status'],
            'internal_note' => $data['internal_note'] ?? $quoteRequest->internal_note,
            'handled_by' => Auth::id(),
            'handled_at' => now(),
        ]);

        ActivityLog::record(
            'quote.handled',
            'Demande '.$quoteRequest->reference.' : '.QuoteRequest::STATUTS[$data['status']],
            $quoteRequest,
            ['entreprise' => $quoteRequest->company_name, 'statut' => $data['status']],
        );

        return back()->with('success', Traductions::t('msg.devis_mis_a_jour', 'Demande :reference mise à jour.', ['reference' => $quoteRequest->reference]));
    }

    /** Une piece jointe, pour le personnel seulement. */
    public function piece(QuoteRequest $quoteRequest, int $rang): StreamedResponse
    {
        $piece = ($quoteRequest->attachments ?? [])[$rang] ?? null;

        abort_if($piece === null || ! Storage::disk('local')->exists($piece['chemin']), 404);

        return Storage::disk('local')->download($piece['chemin'], $piece['nom']);
    }

    /**
     * Le devis accepte devient une commande, preremplie et tarifee comme le
     * formulaire de commande le ferait, pour l'entreprise cliente qui a le
     * meme numero de TVA ou dont un compte a la meme adresse e-mail.
     */
    public function commander(QuoteRequest $quoteRequest): RedirectResponse
    {
        if ($quoteRequest->converted_order_id !== null
            || ! in_array('ORDERED', QuoteRequest::TRANSITIONS[$quoteRequest->status] ?? [], true)) {
            return back()->with('error', Traductions::t('msg.devis_deja_commande', 'Cette demande est déjà transformée ou close.'));
        }

        $tva = IdentifiantEntreprise::analyser((string) $quoteRequest->vat_number)['tva'];
        $client = ($tva !== null ? Client::where('vat_number', $tva)->first() : null)
            ?? User::where('email', $quoteRequest->email)->whereNotNull('client_id')->first()?->client;

        if ($client === null) {
            return back()->with('error', Traductions::t('msg.devis_sans_client', 'Aucune entreprise cliente n\'a ce numéro de TVA ni cette adresse e-mail : créez d\'abord son compte.'));
        }

        $trajet = new Trajet((string) ($quoteRequest->pickup_country ?: 'BE'), (string) $quoteRequest->delivery_country);

        if ($refus = $trajet->refus()) {
            return back()->with('error', $refus);
        }

        if (! ($quoteRequest->weight > 0)) {
            return back()->with('error', Traductions::t('msg.devis_sans_poids', 'Renseignez le poids avant de transformer la demande en commande.'));
        }

        $niveau = $quoteRequest->needs_express ? 'EXPRESS' : 'STANDARD';
        $grille = $trajet->grilles()->firstWhere('service_level', $niveau) ?? $trajet->grilles()->first();

        if ($grille === null) {
            return back()->with('error', Traductions::t('msg.devis_sans_grille', 'Aucune formule n\'est ouverte pour ce trajet.'));
        }

        $pays = $trajet->depart;
        $enlevement = Carbon::parse($quoteRequest->pickup_date->toDateString().' 08:00', Trajet::fuseau($pays))->setTimezone(config('app.timezone'));

        if ($pays !== 'BE') {
            $premier = FretRetour::premierEnlevement($pays, (float) $quoteRequest->pickup_lat, (float) $quoteRequest->pickup_lng, $quoteRequest->pickup_address);
            $enlevement = $enlevement->lt($premier) ? $premier : $enlevement;
        }

        $km = Tarificateur::distanceRoutiere((float) $quoteRequest->pickup_lat, (float) $quoteRequest->pickup_lng, (float) $quoteRequest->delivery_lat, (float) $quoteRequest->delivery_lng);
        $marchandise = in_array($quoteRequest->goods_type, TransportOrder::MARCHANDISES, true) ? $quoteRequest->goods_type : 'Autre';
        $volume = QuoteRequest::volumeDesColis($quoteRequest->packages);

        $demande = new TransportOrder([
            'client_id' => $client->id,
            'pickup_country' => $pays,
            'delivery_country' => $trajet->arrivee,
            'pickup_address' => $quoteRequest->pickup_address,
            'pickup_lat' => $quoteRequest->pickup_lat, 'pickup_lng' => $quoteRequest->pickup_lng,
            'delivery_lat' => $quoteRequest->delivery_lat, 'delivery_lng' => $quoteRequest->delivery_lng,
            'pickup_date' => $enlevement,
            'weight' => $quoteRequest->weight,
            'volume' => $volume,
            'is_hazardous' => (bool) $quoteRequest->is_hazardous,
            'needs_tail_lift' => (bool) $quoteRequest->needs_tail_lift,
            'distance_km' => (int) round($km),
        ]);

        $ordre = DB::transaction(function () use ($quoteRequest, $trajet, $demande, $km, $grille, $marchandise, $pays) {
            DB::statement('select pg_advisory_xact_lock(?)', [FretRetour::VERROU]);
            $offre = Tarificateur::offre($trajet, $demande, $km);

            $ordre = TransportOrder::deposer([
                ...$demande->only(['client_id', 'pickup_country', 'delivery_country', 'pickup_address', 'pickup_lat', 'pickup_lng',
                    'delivery_lat', 'delivery_lng', 'pickup_date', 'weight', 'volume', 'is_hazardous', 'needs_tail_lift', 'distance_km']),
                'delivery_address' => $quoteRequest->delivery_address,
                'goods_type' => $marchandise,
                'priority' => $quoteRequest->needs_express ? 'HIGH' : 'NORMAL',
                'requested_delivery_date' => $quoteRequest->delivery_date,
                'tariff_grid_id' => $grille->id,
                'special_instructions' => $this->consignes($quoteRequest),
                'shipper_name' => $quoteRequest->pickup_contact_name ?? ($pays !== 'BE' ? $quoteRequest->company_name : null),
                'shipper_phone' => $quoteRequest->pickup_contact_phone ?? ($pays !== 'BE' ? $quoteRequest->phone : null),
                'status' => 'PENDING',
                'created_date' => now(),
                'approche_km' => $pays === 'BE' ? null : (int) round($offre['porteuse'] !== null
                    ? FretRetour::approche($offre['porteuse'], (float) $quoteRequest->pickup_lat, (float) $quoteRequest->pickup_lng)
                    : Chronologie::kmDepuisDepot((float) $quoteRequest->pickup_lat, (float) $quoteRequest->pickup_lng)),
                'estimated_cost' => $offre['prix'][$grille->id],
                'pricing_basis' => $offre['base'],
                'backhaul_order_id' => $offre['porteuse']?->id,
                'tracking_code' => TransportOrder::prochainCode(),
            ]);

            $quoteRequest->update([
                'status' => 'ORDERED',
                'converted_order_id' => $ordre->id,
                'handled_by' => Auth::id(),
                'handled_at' => now(),
            ]);

            return $ordre;
        });

        ActivityLog::record(
            'quote.ordered',
            'Demande '.$quoteRequest->reference.' transformée en commande '.$ordre->tracking_number,
            $quoteRequest,
            ['entreprise' => $client->company_name, 'commande' => $ordre->tracking_number, 'prix' => (float) $ordre->estimated_cost],
        );

        return redirect()->route('transport-orders.show', $ordre)
            ->with('success', Traductions::t('msg.devis_commande_creee', 'Commande :numero créée à partir de la demande :reference.', [
                'numero' => $ordre->tracking_number,
                'reference' => $quoteRequest->reference,
            ]));
    }

    /** Ce que le chauffeur et le planificateur doivent savoir, en une note. */
    private function consignes(QuoteRequest $d): ?string
    {
        $lignes = [];

        foreach (['pickup' => 'Enlèvement', 'delivery' => 'Livraison'] as $lieu => $titre) {
            $details = array_filter([
                $d->{$lieu.'_contact_name'} ? 'contact '.$d->{$lieu.'_contact_name'}.($d->{$lieu.'_contact_phone'} ? ' ('.$d->{$lieu.'_contact_phone'}.')' : '') : null,
                $d->{$lieu.'_opening_hours'} ? 'ouvert '.$d->{$lieu.'_opening_hours'} : null,
                $d->{$lieu.'_time_slot'} ? 'créneau '.str_replace('_', '-', $d->{$lieu.'_time_slot'}) : null,
                $d->{$lieu.'_has_dock'} === false ? 'sans quai' : null,
                $d->{$lieu.'_appointment'} ? 'sur rendez-vous' : null,
                $d->{$lieu.'_access'} ? 'accès : '.implode(', ', array_map(fn ($a) => str_replace('_', ' ', $a), $d->{$lieu.'_access'})) : null,
                $d->{$lieu.'_access_notes'},
            ]);

            if ($details !== []) {
                $lignes[] = $titre.' : '.implode(' ; ', $details).'.';
            }
        }

        if ($d->needs_temperature) {
            $lignes[] = 'Température dirigée : de '.$d->temperature_min.' à '.$d->temperature_max.' °C.';
        }

        if ($d->is_hazardous && $d->un_number) {
            $lignes[] = 'ADR : ONU '.$d->un_number.', classe '.$d->adr_class.($d->packing_group ? ', groupe '.$d->packing_group : '').'.';
        }

        if ($d->end_client_name) {
            $lignes[] = 'Demande faite pour le compte de : '.$d->end_client_name.'.';
        }

        if ($d->special_instructions) {
            $lignes[] = $d->special_instructions;
        }

        $lignes[] = 'Demande de devis '.$d->reference.'.';

        return implode("\n", $lignes);
    }
}
