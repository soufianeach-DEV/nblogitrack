<?php

namespace App\Support;

use App\Models\ActivityLog;
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
                'le' => $ligne->created_at->toIso8601String(),
                'horodatage' => $ligne->created_at->format(Traductions::t('msg.format_date_heure', 'd/m/Y à H\hi')),
            ])->values());
    }
}
