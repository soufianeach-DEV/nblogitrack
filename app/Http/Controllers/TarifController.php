<?php

namespace App\Http\Controllers;

use App\Models\TariffGrid;
use App\Support\Localite;
use App\Support\Pays;
use App\Support\Tarificateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TarifController extends Controller
{
    private const NIVEAUX = [
        'ECO' => 'Éco',
        'STANDARD' => 'Standard',
        'EXPRESS' => 'Express',
    ];

    public function index(): Response
    {
        return Inertia::render('Tarifs/Index', [
            'destinations' => $this->destinations(),
            'formules' => array_values(self::NIVEAUX),
        ]);
    }

    /**
     * La simulation, calculee au serveur.
     *
     * Le formulaire de commande envoie la grille complete au navigateur pour
     * afficher le detail au client identifie. Ici le visiteur est anonyme :
     * lui livrer la grille, ce serait publier la marge, le cout chauffeur et
     * la consommation a qui veut les lire. Seul le prix sort.
     */
    public function simuler(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'depart' => 'required|string|max:120',
            'destination' => 'required|string|max:120',
            'pays' => 'required|string|size:2|exists:tariff_grids,zone',
            // Quarante-quatre tonnes : la masse maximale autorisee d'un
            // ensemble articule sur les routes belges.
            'poids' => 'required|numeric|min:1|max:44000',
            'adr' => 'boolean',
        ], [
            'depart.required' => 'Indiquez la localité d\'enlèvement.',
            'destination.required' => 'Indiquez la localité de livraison.',
            'pays.exists' => 'Nous ne desservons pas encore ce pays.',
            'poids.required' => 'Indiquez le poids de la marchandise.',
            'poids.min' => 'Le poids doit être d\'au moins un kilogramme.',
            'poids.max' => 'Au-delà de 44 tonnes, la charge dépasse la masse maximale autorisée : demandez un devis.',
            'poids.numeric' => 'Le poids doit être un nombre.',
        ]);

        $depart = $this->localiser($donnees['depart'], 'BE');
        $arrivee = $this->localiser($donnees['destination'], $donnees['pays']);

        if ($depart === null || $arrivee === null) {
            return response()->json([
                'erreur' => $depart === null
                    ? 'Localité de départ introuvable en Belgique.'
                    : 'Localité de destination introuvable dans ce pays.',
            ], 422);
        }

        $km = Tarificateur::distanceRoutiere($depart->lat, $depart->lng, $arrivee->lat, $arrivee->lng);
        $adr = $request->boolean('adr');

        $formules = TariffGrid::where('is_active', true)
            ->where('zone', $donnees['pays'])
            ->orderBy('delivery_days')
            ->get()
            ->map(fn (TariffGrid $grille) => [
                'formule' => self::NIVEAUX[$grille->service_level] ?? $grille->service_level,
                'delai' => (int) $grille->delivery_days,
                'prix' => Tarificateur::cout(
                    $grille,
                    $km,
                    (float) $donnees['poids'],
                    $donnees['pays'],
                    $adr,
                ),
                'dedie' => $grille->service_level === 'EXPRESS',
            ])
            ->all();

        return response()->json([
            'depart' => $depart->ville,
            'arrivee' => $arrivee->ville,
            // Les localites sont la saisie du visiteur, renvoyee telle
            // quelle. Le pays vient d'un code : il se dit dans sa langue.
            'pays' => Pays::libelle($donnees['pays']) ?? $donnees['pays'],
            'distance' => (int) round($km),
            'poids' => (float) $donnees['poids'],
            'adr' => $adr,
            'formules' => $formules,
        ]);
    }

    /**
     * Les pays desservis, tires des grilles actives : une destination sans
     * grille n'est pas une destination.
     *
     * @return array<int, array<string, string>>
     */
    private function destinations(): array
    {
        $pays = TariffGrid::where('is_active', true)
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone')
            ->map(fn (string $code) => [
                'code' => $code,
                'nom' => Pays::libelle($code) ?? $code,
            ])
            ->all();

        // Le tri suit la langue lue, pas l'ordre des octets : « Zweden »
        // precede « Zwitserland », et les accents se rangent a leur place.
        $collateur = new \Collator(app()->getLocale());
        usort($pays, fn (array $a, array $b) => $collateur->compare($a['nom'], $b['nom']));

        return $pays;
    }

    /**
     * La localite dans la table des codes postaux, moyenne de ses coordonnees.
     * Six cent dix mille codes importes localement : aucun appel exterieur.
     */
    private function localiser(string $ville, string $pays): ?object
    {
        return DB::table('postal_codes')
            ->selectRaw('MIN(city) AS ville, AVG(lat) AS lat, AVG(lng) AS lng')
            ->where('country_code', $pays)
            ->where('city', 'ilike', Localite::locale($ville))
            ->groupBy('city')
            ->first();
    }
}
