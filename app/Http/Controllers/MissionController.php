<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\TransportOrder;
use App\Support\Adresse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MissionController extends Controller
{
    /**
     * Les seuls changements d'etat qu'un chauffeur peut provoquer, et depuis
     * quel etat. Annuler une expedition reste une decision commerciale : elle
     * n'apparait pas ici.
     */
    private const TRANSITIONS = [
        'IN_PROGRESS' => 'PENDING',
        'DELIVERED' => 'IN_PROGRESS',
    ];

    /**
     * Les missions du chauffeur connecte.
     */
    public function index(Request $request): Response
    {
        $chauffeur = $request->user();

        $missions = TransportOrder::with([
            'client:id,company_name',
            'vehicle:registration,brand,model,vehicle_type',
        ])
            ->where('driver_id', $chauffeur->id)
            ->orderByRaw("CASE status WHEN 'IN_PROGRESS' THEN 0 WHEN 'PENDING' THEN 1 ELSE 2 END")
            ->orderBy('pickup_date')
            ->get();

        $numero = trim((string) $request->query('mission', ''));
        $ouverte = $numero !== ''
            ? $missions->firstWhere('tracking_number', $numero)
            : null;

        return Inertia::render('Chauffeur/Missions', [
            'missions' => $missions->map(fn (TransportOrder $ordre) => $this->carte($ordre))->all(),
            'mission' => $ouverte ? $this->fiche($ouverte) : null,
            'introuvable' => $numero !== '' && $ouverte === null,
        ]);
    }

    /**
     * Le chauffeur fait avancer sa mission d'un cran.
     */
    public function updateStatus(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        // Une mission qui n'est pas la sienne n'existe pas pour lui.
        abort_if($transportOrder->driver_id !== $request->user()->id, 404);

        $donnees = $request->validate([
            'statut' => 'required|in:'.implode(',', array_keys(self::TRANSITIONS)),
        ]);

        $vise = $donnees['statut'];
        $attendu = self::TRANSITIONS[$vise];

        // Sans ce controle, deux appuis sur le meme bouton livreraient une
        // expedition jamais chargee, et un rappel de la page rejouerait
        // l'action.
        if ($transportOrder->status !== $attendu) {
            return back()->with('error', "Cette mission n'est plus dans l'état attendu, actualisez la page.");
        }

        $changements = ['status' => $vise];

        // La date d'enlevement est celle qui etait prevue : l'ecraser
        // ferait perdre le plan. L'heure reelle de prise en charge vit dans
        // le journal, qui est horodate.
        if ($vise === 'DELIVERED') {
            $changements['actual_delivery_date'] = now();
        }

        $transportOrder->update($changements);

        ActivityLog::record(
            $vise === 'DELIVERED' ? 'mission.delivered' : 'mission.started',
            $vise === 'DELIVERED'
                ? 'Livraison effectuée pour '.$transportOrder->tracking_number
                : 'Prise en charge de '.$transportOrder->tracking_number,
            $transportOrder,
            [
                'ancien_statut' => $attendu,
                'nouveau_statut' => $vise,
                'chauffeur' => Auth::id(),
            ],
        );

        return back()->with('success', $vise === 'DELIVERED'
            ? 'Livraison enregistrée.'
            : 'Mission prise en charge.');
    }

    /**
     * Ce que montre une carte de la liste.
     *
     * @return array<string, mixed>
     */
    private function carte(TransportOrder $ordre): array
    {
        return [
            'id' => $ordre->id,
            'numero' => $ordre->tracking_number,
            'statut' => $ordre->status,
            'priorite' => $ordre->priority,
            'enlevement' => Adresse::localite($ordre->pickup_address),
            'livraison' => Adresse::localite($ordre->delivery_address),
            'heure_enlevement' => $ordre->pickup_date?->format('H\hi'),
            'date_enlevement' => $ordre->pickup_date?->format('d/m'),
            'date_livraison' => $ordre->requested_delivery_date?->format('d/m'),
            'marchandise' => $ordre->goods_type,
            'adr' => (bool) $ordre->is_hazardous,
        ];
    }

    /**
     * Ce que montre la mission ouverte : tout ce dont le chauffeur a besoin
     * au volant, et rien du dossier commercial.
     *
     * @return array<string, mixed>
     */
    private function fiche(TransportOrder $ordre): array
    {
        return array_merge($this->carte($ordre), [
            'adresse_enlevement' => $ordre->pickup_address,
            'adresse_livraison' => $ordre->delivery_address,
            'enlevement_prevu' => $ordre->pickup_date?->format('d/m/Y à H\hi'),
            'livraison_prevue' => $ordre->requested_delivery_date?->format('d/m/Y'),
            'livree_le' => $ordre->actual_delivery_date?->format('d/m/Y'),
            'poids' => $ordre->weight,
            'volume' => $ordre->volume,
            'distance_km' => $ordre->distance_km,
            'consignes' => $ordre->special_instructions,
            'client' => $ordre->client?->company_name,
            'vehicule' => $ordre->vehicle ? [
                'immatriculation' => $ordre->vehicle->registration,
                'modele' => trim($ordre->vehicle->brand.' '.$ordre->vehicle->model),
                'type' => $ordre->vehicle->vehicle_type,
            ] : null,
            // Le bouton affiche depend de l'etat : un seul geste possible
            // a la fois, pas de liste d'actions a trier.
            'action' => match ($ordre->status) {
                'PENDING' => ['statut' => 'IN_PROGRESS', 'libelle' => 'Confirmer la prise en charge'],
                'IN_PROGRESS' => ['statut' => 'DELIVERED', 'libelle' => 'Confirmer la livraison'],
                default => null,
            },
        ]);
    }
}
