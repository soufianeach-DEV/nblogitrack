<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use DatesHeureDeBruxelles;
    use HasFactory;

    public $timestamps = false;

    // Nature d'une ligne : le transport lui-meme, l'indemnite d'une
    // annulation tardive, ou un supplement pose sur l'expedition.
    public const TRANSPORT = 'TRANSPORT';

    public const ANNULATION = 'CANCELLATION';

    public const SUPPLEMENT = 'SURCHARGE';

    protected $fillable = [
        'invoice_id', 'kind', 'transport_order_id', 'order_charge_id', 'description',
        'quantity', 'unit_price', 'amount_excl_tax', 'vat_category', 'vat_rate', 'active',
    ];

    protected function casts(): array
    {
        return [
            'amount_excl_tax' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'quantity' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function orderCharge(): BelongsTo
    {
        return $this->belongsTo(OrderCharge::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function transportOrder(): BelongsTo
    {
        return $this->belongsTo(TransportOrder::class);
    }
}
