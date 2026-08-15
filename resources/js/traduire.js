import { usePage } from '@inertiajs/react';

/**
 * Traduit une cle dans la langue de la page.
 *
 * Le second argument est le texte francais ecrit en clair dans le
 * composant. Il sert de repli et rend le code lisible : on voit la
 * phrase a sa place au lieu de devoir ouvrir un dictionnaire pour
 * savoir ce que la page affiche.
 *
 * Les valeurs entre accolades sont remplacees : t('suivi.bonjour',
 * 'Bonjour :nom', { nom: 'Sofie' }).
 */
export function useTraduction() {
    const { dictionnaire = {} } = usePage().props;

    return (cle, defaut = '', valeurs = {}) => {
        const texte = dictionnaire[cle] ?? defaut;

        return Object.entries(valeurs).reduce(
            (sortie, [nom, valeur]) => sortie.replaceAll(`:${nom}`, valeur),
            texte,
        );
    };
}

/** La langue courante, pour les formats de date et de nombre. */
export function useLangue() {
    const { langue = 'fr' } = usePage().props;

    return langue;
}

/** Le code de locale complet, celui qu'attend toLocaleString. */
export function useLocale() {
    return { fr: 'fr-BE', nl: 'nl-BE', en: 'en-GB' }[useLangue()] ?? 'fr-BE';
}
