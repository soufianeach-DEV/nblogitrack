<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransportOrder extends Model
{
    use HasFactory;

    /** De la plus pressante a la moins pressante. */
    public const PRIORITES = ['URGENT', 'HIGH', 'NORMAL', 'LOW'];

    /**
     * La cle du verrou consultatif qui serialise l'attribution des numeros
     * de suivi. Un entier arbitraire, mais fixe : deux processus ne se
     * croisent que s'ils demandent la meme cle.
     */
    private const VERROU_NUMEROTATION = 4207301;

    /**
     * Le vocabulaire des marchandises, commun au formulaire de commande et au
     * jeu de donnees : un client doit pouvoir commander ce que l'historique montre.
     */
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

    /**
     * Seules ces marchandises peuvent relever de l'ADR : produits chimiques,
     * certains medicaments, et les batteries au lithium que contiennent
     * l'electronique et les pieces automobiles (classe 9).
     */
    public const MARCHANDISES_ADR = [
        'Produits chimiques',
        'Produits pharmaceutiques',
        'Matériel électronique',
        'Pièces automobiles',
    ];

    protected $table = 'transport_orders';

    protected $fillable = [
        'client_id', 'created_date', 'pickup_date', 'pickup_address', 'delivery_address',
        'weight', 'distance_km', 'volume', 'goods_type', 'is_hazardous', 'needs_tail_lift', 'status', 'priority',
        'tracking_number', 'tracking_code', 'special_instructions', 'requested_delivery_date',
        'actual_delivery_date', 'estimated_cost', 'tariff_grid_id',
        'vehicle_registration', 'driver_id', 'assigned_at', 'suivi_direct',
        'pickup_lat', 'pickup_lng', 'delivery_lat', 'delivery_lng',
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
            'distance_km' => 'integer',
        ];
    }

    /**
     * Le numero de suivi lisible : TRK-2026-00042.
     *
     * Le formulaire et l'API deposent des ordres par des chemins
     * differents mais dans la meme numerotation : elle vit ici pour
     * qu'aucun des deux ne puisse en inventer une autre.
     */
    public static function prochainNumero(): string
    {
        return 'TRK-'.now()->year.'-'.str_pad((string) (self::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Depose un ordre en lui attribuant son numero de suivi.
     *
     * Le numero se deduit du plus grand identifiant deja pris. Lu puis
     * ecrit en deux temps, ce calcul se trompe des que deux depots se
     * croisent : les deux lisent le meme maximum, fabriquent le meme
     * numero, et le second s'ecrase sur la contrainte d'unicite de la
     * colonne. Le client verrait une erreur serveur pour une commande
     * pourtant valable.
     *
     * Le verrou consultatif serialise les deux depots sur cette seule
     * numerotation, sans bloquer le reste de la table. PostgreSQL le
     * relache de lui-meme a la fin de la transaction, y compris si elle
     * echoue : rien ne peut rester coince.
     *
     * Les deux chemins de depot passent par ici, le formulaire comme
     * l'API, pour qu'aucun ne puisse oublier la precaution.
     */
    public static function deposer(array $attributs): self
    {
        return DB::transaction(function () use ($attributs) {
            DB::statement('select pg_advisory_xact_lock(?)', [self::VERROU_NUMEROTATION]);

            return self::create($attributs + ['tracking_number' => self::prochainNumero()]);
        });
    }

    /** Le code que le client donne pour suivre son colis sans compte. */
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
}
