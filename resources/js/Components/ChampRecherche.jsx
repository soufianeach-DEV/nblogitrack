import { useTraduction } from '@/traduire';
import { useEffect, useId, useRef, useState } from 'react';

const normaliser = (texte) => String(texte ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

/**
 * Champ de recherche avec suggestions : le serveur renvoie, pour le texte
 * tape, les valeurs qui existent (immatriculations, noms, villes...). Un
 * clic ou Entree sur une suggestion la reprend dans le champ.
 */
export default function ChampRecherche({ value, onChange, suggestions = [], local = false, placeholder, className = '', icone = null, id }) {
    const t = useTraduction();
    const idListe = useId();
    const conteneur = useRef(null);
    const [ouvert, setOuvert] = useState(false);
    const [survol, setSurvol] = useState(-1);

    useEffect(() => {
        const dehors = (e) => conteneur.current && ! conteneur.current.contains(e.target) && setOuvert(false);
        document.addEventListener('mousedown', dehors);

        return () => document.removeEventListener('mousedown', dehors);
    }, []);

    // « local » : la liste complete est dans la page, on la filtre ici.
    // Rien a proposer quand la suggestion est deja ce qui est tape.
    const cherche = normaliser(value);
    const proposees = (local ? suggestions.filter((s) => normaliser(s).includes(cherche)).slice(0, 50) : suggestions)
        .filter((s) => normaliser(s) !== cherche);
    // Une liste locale s'ouvre des le clic ; une recherche serveur attend
    // deux caracteres.
    const visible = ouvert && proposees.length > 0 && (local || String(value ?? '').trim().length >= 2);

    const prendre = (s) => {
        onChange(s);
        setOuvert(false);
        setSurvol(-1);
    };

    const auClavier = (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (proposees.length === 0) return;
            e.preventDefault();
            setOuvert(true);
            const pas = e.key === 'ArrowDown' ? 1 : -1;
            setSurvol((i) => (i + pas + proposees.length) % proposees.length);
        } else if (e.key === 'Enter' && visible && proposees[survol]) {
            e.preventDefault();
            prendre(proposees[survol]);
        } else if (e.key === 'Escape' || e.key === 'Tab') {
            setOuvert(false);
        }
    };

    return (
        <div ref={conteneur} className="relative">
            {icone}
            <input
                id={id}
                value={value}
                onChange={(e) => { onChange(e.target.value); setOuvert(true); setSurvol(-1); }}
                onFocus={() => setOuvert(true)}
                onBlur={() => setOuvert(false)}
                onKeyDown={auClavier}
                placeholder={placeholder}
                // Avec un id, c'est le <label for> qui nomme le champ : le
                // texte d'exemple n'est pas son nom.
                aria-label={id ? undefined : placeholder}
                aria-activedescendant={visible && survol >= 0 ? `${idListe}-o${survol}` : undefined}
                role="combobox"
                aria-expanded={visible}
                aria-controls={idListe}
                aria-autocomplete="list"
                autoComplete="off"
                className={className}
            />
            {visible && (
                <ul id={idListe} role="listbox" className="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                    <li role="presentation" className="px-3 pb-1 pt-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-500">{t('liste.suggestions', 'Suggestions')}</li>
                    {proposees.map((s, i) => (
                        <li
                            key={s}
                            id={`${idListe}-o${i}`}
                            role="option"
                            aria-selected={i === survol}
                            onMouseDown={(e) => { e.preventDefault(); prendre(s); }}
                            onMouseEnter={() => setSurvol(i)}
                            className={'cursor-pointer px-3 py-1.5 ' + (i === survol ? 'bg-marine/10' : 'hover:bg-surface')}
                        >
                            {s}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
