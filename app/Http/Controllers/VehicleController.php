<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Indisponibilite;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\ControleAffectation;
use App\Support\Suggestions;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        $filtres = $request->validate([
            'q' => 'nullable|string|max:60',
            'type' => 'nullable|string|max:40',
            'charge' => 'nullable|in:1,3,6,12,24',
            'norme' => 'nullable|string|max:10',
            'hayon' => 'nullable|in:1',
            'etat' => 'nullable|in:disponibles,indisponibles,controle,controle_roulant',
        ]);

        $requete = Vehicle::query();

        if (! empty($filtres['q'])) {
            $terme = (string) $filtres['q'];
            $requete->where(fn ($q) => $q
                ->whereContient('registration', $terme)
                ->orWhereContient('brand', $terme)
                ->orWhereContient('model', $terme)
                ->orWhereContient('vin', $terme));
        }

        if (! empty($filtres['type'])) {
            $requete->where('vehicle_type', $filtres['type']);
        }

        if (! empty($filtres['charge'])) {
            $requete->where('capacity_tonnes', '>=', (float) $filtres['charge']);
        }

        if (! empty($filtres['norme'])) {
            $requete->where('euro_standard', $filtres['norme']);
        }

        if (! empty($filtres['hayon'])) {
            $requete->where('has_tail_lift', true);
        }

        $aujourdhui = now()->toDateString();

        match ($filtres['etat'] ?? null) {
            'disponibles' => $requete->where('is_available', true),
            'indisponibles' => $requete->where('is_available', false),
            'controle' => $requete->where('inspection_valid_until', '<', $aujourdhui),
            // Le lien « Voir les N » du tableau de bord arrive ici : les
            // vehicules hors service a l'arret n'y sont pas comptes.
            'controle_roulant' => $requete->where(DashboardController::vehiculesControleRoulant()),
            default => null,
        };

        $engages = TransportOrder::whereIn('status', TransportOrder::ACTIFS)
            ->whereNotNull('vehicle_registration')
            ->pluck('vehicle_registration')
            ->unique()
            ->flip();

        return Inertia::render('Parc/Vehicules', [
            'suggestions' => Suggestions::depuis(fn () => Vehicle::query(), ['registration', 'brand', 'model'], $filtres['q'] ?? null),
            'vehicules' => $requete->with(['indisponibilites' => fn ($q) => $q->where('au', '>=', today()->toDateString())->orderBy('du')])
                ->orderBy('registration')->get()->map(fn (Vehicle $v) => [
                    'immatriculation' => $v->registration,
                    'marque' => trim($v->brand.' '.$v->model),
                    'type' => $v->vehicle_type,
                    'permis_requis' => $v->permisRequis(),
                    'permis_fiche' => $v->permis_requis,
                    'permis_gabarit' => Vehicle::permisDeduit((string) $v->vehicle_type, (float) $v->capacity_tonnes),
                    'adr_equipe' => (bool) $v->adr_equipe,
                    'indisponibilites' => $v->indisponibilites->map(fn (Indisponibilite $i) => [
                        'id' => $i->id,
                        'resume' => $i->resume(),
                        'commentaire' => $i->commentaire,
                    ])->all(),
                    'norme' => $v->euro_standard,
                    'carburant' => $v->fuel_type,
                    'capacite' => (float) $v->capacity_tonnes,
                    'volume' => (float) $v->capacity_volume,
                    'hayon' => (bool) $v->has_tail_lift,
                    'kilometrage' => (int) $v->mileage,
                    'controle' => $v->inspection_date?->format('Y-m-d'),
                    'controle_affiche' => $v->inspection_date?->format('d/m/Y'),
                    'controle_valide' => $v->inspection_valid_until?->format('Y-m-d'),
                    'controle_valide_affiche' => $v->inspection_valid_until?->format('d/m/Y'),
                    'controle_depasse' => $v->inspection_valid_until !== null
                        && $v->inspection_valid_until->lt(now()->startOfDay()),
                    'disponible' => (bool) $v->is_available,
                    'engage' => $engages->has($v->registration),
                    'vin' => $v->vin,
                ])->all(),
            'types' => Vehicle::distinct()->orderBy('vehicle_type')->pluck('vehicle_type'),
            'normes' => Vehicle::whereNotNull('euro_standard')->distinct()->orderBy('euro_standard')->pluck('euro_standard'),
            'compteurs' => [
                'total' => Vehicle::count(),
                'disponibles' => Vehicle::where('is_available', true)->count(),
                'controle' => Vehicle::where('inspection_valid_until', '<', $aujourdhui)->count(),
                'controle_roulant' => Vehicle::where(DashboardController::vehiculesControleRoulant())->count(),
            ],
            'filtres' => $filtres,
            'peutModifier' => $request->user()->can('manage-fleet'),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $donnees = $request->validate([
            'is_available' => 'required|boolean',
            'inspection_date' => 'nullable|date|before_or_equal:today',
            'inspection_valid_until' => 'nullable|date|after_or_equal:inspection_date',
            'mileage' => 'nullable|numeric|min:0|max:9999999',
            'permis_requis' => 'sometimes|nullable|in:'.implode(',', Vehicle::PERMIS),
            'adr_equipe' => 'sometimes|boolean',
        ], [
            'inspection_date.before_or_equal' => Traductions::t('msg.controle_futur', 'Un contrôle technique ne peut pas être daté dans le futur.'),
            'inspection_valid_until.after_or_equal' => Traductions::t('msg.validite_avant_controle', 'La validité ne peut pas précéder le passage au contrôle.'),
        ]);

        // Seul un vrai retrait du service se refuse : enregistrer la fiche
        // d'un camion deja hors service ne doit pas etre bloque.
        if ($donnees['is_available'] === false && $vehicle->is_available) {
            $engage = TransportOrder::whereIn('status', TransportOrder::ACTIFS)
                ->where('vehicle_registration', $vehicle->registration)
                ->exists();

            if ($engage) {
                return back()->withErrors([
                    'is_available' => Traductions::t('msg.vehicule_engage_service', 'Ce véhicule porte une expédition en cours : réaffectez-la depuis l\'écran Planification avant de le retirer du service.'),
                ]);
            }
        }

        // Seul un vrai retrait de l'equipement ADR se refuse : la fiche
        // envoie toujours la case, meme non modifiee.
        if (($donnees['adr_equipe'] ?? null) === false && $vehicle->adr_equipe) {
            $dangereux = TransportOrder::whereIn('status', TransportOrder::ACTIFS)
                ->where('vehicle_registration', $vehicle->registration)
                ->where('is_hazardous', true)
                ->exists();

            if ($dangereux) {
                return back()->withErrors([
                    'adr_equipe' => Traductions::t('msg.vehicule_engage_adr', 'Ce véhicule transporte une matière dangereuse : son équipement ADR ne peut pas être retiré maintenant.'),
                ]);
            }
        }

        // Le permis exige peut etre releve (grue, remorque), jamais abaisse
        // sous celui du gabarit : une semi-remorque reglee sur C partirait
        // avec un permis C.
        if (! empty($donnees['permis_requis'])) {
            $minimum = Vehicle::permisDeduit((string) $vehicle->vehicle_type, (float) $vehicle->capacity_tonnes);

            if (! in_array($minimum, Driver::COUVERTURE[$donnees['permis_requis']] ?? [], true)) {
                return back()->withErrors([
                    'permis_requis' => Traductions::t('msg.permis_sous_gabarit', 'Ce véhicule exige au moins le permis :minimum : le permis :choisi ne le couvre pas.', [
                        'minimum' => $minimum,
                        'choisi' => $donnees['permis_requis'],
                    ]),
                ]);
            }
        }

        // Le formulaire montre le releve au kilometre pres : renvoyer la
        // valeur affichee (458 099 pour 458 099,64) ne fait pas reculer le
        // compteur.
        if ($donnees['mileage'] !== null && (float) $donnees['mileage'] < floor((float) $vehicle->mileage)) {
            return back()->withErrors([
                // Arrondi, le message annoncait 175 662 la ou le controle et
                // le champ retiennent 175 661 : on tronque comme eux.
                'mileage' => Traductions::t('msg.kilometrage_inferieur', 'Le kilométrage ne peut pas descendre sous le relevé actuel (:km km).', [
                    'km' => number_format(floor((float) $vehicle->mileage), 0, ',', ' '),
                ]),
            ]);
        }

        // Le releve renvoye tel qu'affiche ne remplace pas la valeur exacte ;
        // un champ vide garde le releve actuel (il faisait une erreur 500).
        if ($donnees['mileage'] === null || (float) $donnees['mileage'] === floor((float) $vehicle->mileage)) {
            unset($donnees['mileage']);
        }

        $vehicle->update($donnees);

        ActivityLog::record(
            'vehicle.updated',
            'Véhicule '.$vehicle->registration.' mis à jour',
            $vehicle,
            $donnees,
        );

        $reponse = back()->with('success', Traductions::t('msg.vehicule_mis_a_jour', 'Véhicule mis à jour.'));
        $aReaffecter = ControleAffectation::missionsNonConformes(TransportOrder::where('vehicle_registration', $vehicle->registration));

        return $aReaffecter === [] ? $reponse : $reponse->with('error', Traductions::t('msg.missions_a_reaffecter', 'Attention : ces missions ne sont plus conformes et doivent être réaffectées : :missions.', [
            'missions' => implode(', ', $aReaffecter),
        ]));
    }
}
