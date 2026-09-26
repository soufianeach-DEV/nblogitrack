<?php

namespace App\Support;

use App\Models\TariffGrid;
use Illuminate\Support\Collection;

/**
 * Un trajet entre un pays d'enlevement et un pays de livraison. En ligne :
 * national, export depuis la Belgique et import vers la Belgique. Un
 * trajet entre deux pays etrangers (cabotage compris) se traite sur devis.
 *
 * La zone tarifaire est le pays etranger du trajet : un import Lyon ->
 * Bruxelles coute le prix de l'export Bruxelles -> Lyon, sa ligne miroir.
 */
final class Trajet
{
    public const NATIONAL = 'NATIONAL';

    public const EXPORT = 'EXPORT';

    public const IMPORT = 'IMPORT';

    public const ENTRE_PAYS_ETRANGERS = 'ENTRE_PAYS_ETRANGERS';

    public const CABOTAGE = 'CABOTAGE';

    public readonly string $depart;

    public readonly string $arrivee;

    public function __construct(string $depart, string $arrivee)
    {
        $this->depart = strtoupper(trim($depart)) ?: 'BE';
        $this->arrivee = strtoupper(trim($arrivee)) ?: 'BE';
    }

    public function type(): string
    {
        return match (true) {
            $this->depart === 'BE' && $this->arrivee === 'BE' => self::NATIONAL,
            $this->depart === 'BE' => self::EXPORT,
            $this->arrivee === 'BE' => self::IMPORT,
            $this->depart === $this->arrivee => self::CABOTAGE,
            default => self::ENTRE_PAYS_ETRANGERS,
        };
    }

    public function zone(): ?string
    {
        return match ($this->type()) {
            self::NATIONAL => 'BE',
            self::EXPORT => $this->arrivee,
            self::IMPORT => $this->depart,
            default => null,
        };
    }

    public function estImport(): bool
    {
        return $this->type() === self::IMPORT;
    }

    /**
     * Pourquoi ce trajet ne se commande pas en ligne, ou null.
     */
    public function refus(): ?string
    {
        if (! self::enLigne($this->depart)) {
            return self::surDevis($this->depart)
                ? Traductions::t('msg.enlevement_sur_devis', 'Nous enlevons en :pays sur devis (douane, ferry ou îles) : demandez un devis.', ['pays' => Pays::libelle($this->depart) ?? $this->depart])
                : Traductions::t('msg.enlevement_non_desservi', 'Nous n\'enlevons pas encore en ligne dans ce pays : demandez un devis.');
        }

        if ($this->zone() === null) {
            return Traductions::t('msg.trajet_sur_devis', 'Ce trajet ne commence ni ne finit en Belgique : il se traite sur devis.');
        }

        return null;
    }

    /**
     * @return Collection<int, TariffGrid>
     */
    public function grilles(): Collection
    {
        $zone = $this->zone();

        return $zone === null
            ? collect()
            : TariffGrid::where('zone', $zone)->where('is_active', true)->get();
    }

    /** « France → Belgique », dans la langue de l'ecran. */
    public function fleche(): string
    {
        return (Pays::libelle($this->depart) ?? $this->depart).' → '.(Pays::libelle($this->arrivee) ?? $this->arrivee);
    }

    /** « FR → BE ». */
    public function court(): string
    {
        return $this->depart.' → '.$this->arrivee;
    }

    public static function enLigne(string $pays): bool
    {
        return in_array(strtoupper($pays), config('fret.pays_enlevement', ['BE']), true);
    }

    public static function surDevis(string $pays): bool
    {
        return in_array(strtoupper($pays), config('fret.pays_enlevement_devis', []), true);
    }

    /**
     * Une ile ou un territoire hors TVA (Corse, Baleares, Madere...) :
     * l'enlevement y passe par un devis.
     */
    public static function codePostalExclu(string $pays, ?string $codePostal): bool
    {
        $cp = preg_replace('/\D/', '', (string) $codePostal);

        if ($cp === '') {
            return false;
        }

        foreach (config('fret.cp_exclus.'.strtoupper($pays), []) as $prefixe) {
            if (str_starts_with($cp, $prefixe)) {
                return true;
            }
        }

        return false;
    }

    public static function fuseau(string $pays): string
    {
        return config('fret.fuseaux.'.strtoupper($pays), config('app.timezone'));
    }
}
