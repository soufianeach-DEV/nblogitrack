import Icone from '@/Components/Icone';
import { useTraduction } from '@/traduire';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * La barre de recherche du bandeau, avec suggestions.
 *
 * Elle envoyait la saisie telle quelle et rechargeait une liste
 * filtree : il fallait connaitre le debut exact d'un nom d'entreprise
 * ou le numero complet d'une expedition pour trouver quelque chose.
 *
 * Elle propose maintenant pendant la frappe et mene directement a la
 * fiche. La touche Entree sans selection garde l'ancien comportement :
 * quelqu'un qui tape vite et valide retombe sur la liste filtree plutot
 * que sur rien.
 *
 * Ce qui est propose depend du role, mais c'est le serveur qui en
 * decide : ce composant affiche ce qu'on lui rend, il ne filtre rien.
 */
export default function RechercheGlobale({ placeholder, onValider }) {
    const t = useTraduction();
    const [terme, setTerme] = useState('');
    const [suggestions, setSuggestions] = useState([]);
    const [ouvert, setOuvert] = useState(false);
    const [survol, setSurvol] = useState(-1);
    const [cherche, setCherche] = useState(false);

    const conteneur = useRef(null);
    const minuteur = useRef(null);
    const dernier = useRef(0);

    // Un clic ailleurs referme la liste : sans cela elle resterait
    // ouverte par-dessus la page qu'on vient d'atteindre.
    useEffect(() => {
        const dehors = (e) => {
            if (conteneur.current && ! conteneur.current.contains(e.target)) {
                setOuvert(false);
            }
        };

        document.addEventListener('mousedown', dehors);

        return () => document.removeEventListener('mousedown', dehors);
    }, []);

    const chercher = (valeur) => {
        setTerme(valeur);
        setSurvol(-1);
        clearTimeout(minuteur.current);

        if (valeur.trim().length < 3) {
            setSuggestions([]);
            setOuvert(false);

            return;
        }

        // Un appel par frappe saturerait le serveur pour rien : on attend
        // que la saisie se pose.
        minuteur.current = setTimeout(async () => {
            const appel = ++dernier.current;
            setCherche(true);

            try {
                const r = await fetch(route('recherche.suggestions', { q: valeur }), {
                    headers: { Accept: 'application/json' },
                });
                const json = await r.json();

                // Une reponse arrivee apres une plus recente ne doit pas
                // ecraser celle qu'on affiche deja.
                if (appel !== dernier.current) return;

                setSuggestions(json.suggestions ?? []);
                setOuvert(true);
            } catch {
                setSuggestions([]);
            } finally {
                if (appel === dernier.current) setCherche(false);
            }
        }, 250);
    };

    const aller = (suggestion) => {
        setOuvert(false);
        setTerme('');
        router.get(suggestion.url);
    };

    const auClavier = (e) => {
        if (e.key === 'Escape') {
            setOuvert(false);

            return;
        }

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();

            if (suggestions.length === 0) return;

            const pas = e.key === 'ArrowDown' ? 1 : -1;
            setSurvol((i) => (i + pas + suggestions.length) % suggestions.length);
            setOuvert(true);

            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();

            if (survol >= 0 && suggestions[survol]) {
                aller(suggestions[survol]);
            } else if (terme.trim() !== '') {
                setOuvert(false);
                onValider(terme);
            }
        }
    };

    return (
        <div ref={conteneur} className="relative hidden w-full max-w-md sm:block">
            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-600">
                <Icone nom="recherche" className="h-5 w-5" />
            </span>

            <input
                value={terme}
                onChange={(e) => chercher(e.target.value)}
                onKeyDown={auClavier}
                onFocus={() => suggestions.length > 0 && setOuvert(true)}
                placeholder={placeholder}
                aria-label={placeholder}
                role="combobox"
                aria-expanded={ouvert}
                aria-controls="suggestions-recherche"
                aria-autocomplete="list"
                autoComplete="off"
                className="w-full rounded-lg border-slate-200 bg-surface py-2 pl-10 pr-9 text-sm shadow-sm focus:border-marine focus:ring-marine"
            />

            {cherche && (
                <span className="absolute inset-y-0 right-3 flex items-center text-xs text-slate-500">…</span>
            )}

            {ouvert && (
                <ul
                    id="suggestions-recherche"
                    role="listbox"
                    className="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                >
                    {suggestions.length === 0 && (
                        <li className="px-4 py-3 text-sm text-slate-600">
                            {t('action.aucun_resultat', 'Aucun résultat.')}
                        </li>
                    )}

                    {suggestions.map((s, i) => (
                        <li key={s.type + s.libelle + i} role="option" aria-selected={i === survol}>
                            <button
                                type="button"
                                onMouseEnter={() => setSurvol(i)}
                                onClick={() => aller(s)}
                                className={
                                    'flex w-full items-center gap-3 px-4 py-2.5 text-left transition ' +
                                    (i === survol ? 'bg-surface' : 'hover:bg-surface')
                                }
                            >
                                <Icone
                                    nom={s.type === 'entreprise' ? 'valide' : 'colis'}
                                    className="h-4 w-4 shrink-0 text-slate-500"
                                />
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-semibold text-marine">{s.libelle}</span>
                                    <span className="block truncate text-xs text-slate-600">{s.detail}</span>
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
