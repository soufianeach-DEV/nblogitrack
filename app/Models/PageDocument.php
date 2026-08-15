<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un fichier mis a disposition des visiteurs (A13) : tarif en PDF,
 * conditions signees, visuel d'une page.
 */
class PageDocument extends Model
{
    /**
     * Ce qui peut etre televerse. La liste est courte a dessein : tout
     * type accepte est un type a servir, et un fichier servi depuis le
     * meme domaine que l'application peut lui nuire.
     */
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
