<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use App\Support\Traductions;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use DatesHeureDeBruxelles;
    use HasFactory;

    protected $table = 'drivers';

    public $timestamps = false;

    public const STATUTS = [
        'OUVRIER' => 'Ouvrier (CP 140.03)',
        'INDEPENDANT' => 'Indépendant',
    ];

    public const MOTIFS_SORTIE = [
        'RETRAITE' => 'Retraite',
        'DEMISSION' => 'Démission',
        'LICENCIEMENT' => 'Licenciement',
        'INAPTITUDE' => 'Inaptitude médicale',
        'DECHEANCE' => 'Déchéance du permis',
    ];

    protected $fillable = [
        'user_id', 'employment_status', 'hired_on', 'birth_date', 'retirement_planned_on',
        'license_number', 'license_type', 'license_expiry', 'cpc_expiry', 'tacho_card_expiry',
        'is_available', 'adr_certified', 'adr_expiry', 'medical_exam_date', 'daily_driving_hours',
        'left_on', 'departure_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'adr_certified' => 'boolean',
            'adr_expiry' => 'date',
            'license_expiry' => 'date',
            'cpc_expiry' => 'date',
            'tacho_card_expiry' => 'date',
            'medical_exam_date' => 'date',
            'hired_on' => 'date',
            'birth_date' => 'date',
            'retirement_planned_on' => 'date',
            'left_on' => 'date',
        ];
    }

    /**
     * @return list<string>
     */
    public function empechements(?CarbonInterface $jusquau = null, ?Vehicle $vehicule = null): array
    {
        // Le code 95 et la carte tachygraphe ne concernent que les vehicules
        // de plus de 3,5 t (directive 2022/2561, reglement 561/2006) : pour
        // une camionnette de categorie B, ou un chauffeur qui n'a que le
        // permis B, ils ne bloquent pas.
        $professionnels = $vehicule !== null
            ? $vehicule->permisRequis() !== 'B'
            : $this->license_type !== 'B';

        return [
            ...$this->empechementsDeBase($jusquau),
            ...($professionnels ? $this->empechementsProfessionnels($jusquau) : []),
        ];
    }

    /**
     * Documents exiges pour tout vehicule : depart, permis, visite
     * medicale.
     *
     * @return list<string>
     */
    public function empechementsDeBase(?CarbonInterface $jusquau = null): array
    {
        // Les documents doivent etre valables jusqu'au dernier jour de la
        // mission, pas seulement aujourd'hui : une carte tachygraphe qui
        // expire avant un transport prevu dans trois semaines l'empeche.
        $motifs = [];
        $aujourdhui = ($jusquau ?? now())->copy()->startOfDay();

        if ($this->left_on !== null && $this->left_on->lte($aujourdhui)) {
            $motifs[] = Traductions::t('empechement.sortie', 'a quitté l\'entreprise le :date',
                ['date' => $this->left_on->format('d/m/Y')]);
        }

        if ($this->license_expiry !== null && $this->license_expiry->lt($aujourdhui)) {
            $motifs[] = Traductions::t('empechement.permis', 'permis expiré le :date',
                ['date' => $this->license_expiry->format('d/m/Y')]);
        }

        if ($this->medical_exam_date === null) {
            $motifs[] = Traductions::t('empechement.sans_visite', 'aucune visite médicale enregistrée');
        } elseif ($this->medical_exam_date->lt($aujourdhui->copy()->subYear())) {
            $motifs[] = Traductions::t('empechement.visite', 'visite médicale du :date à renouveler',
                ['date' => $this->medical_exam_date->format('d/m/Y')]);
        }

        return $motifs;
    }

    /**
     * Qualification code 95 et carte tachygraphe, exigees au-dela de 3,5 t.
     * Une date absente bloque, comme la visite medicale.
     *
     * @return list<string>
     */
    public function empechementsProfessionnels(?CarbonInterface $jusquau = null): array
    {
        $motifs = [];
        $aujourdhui = ($jusquau ?? now())->copy()->startOfDay();

        if ($this->cpc_expiry === null) {
            $motifs[] = Traductions::t('empechement.sans_code95', 'aucune qualification code 95 enregistrée');
        } elseif ($this->cpc_expiry->lt($aujourdhui)) {
            $motifs[] = Traductions::t('empechement.code95', 'qualification code 95 expirée le :date',
                ['date' => $this->cpc_expiry->format('d/m/Y')]);
        }

        if ($this->tacho_card_expiry === null) {
            $motifs[] = Traductions::t('empechement.sans_tachygraphe', 'aucune carte tachygraphe enregistrée');
        } elseif ($this->tacho_card_expiry->lt($aujourdhui)) {
            $motifs[] = Traductions::t('empechement.tachygraphe', 'carte tachygraphe expirée le :date',
                ['date' => $this->tacho_card_expiry->format('d/m/Y')]);
        }

        return $motifs;
    }

    /**
     * Ce que couvre chaque categorie (directive 2006/126) : le CE couvre
     * tout ; le C couvre le C1 mais pas les remorques ; le C1E couvre le
     * C1 avec remorque, pas le C.
     */
    public const COUVERTURE = [
        'B' => ['B'],
        'C1' => ['B', 'C1'],
        'C1E' => ['B', 'C1', 'C1E'],
        'C' => ['B', 'C1', 'C'],
        'CE' => ['B', 'C1', 'C1E', 'C', 'CE'],
    ];

    /**
     * La categorie de permis couvre-t-elle ce vehicule ? Le permis requis
     * vient de la fiche du vehicule (ou de son gabarit) : un tracteur de
     * 44 t exige le CE, qu'il tire un frigo, une benne ou une citerne.
     */
    public function motifPermis(Vehicle $vehicule): ?string
    {
        $permis = (string) $this->license_type;
        $requis = $vehicule->permisRequis();

        if (in_array($requis, self::COUVERTURE[$permis] ?? [], true)) {
            return null;
        }

        return Traductions::t('empechement.permis_requis', 'ce véhicule exige le permis :requis (permis :permis)', [
            'requis' => $requis,
            'permis' => $permis !== '' ? $permis : '—',
        ]);
    }

    /**
     * Une marchandise dangereuse exige un certificat ADR valable jusqu'au
     * dernier jour de la mission (il vaut cinq ans).
     */
    public function motifAdr(CarbonInterface $jusquau): ?string
    {
        if (! $this->adr_certified) {
            return Traductions::t('empechement.adr_absent', 'ce chauffeur n\'a pas la certification ADR');
        }

        if ($this->adr_expiry === null) {
            return Traductions::t('empechement.adr_sans_date', 'la fin de validité de son certificat ADR n\'est pas enregistrée');
        }

        if ($this->adr_expiry->lt($jusquau->copy()->startOfDay())) {
            return Traductions::t('empechement.adr_expire', 'son certificat ADR expire le :date', [
                'date' => $this->adr_expiry->format('d/m/Y'),
            ]);
        }

        return null;
    }

    /**
     * Chauffeurs qui ne peuvent pas prendre la route a cette date : memes
     * criteres que empechements(), pour les filtres en SQL.
     */
    public function scopeInapte(Builder $requete, ?CarbonInterface $date = null): void
    {
        $jour = ($date ?? now())->toDateString();
        $visite = ($date ?? now())->copy()->subYear()->toDateString();

        $requete->where(fn ($q) => $q
            ->where(fn ($p) => $p->whereNotNull('left_on')->where('left_on', '<=', $jour))
            ->orWhere('license_expiry', '<', $jour)
            ->orWhereNull('medical_exam_date')
            ->orWhere('medical_exam_date', '<', $visite)
            ->orWhere(fn ($pro) => $pro
                ->where(fn ($p) => $p->whereNull('license_type')->orWhere('license_type', '!=', 'B'))
                ->where(fn ($d) => $d
                    ->whereNull('cpc_expiry')
                    ->orWhere('cpc_expiry', '<', $jour)
                    ->orWhereNull('tacho_card_expiry')
                    ->orWhere('tacho_card_expiry', '<', $jour))));
    }

    public function indisponibilites(): HasMany
    {
        return $this->hasMany(Indisponibilite::class);
    }

    /**
     * La premiere indisponibilite (conge, maladie...) qui croise la periode.
     */
    public function indisponibleEntre(CarbonInterface $debut, CarbonInterface $fin): ?Indisponibilite
    {
        return $this->indisponibilites->first(fn (Indisponibilite $i) => $i->croise($debut, $fin));
    }

    public function estApte(): bool
    {
        return $this->empechements() === [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
