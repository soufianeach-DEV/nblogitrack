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
    ];

    /**
     * Une demande avance, elle ne recule pas : un devis transmis ou une
     * demande classee sans suite est terminee. Seul l'ecran le laissait
     * entendre ; le serveur acceptait n'importe quel retour en arriere.
     */
    public const TRANSITIONS = [
        'PENDING' => ['PROCESSING', 'QUOTED', 'CLOSED'],
        'PROCESSING' => ['QUOTED', 'CLOSED'],
        'QUOTED' => [],
        'CLOSED' => [],
    ];

    protected $table = 'quote_requests';

    protected $fillable = [
        'reference', 'company_name', 'contact_name', 'email', 'phone',
        'vat_number', 'customer_type',
        'pickup_address', 'pickup_lat', 'pickup_lng',
        'delivery_address', 'delivery_lat', 'delivery_lng', 'delivery_country',
        'pickup_date', 'trip_type', 'frequency', 'date_flexibility',
        'goods_type', 'weight', 'volume', 'vehicle_type', 'insurance_value',
        'needs_tail_lift', 'is_hazardous', 'needs_express', 'needs_ecmr',
        'special_instructions', 'status',
        'handled_by', 'handled_at', 'internal_note',
    ];

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

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
