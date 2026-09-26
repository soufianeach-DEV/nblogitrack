<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientContact extends Model
{
    use DatesHeureDeBruxelles;
    use HasFactory;

    protected $table = 'client_contacts';

    public $timestamps = false;

    protected $fillable = [
        'client_id', 'last_name', 'first_name', 'email', 'phone', 'position', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
