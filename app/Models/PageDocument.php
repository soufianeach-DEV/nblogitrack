<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageDocument extends Model
{
    public const TYPES = [
        'application/pdf' => 'PDF',
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'image/webp' => 'WebP',
    ];

    protected $fillable = ['titre', 'nom_origine', 'chemin', 'mime', 'taille', 'uploaded_by'];

    public function estImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
