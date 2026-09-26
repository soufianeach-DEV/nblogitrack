<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use App\Support\Pays;
use App\Support\Tarificateur;
use App\Support\Traductions;
use App\Support\Trajet;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransportOrder extends Model
{
    use DatesHeureDeBruxelles;
    use HasFactory;

    public const PRIORITES = ['URGENT', 'HIGH', 'NORMAL', 'LOW'];

    private const VERROU_NUMEROTATION = 4207301;

    public const MARCHANDISES = [
        'Boissons',
        'Colis express',
        'Machines',
        'Matériaux de construction',
        'Matériel électronique',
        'Mobilier',
        'Palettes',
        'Pièces automobiles',
        'Produits alimentaires',
        'Produits chimiques',
        'Produits pharmaceutiques',
        'Textile',
        'Autre',
    ];

    /** Marchandise du formulaire de devis => marchandise de la commande. */
    public const DEPUIS_DEVIS = [
        'Palettes' => 'Palettes',
        'Mobilier' => 'Mobilier',
        'Matériel' => 'Machines',
        'Alimentaire' => 'Produits alimentaires',
        'Frigorifique' => 'Produits alimentaires',
        'Textile' => 'Textile',
        'Électronique' => 'Matériel électronique',
        'Matériaux de construction' => 'Matériaux de construction',
        'Chimie' => 'Produits chimiques',
        'Automobile' => 'Pièces automobiles',
        'Colis' => 'Colis express',
    ];

    public static function marchandiseDepuisDevis(?string $devis): string
    {
        if (in_array($devis, self::MARCHANDISES, true)) {
            return $devis;
        }

        return self::DEPUIS_DEVIS[$devis] ?? 'Autre';
    }

    /**
     * Marchandises souvent soumises a l'ADR (produits chimiques, batteries
     * au lithium, airbags...) : le client doit dire explicitement si son
     * envoi l'est. Une case decochee par defaut faisait partir des
     * produits chimiques sans chauffeur ni vehicule ADR.
     */
    public const MARCHANDISES_ADR = [
        'Produits chimiques',
        'Produits pharmaceutiques',
        'Matériel électronique',
        'Pièces automobiles',
    ];

    /*
     * Un ordre attend (PENDING), puis le planificateur l'affecte a un
     * chauffeur et a un camion (ASSIGNED). Il n'est en cours
     * (IN_PROGRESS) qu'une fois la marchandise enlevee, sur confirmation
     * du chauffeur, puis livre (DELIVERED).
     */
    public const STATUTS = ['PENDING', 'ASSIGNED', 'IN_PROGRESS', 'DELIVERED', 'CANCELLED'];

    // Les transitions entre ces statuts sont definies par App\Enums\OrderStatus
    // et appliquees par App\Support\OrderWorkflow, seul a modifier le statut.

    /** Les ordres qui mobilisent encore un chauffeur ou un camion. */
    public const ACTIFS = ['PENDING', 'ASSIGNED', 'IN_PROGRESS'];

    /*
     * Article 8 bis des conditions generales : une expedition annulee par
     * le client alors qu'un camion lui etait reserve coute vingt-cinq pour
     * cent de son prix, cinquante euros au moins, jamais plus que le prix.
     */
    public const TAUX_ANNULATION = 25;

    public const MINIMUM_ANNULATION = 50;

    /**
     * Ce que coute l'annulation par le client, dans l'etat actuel de
     * l'ordre. Null si le client ne peut plus l'annuler lui-meme.
     */
    public function fraisAnnulation(): ?float
    {
        // Marchandise deja chargee (puis desaffectee apres une panne, par
        // exemple) : l'annulation en ligne n'est plus possible.
        if ($this->picked_up_at !== null) {
            return null;
        }

        return match ($this->status) {
            'PENDING' => 0.0,
            'ASSIGNED' => round(min(
                (float) $this->estimated_cost,
                max(self::MINIMUM_ANNULATION, (float) $this->estimated_cost * self::TAUX_ANNULATION / 100),
            ), 2),
            default => null,
        };
    }

    /** Le jour qui rattache l'ordre a une facture mensuelle. */
    public function dateFacturable(): CarbonInterface
    {
        return $this->status === 'CANCELLED'
            ? $this->cancelled_at
            : $this->actual_delivery_date;
    }

    /** Le montant qui part sur la facture : le transport, ou l'indemnite. */
    public function montantFacturable(): float
    {
        return $this->status === 'CANCELLED'
            ? (float) $this->cancellation_fee
            : (float) $this->estimated_cost;
    }

    protected $table = 'transport_orders';

    protected $fillable = [
        'client_id', 'created_date', 'pickup_date', 'pickup_address', 'delivery_address',
        'weight', 'distance_km', 'volume', 'goods_type', 'is_hazardous', 'needs_tail_lift', 'status', 'priority',
        'tracking_number', 'tracking_code', 'special_instructions', 'requested_delivery_date',
        'actual_delivery_date', 'delivered_at', 'received_by', 'delivery_reserves', 'estimated_cost', 'tariff_grid_id',
        'vehicle_registration', 'driver_id', 'assigned_at', 'picked_up_at', 'suivi_direct',
        'pickup_lat', 'pickup_lng', 'delivery_lat', 'delivery_lng',
        'cancelled_at', 'cancelled_by', 'cancellation_fee',
        'pickup_country', 'delivery_country', 'pricing_basis', 'backhaul_order_id', 'approche_km',
        'shipper_name', 'shipper_phone', 'loading_reference',
    ];

    protected function casts(): array
    {
        return [
            'is_hazardous' => 'boolean',
            'needs_tail_lift' => 'boolean',
            'suivi_direct' => 'boolean',
            'created_date' => 'date',
            'requested_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
            'pickup_date' => 'datetime',
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancellation_fee' => 'decimal:2',
            'distance_km' => 'integer',
            'approche_km' => 'integer',
        ];
    }

    public static function prochainNumero(): string
    {
        return 'TRK-'.now()->year.'-'.str_pad((string) (self::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    public static function deposer(array $attributs): self
    {
        return DB::transaction(function () use ($attributs) {
            DB::statement('select pg_advisory_xact_lock(?)', [self::VERROU_NUMEROTATION]);

            return self::create($attributs + ['tracking_number' => self::prochainNumero()]);
        });
    }

    public static function prochainCode(): string
    {
        return strtoupper(Str::random(12));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_registration', 'registration');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function tariffGrid(): BelongsTo
    {
        return $this->belongsTo(TariffGrid::class);
    }

    /** La mission dont le camion porte ce fret retour. */
    public function porteuse(): BelongsTo
    {
        return $this->belongsTo(self::class, 'backhaul_order_id');
    }

    /** Les frets retour vendus sur le retour de cette mission. */
    public function fretsRetour(): HasMany
    {
        return $this->hasMany(self::class, 'backhaul_order_id');
    }

    public function trajet(): Trajet
    {
        return new Trajet((string) ($this->pickup_country ?: 'BE'), (string) ($this->delivery_country ?: 'BE'));
    }

    /**
     * « Import France — Standard · Tarif fret retour », dans la langue du
     * lecteur.
     */
    public function formule(): string
    {
        $trajet = $this->trajet();
        $niveau = $this->tariffGrid?->service_level;
        $libelle = match ($trajet->type()) {
            Trajet::IMPORT => Traductions::t('grille.import', 'Import :pays', ['pays' => Pays::libelle($trajet->depart) ?? $trajet->depart]),
            Trajet::EXPORT => Traductions::t('grille.export', 'Export :pays', ['pays' => Pays::libelle($trajet->arrivee) ?? $trajet->arrivee]),
            default => Traductions::t('grille.national', 'National (BE)'),
        };

        if ($niveau !== null) {
            $libelle .= ' — '.Traductions::t('commande.offre_'.strtolower($niveau), ucfirst(strtolower($niveau)));
        }

        if ($this->pricing_basis === 'BACKHAUL') {
            $libelle .= ' · '.Traductions::t('commande.tarif_fret_retour', 'Tarif fret retour');
        }

        return $libelle;
    }

    /** Delai promis en jours : la formule, jamais moins que la route. */
    public function delaiPromis(): ?int
    {
        return $this->tariffGrid === null ? null : Tarificateur::delai($this->tariffGrid, $this->distance_km);
    }

    /** La ligne qui facture cette expedition, tant qu'aucun avoir ne l'a liberee. */
    public function invoiceLine(): HasOne
    {
        return $this->hasOne(InvoiceLine::class)
            ->where('active', true)
            ->whereIn('kind', [InvoiceLine::TRANSPORT, InvoiceLine::ANNULATION]);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(OrderCharge::class)->orderBy('id');
    }

    public function estEnAttenteDePaiement(): bool
    {
        return $this->status === 'DELIVERED'
            && $this->invoiceLine?->invoice?->status === 'SENT';
    }

    protected function enAttenteDePaiement(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estEnAttenteDePaiement());
    }
}
