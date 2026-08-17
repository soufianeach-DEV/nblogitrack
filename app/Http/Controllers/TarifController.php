<?php

namespace App\Http\Controllers;

use App\Models\TariffGrid;
use App\Support\Localite;
use App\Support\Pays;
use App\Support\Tarificateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function simuler(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'depart' => 'required|string|max:120',
            'destination' => 'required|string|max:120',
            'pays' => 'required|string|size:2|exists:tariff_grids,zone',
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
            'pays' => Pays::libelle($donnees['pays']) ?? $donnees['pays'],
            'distance' => (int) round($km),
            'poids' => (float) $donnees['poids'],
            'adr' => $adr,
            'formules' => $formules,
        ]);
    }

    /**
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

        $collateur = new \Collator(app()->getLocale());
        usort($pays, fn (array $a, array $b) => $collateur->compare($a['nom'], $b['nom']));

        return $pays;
    }

    private function localiser(string $ville, string $pays): ?object
    {
        return Localite::coordonnees($ville, $pays);
    }
}
