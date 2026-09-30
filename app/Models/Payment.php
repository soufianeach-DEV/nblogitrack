<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use DatesHeureDeBruxelles;

    public const METHODES = ['TRANSFER', 'STRIPE', 'CASH', 'OTHER'];

    protected $fillable = ['invoice_id', 'amount', 'paid_on', 'method', 'reference', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
