<?php

namespace App\Models;

use App\Support\Traductions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Driver extends Model
{
    use HasFactory;

    protected $table = 'drivers';

    public $incrementing = false;

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
        'id', 'employment_status', 'hired_on', 'birth_date', 'retirement_planned_on',
        'license_number', 'license_type', 'license_expiry', 'cpc_expiry', 'tacho_card_expiry',
        'is_available', 'adr_certified', 'medical_exam_date', 'daily_driving_hours',
        'left_on', 'departure_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'adr_certified' => 'boolean',
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
    public function empechements(): array
    {
        $motifs = [];
        $aujourdhui = now()->startOfDay();

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

        if ($this->cpc_expiry !== null && $this->cpc_expiry->lt($aujourdhui)) {
            $motifs[] = Traductions::t('empechement.code95', 'qualification code 95 expirée le :date',
                ['date' => $this->cpc_expiry->format('d/m/Y')]);
        }

        if ($this->tacho_card_expiry !== null && $this->tacho_card_expiry->lt($aujourdhui)) {
            $motifs[] = Traductions::t('empechement.tachygraphe', 'carte tachygraphe expirée le :date',
                ['date' => $this->tacho_card_expiry->format('d/m/Y')]);
        }

        return $motifs;
    }

    public function estApte(): bool
    {
        return $this->empechements() === [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }
}
