<?php

namespace App\Http\Controllers;

use App\Models\TariffGrid;
use App\Support\GeocodageIndisponible;
use App\Support\Localite;
use App\Support\Pays;
use App\Support\Tarificateur;
use App\Support\Traductions;
use App\Support\Trajet;
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
            // Pays d'enlevement ouverts en ligne ; les autres passent par un devis.
            'departs' => $this->departs(),
            'remiseFretRetour' => (int) round(Tarificateur::remise() * 100),
            'formules' => array_values(self::NIVEAUX),
        ]);
    }

    public function simuler(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'depart' => 'required|string|max:120',
            'pays_depart' => 'nullable|string|size:2',
            'destination' => 'required|string|max:120',
            'pays' => 'required|string|size:2|exists:tariff_grids,zone',
            'poids' => 'required|numeric|min:1|max:44000',
            'adr' => 'boolean',
        ], [
            'depart.required' => Traductions::t('msg.localite_enlevement_requise', 'Indiquez la localité d\'enlèvement.'),
            'destination.required' => Traductions::t('msg.localite_livraison_requise', 'Indiquez la localité de livraison.'),
            'pays.exists' => Traductions::t('msg.pays_non_desservi', 'Nous ne desservons pas encore ce pays.'),
            'poids.required' => Traductions::t('msg.poids_requis', 'Indiquez le poids de la marchandise.'),
            'poids.min' => Traductions::t('msg.poids_min', 'Le poids doit être d\'au moins un kilogramme.'),
            'poids.max' => Traductions::t('msg.poids_max_tarif', 'Au-delà de 44 tonnes, la charge dépasse la masse maximale autorisée : demandez un devis.'),
            'poids.numeric' => Traductions::t('msg.poids_nombre', 'Le poids doit être un nombre.'),
        ]);

        $paysDepart = strtoupper($donnees['pays_depart'] ?? 'BE');
        $trajet = new Trajet($paysDepart, $donnees['pays']);

        // Aucune lecture de la flotte : le simulateur public ne dit jamais
        // ou se trouvent nos camions.
        if ($refus = $trajet->refus()) {
            return response()->json(['erreur' => $refus], 422);
        }

        try {
            $depart = $this->localiser($donnees['depart'], $paysDepart);
            $arrivee = $this->localiser($donnees['destination'], $donnees['pays']);
        } catch (GeocodageIndisponible) {
            return response()->json([
                'erreur' => Traductions::t('tarifs.service_indisponible', 'Le service est momentanément indisponible.'),
            ], 503);
        }

        if ($depart === null || $arrivee === null) {
            return response()->json([
                'erreur' => $depart === null
                    ? Traductions::t('msg.depart_introuvable', 'Localité de départ introuvable dans ce pays.')
                    : Traductions::t('msg.destination_introuvable', 'Localité de destination introuvable dans ce pays.'),
            ], 422);
        }

        $km = Tarificateur::distanceRoutiere($depart->lat, $depart->lng, $arrivee->lat, $arrivee->lng);
        $adr = $request->boolean('adr');

        $grilles = $trajet->grilles()->sortBy('delivery_days')->values();
        $prix = Tarificateur::parFormule($grilles, $km, (float) $donnees['poids'], (string) $trajet->zone(), $adr);

        $formules = $grilles
            ->map(fn (TariffGrid $grille) => [
                'formule' => self::NIVEAUX[$grille->service_level] ?? $grille->service_level,
                'delai' => Tarificateur::delai($grille, $km),
                'prix' => $prix[$grille->id],
                'dedie' => $grille->service_level === 'EXPRESS',
            ])
            ->all();

        return response()->json([
            'depart' => Traductions::vocabulaire('ville', $depart->ville),
            'arrivee' => Traductions::vocabulaire('ville', $arrivee->ville),
            'pays' => Pays::libelle($donnees['pays']) ?? $donnees['pays'],
            'pays_depart' => Pays::libelle($paysDepart) ?? $paysDepart,
            'trajet' => $trajet->fleche(),
            'fret_retour_possible' => $trajet->estImport() && Tarificateur::remise() > 0,
            'distance' => (int) round($km),
            'poids' => (float) $donnees['poids'],
            'adr' => $adr,
            'formules' => $formules,
        ]);
    }

    /**
     * en_ligne : pays sans codes postaux, dont les localites se proposent
     * par Photon (voir Geocodeur).
     *
     * @return array<int, array{code: string, nom: string, en_ligne: bool}>
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
                'en_ligne' => Localite::enLigne($code),
            ])
            ->all();

        $collateur = new \Collator(app()->getLocale());
        usort($pays, fn (array $a, array $b) => $collateur->compare($a['nom'], $b['nom']));

        return $pays;
    }

    /**
     * @return array<int, array{code: string, nom: string, en_ligne: bool}>
     */
    private function departs(): array
    {
        $pays = collect(config('fret.pays_enlevement'))
            ->map(fn (string $code) => [
                'code' => $code,
                'nom' => Pays::libelle($code) ?? $code,
                'en_ligne' => Localite::enLigne($code),
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
