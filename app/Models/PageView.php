<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['jour', 'chemin', 'langue', 'entree', 'source', 'support', 'campagne', 'appareil', 'evenement'];

    protected $casts = [
        'jour' => 'date',
        'entree' => 'boolean',
    ];
}
