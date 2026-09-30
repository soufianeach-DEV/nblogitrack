<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use App\Support\Traductions;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Periode ou un chauffeur (conge, maladie, formation) ou un vehicule
 * (entretien, reparation) ne peut pas etre affecte.
 */
class Indisponibilite extends Model
{
    use DatesHeureDeBruxelles;

    protected $table = 'indisponibilites';

    public const MOTIFS_CHAUFFEUR = [
        'CONGE' => 'Congé',
        'MALADIE' => 'Maladie',
        'FORMATION' => 'Formation',
        'AUTRE' => 'Autre',
    ];

    public const MOTIFS_VEHICULE = [
        'ENTRETIEN' => 'Entretien',
        'REPARATION' => 'Réparation',
        'CONTROLE' => 'Contrôle technique',
        'AUTRE' => 'Autre',
    ];

    protected $fillable = ['driver_id', 'vehicle_registration', 'du', 'au', 'motif', 'commentaire', 'created_by'];

    protected function casts(): array
    {
        return [
            'du' => 'date',
            'au' => 'date',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_registration', 'registration');
    }

    public function croise(CarbonInterface $debut, CarbonInterface $fin): bool
    {
        return $this->du->lte($fin->copy()->startOfDay()) && $this->au->gte($debut->copy()->startOfDay());
    }

    public function libelle(): string
    {
        $cle = strtolower($this->motif);
        $defaut = self::MOTIFS_CHAUFFEUR[$this->motif] ?? self::MOTIFS_VEHICULE[$this->motif] ?? $this->motif;

        return Traductions::t('indispo.'.$cle, $defaut);
    }

    /**
     * « conge du 10/10/2026 au 20/10/2026 »
     */
    public function resume(): string
    {
        return Traductions::t('indispo.resume', ':motif du :du au :au', [
            'motif' => mb_strtolower($this->libelle()),
            'du' => $this->du->format('d/m/Y'),
            'au' => $this->au->format('d/m/Y'),
        ]);
    }
}
