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

/**
 * Traduit une valeur de vocabulaire venue de la base : fonction d'un
 * contact, secteur d'activite, nature d'une marchandise.
 *
 * La cle se derive de la valeur francaise enregistree. Une saisie libre
 * inconnue du dictionnaire retombe sur cette valeur, ce qui vaut mieux
 * qu'une cle technique affichee au client. Le meme calcul existe cote
 * serveur dans Traductions::cleDepuis.
 *
 * Les adresses n'y passent pas : une adresse postale est un identifiant.
 */
export function useVocabulaire() {
    const t = useTraduction();

    return (groupe, valeur) => {
        if (! valeur) return valeur;

        // Les diacritiques s'ecrivent en echappement : leur plage depend
        // sinon de l'encodage du fichier source.
        const cle = valeur
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');

        return t(`vocab.${groupe}.${cle}`, valeur);
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
