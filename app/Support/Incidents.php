<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\TransportOrder;
use Illuminate\Support\Collection;

/**
 * Les incidents que le chauffeur signale en mission : accident, panne ou
 * marchandise endommagee. Ils sont gardes au journal d'activite, rattaches
 * a l'ordre, avec le commentaire du chauffeur et sa position.
 */
class Incidents
{
    public const ACTION = 'order.incident';

    public const TYPES = ['ACCIDENT', 'PANNE', 'DOMMAGE'];

    // Un accident ou une panne immobilise le camion : la mission attend que
    // le chauffeur reprenne la route, ou qu'un autre camion lui soit
    // affecte. Une marchandise endommagee se livre, avec des reserves.
    public const BLOQUANTS = ['ACCIDENT', 'PANNE'];

    public const REPRISE = 'order.incident_resolved';

    public const VEHICULE_DEMANDE = 'order.vehicle_requested';

    public static function libelle(?string $type): string
    {
        return match ($type) {
            'ACCIDENT' => Traductions::t('incident.accident', 'Accident'),
            'PANNE' => Traductions::t('incident.panne', 'Panne'),
            'DOMMAGE' => Traductions::t('incident.dommage', 'Marchandise endommagée'),
            default => Traductions::t('incident.incident', 'Incident'),
        };
    }

    /**
     * Les incidents de plusieurs ordres, du plus ancien au plus recent,
     * ranges par identifiant d'ordre.
     *
     * @param  iterable<int, int|string>  $ordres
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public static function pour(iterable $ordres): Collection
    {
        $ids = collect($ordres)->map(fn ($id) => (string) $id)->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return ActivityLog::where('subject_type', 'TransportOrder')
            ->where('action', self::ACTION)
            ->whereIn('subject_id', $ids)
            ->orderBy('created_at')
            ->get(['subject_id', 'properties', 'created_at'])
            ->groupBy('subject_id')
            ->map(fn (Collection $lignes) => $lignes->map(fn (ActivityLog $ligne) => [
                'type' => $ligne->properties['type'] ?? null,
                'libelle' => self::libelle($ligne->properties['type'] ?? null),
                'commentaire' => $ligne->properties['commentaire'] ?? null,
                'marchandise_endommagee' => ($ligne->properties['type'] ?? null) === 'DOMMAGE'
                    || (bool) ($ligne->properties['marchandise_endommagee'] ?? false),
                'le' => $ligne->created_at->toIso8601String(),
                'horodatage' => $ligne->created_at->format(Traductions::t('msg.format_date_heure', 'd/m/Y à H\hi')),
            ])->values());
    }

    /**
     * Le camion est-il immobilise ? Le dernier accident ou la derniere
     * panne, sauf si le chauffeur a repris la route depuis ou si l'ordre a
     * change de camion. Null quand rien ne bloque la mission.
     *
     * @return array{decision_planificateur: bool, type: string, libelle: string, commentaire: ?string, le: string, vehicule_demande: bool}|null
     */
    public static function immobilisation(TransportOrder $ordre): ?array
    {
        return self::immobilisations(collect([$ordre]))->get((string) $ordre->id);
    }

    /**
     * @param  Collection<int, TransportOrder>  $ordres
     * @return Collection<string, array<string, mixed>>
     */
    public static function immobilisations(Collection $ordres): Collection
    {
        $enCours = $ordres->filter(fn (TransportOrder $o) => in_array($o->status, ['ASSIGNED', 'IN_PROGRESS'], true))->keyBy(fn ($o) => (string) $o->id);

        if ($enCours->isEmpty()) {
            return collect();
        }

        return ActivityLog::where('subject_type', 'TransportOrder')
            ->whereIn('action', [self::ACTION, self::REPRISE, self::VEHICULE_DEMANDE])
            ->whereIn('subject_id', $enCours->keys())
            ->orderBy('id')
            ->get(['id', 'subject_id', 'action', 'properties', 'created_at'])
            ->groupBy('subject_id')
            ->map(function (Collection $lignes, string $id) use ($enCours) {
                $incident = $lignes->last(fn (ActivityLog $l) => $l->action === self::ACTION
                    && in_array($l->properties['type'] ?? null, self::BLOQUANTS, true));

                if ($incident === null) {
                    return null;
                }

                $ensuite = $lignes->filter(fn (ActivityLog $l) => $l->id > $incident->id);

                // Un autre camion affecte depuis l'incident : la mission repart.
                $camion = $incident->properties['camion'] ?? null;
                if ($camion !== null && $camion !== $enCours[$id]->vehicle_registration) {
                    return null;
                }

                if ($ensuite->contains(fn (ActivityLog $l) => $l->action === self::REPRISE)) {
                    return null;
                }

                // Marchandise endommagee en plus du camion : ni reprise ni autre
                // vehicule a l'initiative du chauffeur, le planificateur
                // decide avec le client (annuler, envoyer un camion, reprendre).
                $decision = $lignes->contains(fn (ActivityLog $l) => $l->action === self::ACTION
                    && (($l->properties['type'] ?? null) === 'DOMMAGE' || ($l->properties['marchandise_endommagee'] ?? false)));

                return [
                    'decision_planificateur' => $decision,
                    'type' => $incident->properties['type'],
                    'libelle' => self::libelle($incident->properties['type']),
                    'commentaire' => $incident->properties['commentaire'] ?? null,
                    'le' => $incident->created_at->toIso8601String(),
                    'vehicule_demande' => $ensuite->contains(fn (ActivityLog $l) => $l->action === self::VEHICULE_DEMANDE),
                ];
            })
            ->filter();
    }
}
