// Un champ date ne montrait son calendrier qu'au clic sur la petite icone
// (Chrome, Edge) : un clic n'importe ou dans le champ l'ouvre desormais.
// Au clavier, rien ne change : la tabulation ne deroule rien, on saisit
// la date au clavier comme avant.
const AVEC_CALENDRIER = new Set(['date', 'datetime-local', 'month', 'week', 'time']);

document.addEventListener('click', (evenement) => {
    const champ = evenement.target;

    if (! (champ instanceof HTMLInputElement) || ! AVEC_CALENDRIER.has(champ.type)
        || champ.disabled || champ.readOnly || typeof champ.showPicker !== 'function') {
        return;
    }

    try {
        champ.showPicker();
    } catch {
        // Calendrier deja ouvert, ou navigateur qui le refuse : le champ
        // reste utilisable au clavier.
    }
});
