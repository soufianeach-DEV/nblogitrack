<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $table = 'clients';
    public $incrementing = false;   // l'id vient de users
    public $timestamps = false;     // T08 : pas de timestamps

    protected $fillable = [
        'id', 'company_name', 'vat_number', 'enterprise_number', 'peppol_id',
        'billing_address', 'city', 'postal_code', 'country',
        'is_validated', 'business_sector', 'credit_limit', 'payment_terms',
    ];

    protected function casts(): array
    {
        return ['is_validated' => 'boolean'];
    }
}