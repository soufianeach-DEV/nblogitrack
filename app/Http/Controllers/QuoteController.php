<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\QuoteRequest;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    private const CHOIX = [
        'clients' => [
            'Client régulier / entreprise',
            'Nouvelle entreprise',
            'Intermédiaire / commissionnaire',
        ],
        'trajets' => [
            'National (Belgique)',
            'International (Union européenne)',
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
        'marchandises' => [
            'Palettes', 'Mobilier', 'Matériel', 'Alimentaire', 'Frigorifique',
            'Textile', 'Électronique', 'Matériaux de construction', 'Chimie',
            'Automobile', 'Vrac', 'Colis',
        ],
    ];

    private const CHAMPS_CHOIX = [
        'customer_type' => 'clients',
        'trip_type' => 'trajets',
        'frequency' => 'frequences',
        'date_flexibility' => 'flexibilites',
        'vehicle_type' => 'vehicules',
        'insurance_value' => 'assurances',
    ];

    private const OPTIONS = [
        'Hayon élévateur' => 'devis.hayon',
        'Marchandise dangereuse (ADR)' => 'devis.adr',
        'Livraison express' => 'devis.express',
        'Preuve de livraison (e-CMR)' => 'devis.ecmr',
    ];

    public function create(): Response
    {
        return Inertia::render('Devis/Create', ['choix' => self::choixTraduits()]);
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

        $data = $request->validate([
            'company_name' => 'required|string|max:150',
            'contact_name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:20',
            'vat_number' => 'nullable|string|max:30',
            'customer_type' => 'required|in:'.implode(',', self::CHOIX['clients']),

            'pickup_address' => 'required|string|max:255',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'delivery_address' => 'required|string|max:255',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'delivery_country' => 'required|string|size:2',

            'pickup_date' => 'required|date|after_or_equal:today',
            'trip_type' => 'required|in:'.implode(',', self::CHOIX['trajets']),
            'frequency' => 'required|in:'.implode(',', self::CHOIX['frequences']),
            'date_flexibility' => 'required|in:'.implode(',', self::CHOIX['flexibilites']),

            'goods_type' => 'required|string|max:100',
            'weight' => 'nullable|integer|min:0|max:44000',
            'volume' => 'nullable|string|max:60',
            'vehicle_type' => 'required|in:'.implode(',', self::CHOIX['vehicules']),
            'insurance_value' => 'required|in:'.implode(',', self::CHOIX['assurances']),

            'needs_tail_lift' => 'boolean',
            'is_hazardous' => 'boolean',
            'needs_express' => 'boolean',
            'needs_ecmr' => 'boolean',

            'special_instructions' => 'nullable|string|max:2000',
        ], [
            'pickup_address.required' => Traductions::t('msg.devis_adresse_enlevement', 'Sélectionne l\'adresse d\'enlèvement dans les listes proposées.'),
            'pickup_lat.required' => Traductions::t('msg.devis_adresse_enlevement', 'Sélectionne l\'adresse d\'enlèvement dans les listes proposées.'),
            'delivery_address.required' => Traductions::t('msg.devis_adresse_livraison', 'Sélectionne l\'adresse de livraison dans les listes proposées.'),
            'delivery_lat.required' => Traductions::t('msg.devis_adresse_livraison', 'Sélectionne l\'adresse de livraison dans les listes proposées.'),
            'pickup_date.after_or_equal' => Traductions::t('msg.enlevement_passe', 'La date d\'enlèvement ne peut pas être dans le passé.'),
            'weight.max' => Traductions::t('msg.poids_max_devis', 'Au-delà de 44 tonnes, contactez-nous par téléphone.'),
        ]);

        $data['goods_type'] = Traductions::vocabulaireEnFrancais('marchandise', trim($data['goods_type']));

        $devis = QuoteRequest::create($data);

        $devis->update([
            'reference' => 'DEV-'.now()->year.'-'.str_pad($devis->id, 4, '0', STR_PAD_LEFT),
        ]);

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

        $requete = QuoteRequest::with('handler:id,first_name,last_name')
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
        ]);
    }

    public function updateStatus(Request $request, QuoteRequest $quoteRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(QuoteRequest::STATUTS)),
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
}
