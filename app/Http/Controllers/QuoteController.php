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
        // Service entre entreprises : pas de particulier.
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

    public function create(): Response
    {
        return Inertia::render('Devis/Create', ['choix' => self::CHOIX]);
    }

    public function store(Request $request): RedirectResponse
    {
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
            'pickup_address.required' => 'Sélectionne l\'adresse d\'enlèvement dans les listes proposées.',
            'pickup_lat.required' => 'Sélectionne l\'adresse d\'enlèvement dans les listes proposées.',
            'delivery_address.required' => 'Sélectionne l\'adresse de livraison dans les listes proposées.',
            'delivery_lat.required' => 'Sélectionne l\'adresse de livraison dans les listes proposées.',
            'pickup_date.after_or_equal' => 'La date d\'enlèvement ne peut pas être dans le passé.',
            'weight.max' => 'Au-delà de 44 tonnes, contactez-nous par téléphone.',
        ]);

        $devis = QuoteRequest::create($data);

        // La reference reprend le numero de la ligne, elle n'existe qu'apres l'insertion.
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
            $devis->goods_type,
        ]);

        return Inertia::render('Devis/Confirmation', [
            'devis' => [
                'reference' => $devis->reference,
                'trajet' => $devis->pickup_address.' → '.$devis->delivery_address,
                'enlevement' => $devis->pickup_date->format('d/m/Y'),
                'marchandise' => implode(' · ', $marchandise),
                'options' => $devis->options(),
            ],
        ]);
    }

    /**
     * Back-office : les demandes recues, filtrees par statut et par recherche.
     */
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
                    $q->orWhere($colonne, 'ilike', '%'.$recherche.'%');
                }
            });
        }

        return Inertia::render('Devis/Index', [
            'demandes' => $requete->paginate(10)->withQueryString(),
            'statut' => $statut,
            'recherche' => $recherche,
            // La constante porte le francais : elle est evaluee au
            // chargement de la classe, avant que la langue soit connue.
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

        return back()->with('success', 'Demande '.$quoteRequest->reference.' mise à jour.');
    }
}
