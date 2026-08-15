<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Une page publique redigee depuis l'administration (A13).
 */
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

    /**
     * Le pied de vitrine est en cache : il se vide a chaque ecriture.
     *
     * Passer par les evenements du modele plutot que par le controleur
     * evite l'oubli le jour ou une page sera modifiee ailleurs.
     */
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

    /**
     * Le texte dans la langue demandee, avec repli sur le francais.
     *
     * Une page legale sans version neerlandaise vaut mieux servie en
     * francais que remplacee par du vide : le visiteur doit pouvoir lire
     * les conditions, meme dans l'autre langue.
     */
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

    /** Vrai quand la langue demandee a son propre texte, repli exclu. */
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
