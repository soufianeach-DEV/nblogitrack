<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $table = 'drivers';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'license_number', 'license_type', 'license_expiry',
        'is_available', 'adr_certified', 'medical_exam_date', 'daily_driving_hours',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'adr_certified' => 'boolean',
            'license_expiry' => 'date',
            'medical_exam_date' => 'date',
        ];
    }
}
