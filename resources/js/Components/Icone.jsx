// Icones dessinees dans l'application plutot que chargees depuis une police
// distante : rien a telecharger, et l'interface reste complete hors ligne.
const TRACES = {
    dashboard: 'M4 4h6v6H4zM14 4h6v4h-6zM14 12h6v8h-6zM4 14h6v6H4z',
    colis: 'M12 3 3 7v10l9 4 9-4V7zM3 7l9 4 9-4M12 11v10',
    planning: 'M4 5h16v15H4zM8 3v4M16 3v4M4 10h16M9 15l2 2 4-4',
    camion: 'M2 6h12v10H2zM14 9h4l3 3v4h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
    valide: 'M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6zM9 12l2 2 4-4',
    journal: 'M3.5 12a8.5 8.5 0 1 0 2.8-6.3M3 4v4h4M12 7.5V12l3 2',
    sortie: 'M14 4h5v16h-5M3 12h11M10 8l4 4-4 4',
    horloge: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
    rotation: 'M20 11a8 8 0 0 0-13.7-5.7L4 7M4 3.5V8h4.5M4 13a8 8 0 0 0 13.7 5.7L20 17M20 20.5V16h-4.5',
    coche: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM8 12l3 3 5-5',
    menu: 'M4 7h16M4 12h16M4 17h16',
    recherche: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3',
    aide: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01',
    plus: 'M12 5v14M5 12h14',
    fermer: 'M6 6l12 12M18 6L6 18',
    agrandir: 'M9 4H4v5M4 4l6 6M15 20h5v-5M20 20l-6-6',
    reduire: 'M4 9h5V4M4 4l5 5M20 15h-5v5M20 20l-5-5',
    peage: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM14.5 9.5a3 3 0 1 0 0 5M8.5 11h5M8.5 13.5h5',
    profil: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 20c0-3.3 3.6-5.5 8-5.5s8 2.2 8 5.5',
    retour: 'M19 12H5M11 18l-6-6 6-6',
};

export default function Icone({ nom, className = 'h-5 w-5' }) {
    const trace = TRACES[nom];

    if (! trace) {
        return null;
    }

    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <path d={trace} />
        </svg>
    );
}
