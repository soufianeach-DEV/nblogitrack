<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use DatesHeureDeBruxelles;
    use HasFactory;

    public const STATUTS = [
        'DRAFT' => 'Brouillon',
        'SENT' => 'Envoyée',
        'PAID' => 'Payée',
        'OVERDUE' => 'En retard',
        'CREDITED' => 'Annulée par avoir',
    ];

    public const FACTURE = 'INVOICE';

    public const AVOIR = 'CREDIT_NOTE';

    public const TAUX_TVA = 21.00;

    protected $fillable = [
        'client_id', 'reference', 'issued_on', 'due_on', 'period_start', 'period_end',
        'amount_excl_tax', 'vat_rate', 'vat_amount', 'amount_incl_tax',
        'reverse_charge', 'status', 'paid_on', 'payment_reference', 'sent_at',
        'type', 'credited_invoice_id', 'credit_reason', 'vat_category',
        'buyer_name', 'buyer_vat_number', 'buyer_peppol_id', 'buyer_address',
        'buyer_postal_code', 'buyer_city', 'buyer_country',
        'stripe_session_id', 'online_payment_pending_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_on' => 'date',
            'sent_at' => 'datetime',
            'online_payment_pending_at' => 'datetime',
            'reverse_charge' => 'boolean',
            'amount_excl_tax' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'amount_incl_tax' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }

    /** La facture qu'un avoir annule. */
    public function creditedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credited_invoice_id');
    }

    /** L'avoir qui annule cette facture. */
    public function creditNote(): HasOne
    {
        return $this->hasOne(Invoice::class, 'credited_invoice_id');
    }

    public function estAvoir(): bool
    {
        return $this->type === self::AVOIR;
    }

    /** Une facture emise, ni reglee, ni annulee : le client la doit. */
    public function estAPayer(): bool
    {
        return ! $this->estAvoir() && $this->status === 'SENT';
    }

    public function montantPaye(): float
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function solde(): float
    {
        return $this->estAvoir() ? 0.0 : max(0.0, round((float) $this->amount_incl_tax - $this->montantPaye(), 2));
    }

    public function estEnRetard(): bool
    {
        return $this->estAPayer() && $this->due_on->isPast();
    }
}
