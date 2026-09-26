<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequest extends Model
{
    use DatesHeureDeBruxelles;

    public const UPDATED_AT = null;

    public const STATUTS = [
        'PENDING' => 'Nouvelle',
        'PROCESSING' => 'Prise en charge',
        'QUOTED' => 'Devis transmis',
        'CLOSED' => 'Sans suite',
        'ORDERED' => 'Transformée en commande',
    ];

    /**
     * Une demande avance, elle ne recule pas : un devis transmis ou une
     * demande classee sans suite est terminee. Seul l'ecran le laissait
     * entendre ; le serveur acceptait n'importe quel retour en arriere.
     */
    public const TRANSITIONS = [
        'PENDING' => ['PROCESSING', 'QUOTED', 'CLOSED', 'ORDERED'],
        'PROCESSING' => ['QUOTED', 'CLOSED', 'ORDERED'],
        // Un devis accepte devient une commande.
        'QUOTED' => ['ORDERED'],
        'CLOSED' => [],
        'ORDERED' => [],
    ];

    protected $table = 'quote_requests';

    protected $fillable = [
        'reference', 'company_name', 'contact_name', 'email', 'phone',
        'vat_number', 'customer_type',
        'pickup_address', 'pickup_country', 'pickup_lat', 'pickup_lng',
        'delivery_address', 'delivery_lat', 'delivery_lng', 'delivery_country',
        'pickup_date', 'trip_type', 'frequency', 'date_flexibility',
        'goods_type', 'weight', 'volume', 'vehicle_type', 'insurance_value',
        'needs_tail_lift', 'is_hazardous', 'needs_express', 'needs_ecmr',
        'special_instructions', 'status',
        'handled_by', 'handled_at', 'internal_note',
        'end_client_name', 'legal_form', 'sector', 'eori_number', 'billing_street', 'billing_postal_code', 'billing_city',
        'billing_country', 'correspondence_language', 'contact_function', 'mobile_phone', 'billing_email',
        'preferred_channel', 'callback_slot',
        'pickup_contact_name', 'pickup_contact_phone', 'pickup_opening_hours', 'pickup_time_slot',
        'pickup_has_dock', 'pickup_appointment', 'pickup_access', 'pickup_access_notes',
        'delivery_contact_name', 'delivery_contact_phone', 'delivery_opening_hours', 'delivery_time_slot',
        'delivery_has_dock', 'delivery_appointment', 'delivery_access', 'delivery_access_notes', 'delivery_date',
        'packages', 'declared_value', 'needs_temperature', 'temperature_min', 'temperature_max',
        'un_number', 'adr_class', 'packing_group',
        'monthly_volume', 'budget', 'response_deadline', 'attachments', 'privacy_accepted_at', 'converted_order_id',
    ];

    /** Pays hors union douaniere : l'EORI est indispensable a la douane. */
    public const PAYS_DOUANE = ['CH', 'GB', 'NO'];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'created_at' => 'datetime',
            'handled_at' => 'datetime',
            'needs_tail_lift' => 'boolean',
            'is_hazardous' => 'boolean',
            'needs_express' => 'boolean',
            'needs_ecmr' => 'boolean',
            'needs_temperature' => 'boolean',
            'pickup_has_dock' => 'boolean',
            'delivery_has_dock' => 'boolean',
            'pickup_appointment' => 'boolean',
            'delivery_appointment' => 'boolean',
            'pickup_access' => 'array',
            'delivery_access' => 'array',
            'packages' => 'array',
            'attachments' => 'array',
            'delivery_date' => 'date',
            'response_deadline' => 'date',
            'privacy_accepted_at' => 'datetime',
            'declared_value' => 'decimal:2',
            'budget' => 'decimal:2',
            'temperature_min' => 'decimal:1',
            'temperature_max' => 'decimal:1',
        ];
    }

    public function options(): array
    {
        return array_keys(array_filter([
            'Hayon élévateur' => $this->needs_tail_lift,
            'Marchandise dangereuse (ADR)' => $this->is_hazardous,
            'Livraison express' => $this->needs_express,
            'Preuve de livraison (e-CMR)' => $this->needs_ecmr,
        ]));
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(TransportOrder::class, 'converted_order_id');
    }

    /**
     * Poids total des colis declares, en kg (null sans colis pese).
     *
     * @param  list<array<string, mixed>>|null  $colis
     */
    public static function poidsDesColis(?array $colis): ?float
    {
        $total = collect($colis ?? [])->sum(fn (array $c) => (int) ($c['quantite'] ?? 0) * (float) ($c['poids_unitaire'] ?? 0));

        return $total > 0 ? round($total, 1) : null;
    }

    /**
     * Volume total des colis mesures, en m3 (null sans dimensions).
     *
     * @param  list<array<string, mixed>>|null  $colis
     */
    public static function volumeDesColis(?array $colis): ?float
    {
        $total = collect($colis ?? [])->sum(fn (array $c) => (int) ($c['quantite'] ?? 0)
            * (float) ($c['longueur'] ?? 0) * (float) ($c['largeur'] ?? 0) * (float) ($c['hauteur'] ?? 0) / 1_000_000);

        return $total > 0 ? round($total, 2) : null;
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
