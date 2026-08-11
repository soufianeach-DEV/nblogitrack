<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Adresse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class TrackingController extends Controller
{
    public function show(Request $request): Response
    {
        return $request->user()
            ? $this->pourUtilisateur($request, $request->user())
            : $this->pourVisiteur($request);
    }

    /**
     * Page accessible sans compte : numero et code exiges, et on n'expose
     * que le strict necessaire au suivi.
     */
    private function pourVisiteur(Request $request): Response
    {
        $cherche = $request->filled('tracking_number') && $request->filled('code');

        $ordre = $cherche
            ? TransportOrder::with('client:id,company_name')
                ->where('tracking_number', $request->query('tracking_number'))
                ->where('tracking_code', strtoupper($request->query('code')))
                ->first([
                    'id', 'client_id', 'tracking_number', 'status',
                    'pickup_address', 'delivery_address', 'requested_delivery_date',
                ])
            : null;

        return Inertia::render('Tracking/Show', [
            'searched' => $cherche,
            'order' => $ordre,
        ]);
    }

    /**
     * Utilisateur identifie : le code n'a plus de sens, c'est le compte qui
     * fait foi. Un client ne retrouve que ses propres expeditions.
     */
    private function pourUtilisateur(Request $request, User $utilisateur): Response
    {
        $numero = trim((string) $request->query('tracking_number', ''));
        $ordre = null;

        if ($numero !== '') {
            $requete = TransportOrder::with([
                'client:id,company_name',
                'vehicle:registration,brand,model,vehicle_type,euro_standard,fuel_type,capacity_tonnes',
                'driver.user:id,first_name,last_name,phone',
                'tariffGrid:id,label,delivery_days',
            ])->where('tracking_number', $numero);

            if ($utilisateur->cannot('view-all-orders')) {
                $requete->where('client_id', $utilisateur->id);
            }

            $ordre = $requete->first();
        }

        return Inertia::render('Tracking/Show', [
            'searched' => $numero !== '',
            'order' => $ordre,
            'expeditions' => $this->expeditionsEnCours($utilisateur, $ordre),
            'chauffeur' => $ordre?->driver?->user ? [
                'nom' => $ordre->driver->user->first_name.' '.$ordre->driver->user->last_name,
                'telephone' => $ordre->driver->user->phone,
                'adr' => (bool) $ordre->driver->adr_certified,
                'permis' => $ordre->driver->license_type,
                // Le numero de permis est une donnee personnelle sans utilite
                // pour un client : le personnel seul y a acces.
                'numero_permis' => $utilisateur->can('view-all-orders')
                    ? $ordre->driver->license_number
                    : null,
                'trajets' => TransportOrder::where('driver_id', $ordre->driver_id)->count(),
            ] : null,
            'etapes' => $ordre ? $this->etapes($ordre) : null,
            'historique' => $ordre && $utilisateur->can('view-all-orders')
                ? $this->historique($ordre)
                : null,
        ]);
    }

    /**
     * L'itineraire routier d'une expedition.
     *
     * Charge a la demande, pour la seule expedition consultee : tracer les
     * cent cinq expeditions en cours ferait cent cinq appels au service
     * d'itineraire a chaque affichage de la page.
     */
    public function itineraire(Request $request, TransportOrder $transportOrder): JsonResponse
    {
        $this->autoriserSuivi($request, $transportOrder);

        return response()->json($this->trajet($transportOrder));
    }

    /**
     * Les peages traverses par cet itineraire.
     *
     * Adresse distincte du trace : la recherche des peages demande de vingt
     * a quarante secondes la premiere fois, le trace ne doit pas attendre.
     */
    public function peages(Request $request, TransportOrder $transportOrder): JsonResponse
    {
        $this->autoriserSuivi($request, $transportOrder);

        $trajet = $this->trajet($transportOrder);

        if ($trajet['direct']) {
            return response()->json([]);
        }

        return response()->json(Cache::remember(
            'peages:'.$this->cleTrajet($transportOrder),
            now()->addDays(30),
            fn () => $this->peagesDuTrace($trajet['geometrie']),
        ));
    }

    /**
     * Le cloisonnement ne peut pas reposer sur la seule page : ces adresses
     * sont appelables directement.
     */
    private function autoriserSuivi(Request $request, TransportOrder $ordre): void
    {
        abort_if(
            $request->user()->cannot('view-all-orders') && $ordre->client_id !== $request->user()->id,
            403,
        );

        abort_if($ordre->pickup_lat === null || $ordre->delivery_lat === null, 404);
    }

    /**
     * Deux expeditions qui relient les memes points partagent leur trace :
     * la cle porte sur les coordonnees, pas sur le numero de l'expedition.
     */
    private function cleTrajet(TransportOrder $ordre): string
    {
        return md5(implode(':', [
            $ordre->pickup_lat, $ordre->pickup_lng,
            $ordre->delivery_lat, $ordre->delivery_lng,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function trajet(TransportOrder $ordre): array
    {
        return Cache::remember(
            'itineraire:'.$this->cleTrajet($ordre),
            now()->addDays(30),
            fn () => $this->routeRoutiere(
                (float) $ordre->pickup_lat,
                (float) $ordre->pickup_lng,
                (float) $ordre->delivery_lat,
                (float) $ordre->delivery_lng,
            ),
        );
    }

    /**
     * Le trace routier complet rendu par OSRM. Si le service ne repond pas,
     * la liaison directe prend le relais : la carte reste utilisable, et
     * l'interface annonce que le trace n'est pas l'itineraire reel.
     *
     * @return array<string, mixed>
     */
    private function routeRoutiere(float $latDepart, float $lngDepart, float $latArrivee, float $lngArrivee): array
    {
        try {
            $reponse = Http::timeout(15)->get(
                "https://router.project-osrm.org/route/v1/driving/{$lngDepart},{$latDepart};{$lngArrivee},{$latArrivee}",
                ['overview' => 'full', 'geometries' => 'geojson'],
            );

            $route = $reponse->ok() ? $reponse->json('routes.0') : null;

            if (isset($route['geometry']['coordinates'])) {
                return [
                    // GeoJSON ordonne longitude puis latitude, Leaflet l'inverse.
                    'geometrie' => array_map(
                        fn (array $point) => [round($point[1], 5), round($point[0], 5)],
                        $route['geometry']['coordinates'],
                    ),
                    'distance_km' => (int) round($route['distance'] / 1000),
                    'duree_min' => (int) round($route['duration'] / 60),
                    'direct' => false,
                ];
            }
        } catch (\Throwable $e) {
            // Service indisponible : on bascule sur la liaison directe.
        }

        return [
            'geometrie' => [[$latDepart, $lngDepart], [$latArrivee, $lngArrivee]],
            'distance_km' => null,
            'duree_min' => null,
            'direct' => true,
        ];
    }

    /**
     * Les peages traverses, tires d'OpenStreetMap.
     *
     * On interroge le rectangle qui contient l'itineraire, puis on ne garde
     * que les postes proches du trace : demander a Overpass de suivre une
     * ligne de plusieurs milliers de points serait bien plus couteux que de
     * filtrer nous-memes.
     *
     * @param  array<int, array{0: float, 1: float}>  $geometrie
     * @return array<int, array<string, mixed>>
     */
    private function peagesDuTrace(array $geometrie): array
    {
        $latitudes = array_column($geometrie, 0);
        $longitudes = array_column($geometrie, 1);

        $requete = '[out:json][timeout:60];'
            .'node["barrier"~"^(toll_booth|toll_gantry)$"]('
            .(min($latitudes) - 0.05).','.(min($longitudes) - 0.05).','
            .(max($latitudes) + 0.05).','.(max($longitudes) + 0.05).');'
            .'out body 400;';

        $noeuds = $this->interrogerOverpass($requete);
        $peages = [];

        foreach ($noeuds as $noeud) {
            $rang = $this->rangSurLeTrace($geometrie, (float) $noeud['lat'], (float) $noeud['lon']);

            if ($rang === null) {
                continue;
            }

            $tags = $noeud['tags'] ?? [];

            $peages[] = [
                'rang' => $rang,
                'lat' => (float) $noeud['lat'],
                'lng' => (float) $noeud['lon'],
                'nom' => $tags['name'] ?? ($tags['operator'] ?? 'Péage'),
                'route' => $tags['highway:ref'] ?? ($tags['ref'] ?? null),
                'portique' => ($tags['barrier'] ?? '') === 'toll_gantry',
            ];
        }

        usort($peages, fn ($a, $b) => $a['rang'] <=> $b['rang']);

        // Une barriere compte un noeud par voie : cinq pour Roye, quatre pour
        // Senlis. On ne garde que le premier de chaque poste, reconnu a son
        // nom et a sa position au kilometre pres.
        $vus = [];
        $retenus = [];

        foreach ($peages as $peage) {
            $cle = mb_strtolower($peage['nom']).':'.round($peage['lat'], 2).':'.round($peage['lng'], 2);

            if (isset($vus[$cle])) {
                continue;
            }

            $vus[$cle] = true;
            unset($peage['rang']);
            $retenus[] = $peage;
        }

        return $retenus;
    }

    /**
     * La position du point le long du trace, ou null s'il en est trop loin.
     *
     * Le trace rendu par OSRM passe par les noeuds memes des voies
     * empruntees : une barriere de pleine voie tombe a moins d'un metre du
     * trace, alors qu'une barriere de sortie, posee sur une bretelle, s'en
     * ecarte de plus de cent cinquante metres. Le seuil de cent metres
     * separe les deux, et ne retient donc que les peages reellement
     * franchis.
     *
     * @param  array<int, array{0: float, 1: float}>  $geometrie
     */
    private function rangSurLeTrace(array $geometrie, float $lat, float $lng): ?int
    {
        $seuil = 0.1;
        $meilleur = null;
        $rang = null;
        // Un degre de longitude se resserre vers le nord : sans ce facteur,
        // l'ecart est surestime de moitie sous nos latitudes.
        $facteur = cos(deg2rad($lat));

        foreach ($geometrie as $index => [$pointLat, $pointLng]) {
            $dx = ($pointLng - $lng) * $facteur;
            $dy = $pointLat - $lat;
            $ecart = $dx * $dx + $dy * $dy;

            if ($meilleur === null || $ecart < $meilleur) {
                $meilleur = $ecart;
                $rang = $index;
            }
        }

        return $meilleur !== null && sqrt($meilleur) * 111.32 <= $seuil ? $rang : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function interrogerOverpass(string $requete): array
    {
        foreach (['https://overpass-api.de/api/interpreter', 'https://overpass.kumi.systems/api/interpreter'] as $hote) {
            try {
                // Overpass met couramment vingt a quarante secondes sur un
                // rectangle a l'echelle d'un pays : un plafond court ne
                // renverrait jamais que des listes vides.
                $reponse = Http::timeout(90)
                    ->withHeaders(['User-Agent' => 'NBLogiTrack/1.0 (epreuve integree)'])
                    ->asForm()
                    ->post($hote, ['data' => $requete]);

                if ($reponse->ok()) {
                    return $reponse->json('elements', []);
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }

    /**
     * Les expeditions encore en circulation, celles que la carte affiche.
     * Un client ne voit que les siennes, le personnel les voit toutes.
     *
     * L'expedition consultee s'ajoute a la liste si elle n'y figure pas
     * deja : on peut chercher un numero livre, la carte doit le montrer.
     *
     * @return array<int, array<string, mixed>>
     */
    private function expeditionsEnCours(User $utilisateur, ?TransportOrder $consultee): array
    {
        $requete = TransportOrder::with('client:id,company_name')
            ->whereIn('status', ['PENDING', 'IN_PROGRESS'])
            ->whereNotNull('pickup_lat')
            ->whereNotNull('delivery_lat');

        if ($utilisateur->cannot('view-all-orders')) {
            $requete->where('client_id', $utilisateur->id);
        }

        $expeditions = $requete
            ->orderByRaw("CASE status WHEN 'IN_PROGRESS' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE priority WHEN 'URGENT' THEN 0 WHEN 'HIGH' THEN 1 WHEN 'NORMAL' THEN 2 ELSE 3 END")
            ->orderBy('requested_delivery_date')
            ->get();

        if ($consultee?->pickup_lat && $consultee->delivery_lat
            && ! $expeditions->contains('id', $consultee->id)) {
            $expeditions->prepend($consultee);
        }

        return $expeditions->map(fn (TransportOrder $ordre) => [
            'id' => $ordre->id,
            'numero' => $ordre->tracking_number,
            'statut' => $ordre->status,
            'priorite' => $ordre->priority,
            'client' => $ordre->client?->company_name,
            'depart' => Adresse::localite($ordre->pickup_address),
            'arrivee' => Adresse::localite($ordre->delivery_address),
            'marchandise' => $ordre->goods_type,
            'adr' => (bool) $ordre->is_hazardous,
            'livraison' => $ordre->requested_delivery_date?->format('d/m/Y'),
            'coordonnees' => [
                [(float) $ordre->pickup_lat, (float) $ordre->pickup_lng],
                [(float) $ordre->delivery_lat, (float) $ordre->delivery_lng],
            ],
        ])->values()->all();
    }

    /**
     * Les trois jalons de la vie d'une expedition, horodates quand ils ont eu lieu.
     *
     * @return array<int, array<string, mixed>>
     */
    private function etapes(TransportOrder $ordre): array
    {
        return [
            [
                'libelle' => 'Commande enregistrée',
                'detail' => 'Ordre créé et confirmé.',
                'horodatage' => $ordre->created_date?->format('d/m/Y'),
                'fait' => true,
            ],
            [
                'libelle' => 'Prise en charge',
                'detail' => $ordre->vehicle
                    ? 'Véhicule '.$ordre->vehicle->registration.' affecté.'
                    : "En attente d'affectation d'un véhicule.",
                'horodatage' => $ordre->assigned_at?->format('d/m/Y à H\hi'),
                'fait' => $ordre->assigned_at !== null,
            ],
            [
                'libelle' => 'Livraison',
                'detail' => $ordre->actual_delivery_date
                    ? 'Marchandise livrée.'
                    : ($ordre->requested_delivery_date
                        ? 'Livraison souhaitée le '.$ordre->requested_delivery_date->format('d/m/Y').'.'
                        : 'Date à confirmer.'),
                'horodatage' => $ordre->actual_delivery_date?->format('d/m/Y'),
                'fait' => $ordre->actual_delivery_date !== null,
            ],
        ];
    }

    /**
     * Le detail horodate vient du journal d'activite, reserve au personnel.
     *
     * @return array<int, array<string, string>>
     */
    private function historique(TransportOrder $ordre): array
    {
        return ActivityLog::where('subject_type', 'TransportOrder')
            ->where('subject_id', (string) $ordre->id)
            ->orderBy('created_at')
            ->get(['description', 'created_at'])
            ->map(fn ($ligne) => [
                'description' => $ligne->description,
                'horodatage' => $ligne->created_at->format('d/m/Y à H\hi'),
            ])
            ->all();
    }
}
