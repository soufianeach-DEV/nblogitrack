<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Http\Request;
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
                'vehicle:registration,brand,model',
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
            'chauffeur' => $ordre?->driver?->user ? [
                'nom' => $ordre->driver->user->first_name.' '.$ordre->driver->user->last_name,
                'telephone' => $ordre->driver->user->phone,
                'adr' => (bool) $ordre->driver->adr_certified,
            ] : null,
            'etapes' => $ordre ? $this->etapes($ordre) : null,
            'historique' => $ordre && $utilisateur->can('view-all-orders')
                ? $this->historique($ordre)
                : null,
        ]);
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
