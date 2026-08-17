<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    protected $fillable = [
        'slug', 'titre_fr', 'titre_nl', 'titre_en',
        'corps_fr', 'corps_nl', 'corps_en',
        'publiee', 'publiee_le', 'au_pied', 'rang', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'publiee' => 'boolean',
            'au_pied' => 'boolean',
            'publiee_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::oublierPied());
        static::deleted(fn () => self::oublierPied());
    }

    public static function oublierPied(): void
    {
        foreach (array_keys(Translation::LANGUES) as $langue) {
            Cache::forget('pages.pied.'.$langue);
        }
    }

    public function titre(string $langue): string
    {
        return $this->texte('titre', $langue);
    }

    public function corps(string $langue): string
    {
        return $this->texte('corps', $langue);
    }

    private function texte(string $champ, string $langue): string
    {
        $valeur = $this->{$champ.'_'.$langue} ?? null;

        return $valeur !== null && trim($valeur) !== '' ? $valeur : $this->{$champ.'_fr'};
    }

    public function traduiteEn(string $langue): bool
    {
        if ($langue === 'fr') {
            return true;
        }

        return trim((string) $this->{'titre_'.$langue}) !== ''
            && trim((string) $this->{'corps_'.$langue}) !== '';
    }

    public function redacteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
