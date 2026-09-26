<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Titre, description et apercu de partage de chaque page, poses par le
 * serveur dans le HTML : les robots des reseaux sociaux (LinkedIn,
 * Facebook, WhatsApp) ne lisent pas le JavaScript, et Google les lit plus
 * vite ainsi. Les pages privees sont marquees « noindex ».
 */
final class Referencement
{
    /** Pages publiques, indexables. */
    private const PUBLIQUES = ['Welcome', 'Tarifs/Index', 'Devis/Create', 'Pages/Show', 'Auth/Register', 'Tracking/Show'];

    /**
     * @param  array<string, mixed>  $props
     * @return array{titre: string, description: string, image: string, indexable: bool, type: string, locale: string}
     */
    public static function pour(string $composant, array $props, string $langue): array
    {
        $marque = config('app.name', 'NBLogiTrack');

        [$titre, $description] = match ($composant) {
            'Welcome' => [
                Traductions::t('referencement.accueil_titre', 'Transport et logistique B2B en Belgique et en Europe'),
                Traductions::t('referencement.accueil_description', 'Transport de marchandises pour les entreprises : enlèvement en Belgique et dans 21 pays européens, prix en ligne, suivi en temps réel et facturation Peppol.'),
            ],
            'Tarifs/Index' => [
                Traductions::t('referencement.tarifs_titre', 'Tarifs de transport et simulateur de prix'),
                Traductions::t('referencement.tarifs_description', 'Simulez le prix d\'un transport de marchandises depuis ou vers la Belgique : tarif au kilomètre et au kilo, délais, formules Éco, Standard et Express.'),
            ],
            'Devis/Create' => [
                Traductions::t('referencement.devis_titre', 'Demander un devis de transport'),
                Traductions::t('referencement.devis_description', 'Devis de transport gratuit et sans engagement : palettes, colis, ADR, température dirigée. Réponse d\'un conseiller sous 24 heures ouvrées.'),
            ],
            'Auth/Register' => [
                Traductions::t('referencement.inscription_titre', 'Créer un espace client'),
                Traductions::t('referencement.inscription_description', 'Ouvrez un compte entreprise pour commander vos transports en ligne, suivre vos expéditions et retrouver vos factures.'),
            ],
            'Pages/Show' => [
                (string) ($props['page']['titre'] ?? $marque),
                self::extrait((string) ($props['page']['corps'] ?? '')),
            ],
            default => [
                Traductions::t('referencement.defaut_titre', 'Espace client'),
                Traductions::t('referencement.accueil_description', 'Transport de marchandises pour les entreprises : enlèvement en Belgique et dans 21 pays européens, prix en ligne, suivi en temps réel et facturation Peppol.'),
            ],
        };

        return [
            'titre' => $titre.' - '.$marque,
            'description' => Str::limit($description, 160),
            'image' => url('/images/partage.png'),
            // Une page publique ouverte avec des parametres (resultat de
            // suivi, filtre, campagne) n'est pas une page a indexer.
            'indexable' => in_array($composant, self::PUBLIQUES, true) && request()->query->count() === 0,
            'type' => $composant === 'Welcome' ? 'website' : 'article',
            'locale' => ['fr' => 'fr_BE', 'nl' => 'nl_BE', 'en' => 'en_GB'][$langue] ?? 'fr_BE',
        ];
    }

    /** Premier paragraphe d'une page en Markdown, sans les titres. */
    private static function extrait(string $markdown): string
    {
        foreach (preg_split('/\R{2,}/', $markdown) as $bloc) {
            $bloc = trim($bloc);
            if ($bloc !== '' && ! str_starts_with($bloc, '#')) {
                return trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['**', '*', '- '], '', $bloc))));
            }
        }

        return '';
    }

    /**
     * La fiche de l'entreprise pour les moteurs (schema.org), sur l'accueil.
     *
     * @return array<string, mixed>
     */
    public static function organisation(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'NBLogiTrack SRL',
            'url' => url('/'),
            'logo' => url('/images/logo-marine.png'),
            'vatID' => 'BE0123456749',
            'email' => 'info@nblogitrack.be',
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Bruxelles', 'addressCountry' => 'BE'],
            'areaServed' => 'Europe',
            'sameAs' => array_values(array_filter(config('services.reseaux', []))),
        ];
    }
}
