<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Adresse;
use App\Support\Traductions;
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
                'numero_permis' => $utilisateur->can('view-all-orders')
                    ? $ordre->driver->license_number
                    : null,
                'trajets' => TransportOrder::where('driver_id', $ordre->driver_id)->count(),
            ] : null,
            'etapes' => $ordre ? $this->etapes($ordre) : null,
            'jalons' => $ordre ? $this->jalons($ordre) : null,
            'position' => $ordre ? $this->positionActuelle($ordre) : null,
            'historique' => $ordre && $utilisateur->can('view-all-orders')
                ? $this->historique($ordre)
                : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jalons(TransportOrder $ordre): array
    {
        return ShipmentPosition::where('transport_order_id', $ordre->id)
            ->where('type', ShipmentPosition::JALON)
            ->orderBy('recorded_at')
            ->get()
            ->map(fn (ShipmentPosition $p) => [
                'evenement' => $p->evenement,
                'libelle' => $p->evenement === 'DELIVERED'
                    ? Traductions::t('suivi.jalon_livraison', 'Livraison')
                    : Traductions::t('suivi.jalon_enlevement', 'Prise en charge'),
                'localite' => Traductions::vocabulaire('ville', Adresse::localite(
                    $p->evenement === 'DELIVERED' ? $ordre->delivery_address : $ordre->pickup_address,
                )),
                'coordonnees' => [$p->lat, $p->lng],
                'horodatage' => $p->recorded_at->format('d/m/Y H:i'),
                'precision_m' => $p->precision_m,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function positionActuelle(TransportOrder $ordre): ?array
    {
        if (! $ordre->suivi_direct || $ordre->status !== 'IN_PROGRESS') {
            return null;
        }

        $point = ShipmentPosition::where('transport_order_id', $ordre->id)
            ->where('type', ShipmentPosition::ROUTE)
            ->latest('recorded_at')
            ->first();

        if ($point === null) {
            return null;
        }

        return [
            'coordonnees' => [$point->lat, $point->lng],
            'horodatage' => $point->recorded_at->format('H:i'),
            'minutes' => (int) $point->recorded_at->diffInMinutes(now()),
            'precision_m' => $point->precision_m,
        ];
    }

    public function itineraire(Request $request, TransportOrder $transportOrder): JsonResponse
    {
        $this->autoriserSuivi($request, $transportOrder);

        return response()->json($this->trajet($transportOrder));
    }

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

    private function autoriserSuivi(Request $request, TransportOrder $ordre): void
    {
        abort_if(
            $request->user()->cannot('view-all-orders') && $ordre->client_id !== $request->user()->id,
            404,
        );

        abort_if($ordre->pickup_lat === null || $ordre->delivery_lat === null, 404);
    }

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
        }

        return [
            'geometrie' => [[$latDepart, $lngDepart], [$latArrivee, $lngArrivee]],
            'distance_km' => null,
            'duree_min' => null,
            'direct' => true,
        ];
    }

    /**
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
     * @param  array<int, array{0: float, 1: float}>  $geometrie
     */
    private function rangSurLeTrace(array $geometrie, float $lat, float $lng): ?int
    {
        $seuil = 0.1;
        $meilleur = null;
        $rang = null;
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
            'depart' => Traductions::vocabulaire('ville', Adresse::localite($ordre->pickup_address)),
            'arrivee' => Traductions::vocabulaire('ville', Adresse::localite($ordre->delivery_address)),
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
