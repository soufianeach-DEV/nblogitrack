<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    use DatesHeureDeBruxelles;

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
            'contenu_modifie_le' => 'datetime',
        ];
    }

    private const CONTENU = ['titre_fr', 'titre_nl', 'titre_en', 'corps_fr', 'corps_nl', 'corps_en'];

    protected static function booted(): void
    {
        // Seul un changement de texte fait une nouvelle version : publier ou
        // ranger la page ne demande a personne de la relire.
        static::saving(function (Page $page) {
            if (! $page->exists || $page->isDirty(self::CONTENU) || $page->contenu_modifie_le === null) {
                $page->contenu_modifie_le = $page->freshTimestamp();
            }
        });
        static::saved(fn () => self::oublierPied());
        static::deleted(fn () => self::oublierPied());
    }

    public static function oublierPied(): void
    {
        foreach (array_keys(Translation::LANGUES) as $langue) {
            Cache::forget('pages.pied.'.$langue);
        }
    }

    /** La version du texte, celle dont un chauffeur prend connaissance. */
    public function version(): ?Carbon
    {
        return $this->contenu_modifie_le ?? $this->updated_at;
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
