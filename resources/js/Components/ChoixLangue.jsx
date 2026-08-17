import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function ChoixLangue({ className = '', sombre = false }) {
    const { langue, langues = {} } = usePage().props;
    const codes = Object.keys(langues);
    const [ouvert, setOuvert] = useState(false);
    const conteneur = useRef(null);

    useEffect(() => {
        if (! ouvert) return;

        const auClic = (evenement) => {
            if (! conteneur.current?.contains(evenement.target)) setOuvert(false);
        };
        const auClavier = (evenement) => {
            if (evenement.key === 'Escape') setOuvert(false);
        };

        document.addEventListener('mousedown', auClic);
        document.addEventListener('keydown', auClavier);

        return () => {
            document.removeEventListener('mousedown', auClic);
            document.removeEventListener('keydown', auClavier);
        };
    }, [ouvert]);

    if (codes.length < 2) return null;

    return (
        <div ref={conteneur} className={`relative ${className}`}>
            <button
                type="button"
                onClick={() => setOuvert((etat) => ! etat)}
                aria-haspopup="menu"
                aria-expanded={ouvert}
                aria-label={'Langue : ' + (langues[langue] ?? langue)}
                className={`flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold uppercase transition ${
                    sombre
                        ? 'text-white/80 hover:bg-white/10 hover:text-white'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-marine'
                }`}
            >
                {langue}
                <svg
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                    className={`h-3.5 w-3.5 transition-transform ${ouvert ? 'rotate-180' : ''}`}
                >
                    <path
                        fillRule="evenodd"
                        d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                        clipRule="evenodd"
                    />
                </svg>
            </button>

            {ouvert && (
                <div
                    role="menu"
                    className="absolute end-0 z-50 mt-2 w-44 overflow-hidden rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5"
                >
                    {codes.map((code) => (
                        <a
                            key={code}
                            href={'/langue/' + code}
                            role="menuitem"
                            aria-current={code === langue ? 'true' : undefined}
                            lang={code}
                            className={`flex items-center justify-between gap-3 px-3 py-2 text-sm transition ${
                                code === langue
                                    ? 'bg-slate-50 font-semibold text-marine'
                                    : 'text-slate-700 hover:bg-slate-50 hover:text-marine'
                            }`}
                        >
                            <span>{langues[code]}</span>
                            <span className="text-xs font-semibold uppercase text-slate-400">
                                {code}
                            </span>
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}
