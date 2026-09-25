<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransportOrder extends Model
{
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
        'actual_delivery_date', 'estimated_cost', 'tariff_grid_id',
        'vehicle_registration', 'driver_id', 'assigned_at', 'picked_up_at', 'suivi_direct',
        'pickup_lat', 'pickup_lng', 'delivery_lat', 'delivery_lng',
        'cancelled_at', 'cancelled_by', 'cancellation_fee',
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
            'cancelled_at' => 'datetime',
            'cancellation_fee' => 'decimal:2',
            'distance_km' => 'integer',
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

    public function invoiceLine(): HasOne
    {
        return $this->hasOne(InvoiceLine::class);
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
