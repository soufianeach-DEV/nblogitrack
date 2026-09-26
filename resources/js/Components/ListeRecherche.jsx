import { useTraduction } from '@/traduire';
import { useEffect, useId, useMemo, useRef, useState } from 'react';

const normaliser = (texte) => String(texte ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

/**
 * Liste deroulante ou l'on tape pour chercher : « auto » propose
 * « Industrie automobile », « liege » trouve « Liège ». Les choix sont
 * ranges par ordre alphabetique (dans la langue de l'ecran), par groupe
 * quand ils en ont un.
 *
 * options : [{ valeur, libelle, groupe?, desactive?, detail? }]
 */
export default function ListeRecherche({
    id,
    value,
    onChange,
    options,
    placeholder,
    vide,
    className = '',
    disabled = false,
    trier = true,
    'aria-label': ariaLabel,
}) {
    const t = useTraduction();
    const idListe = useId();
    const conteneur = useRef(null);
    const liste = useRef(null);
    const [ouvert, setOuvert] = useState(false);
    const [saisie, setSaisie] = useState('');
    const [survol, setSurvol] = useState(0);

    const langue = typeof document !== 'undefined' ? document.documentElement.lang || 'fr' : 'fr';
    const comparer = useMemo(() => new Intl.Collator(langue, { sensitivity: 'base', numeric: true }).compare, [langue]);

    const choisi = options.find((o) => String(o.valeur) === String(value ?? ''));

    // Tri alphabetique : les groupes entre eux, puis les choix de chaque
    // groupe. Une valeur hors liste (ancienne saisie) reste proposee.
    const rangees = useMemo(() => {
        const toutes = choisi || ! value ? options : [...options, { valeur: value, libelle: String(value) }];
        const triees = trier ? [...toutes].sort((a, b) => comparer(a.groupe ?? '', b.groupe ?? '') || comparer(a.libelle, b.libelle)) : toutes;
        const cherche = normaliser(saisie.trim());

        return cherche === '' ? triees : triees.filter((o) => normaliser(o.libelle).includes(cherche) || normaliser(o.groupe).includes(cherche));
    }, [options, value, saisie, trier, comparer]);

    const choisissables = rangees.filter((o) => ! o.desactive);

    useEffect(() => {
        const dehors = (e) => {
            if (conteneur.current && ! conteneur.current.contains(e.target)) {
                setOuvert(false);
                setSaisie('');
            }
        };
        document.addEventListener('mousedown', dehors);

        return () => document.removeEventListener('mousedown', dehors);
    }, []);

    useEffect(() => {
        liste.current?.querySelector('[data-survol="1"]')?.scrollIntoView({ block: 'nearest' });
    }, [survol, ouvert]);

    const choisir = (option) => {
        if (option.desactive) return;
        onChange(String(option.valeur));
        setOuvert(false);
        setSaisie('');
    };

    const auClavier = (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (! ouvert) { setOuvert(true); return; }
            if (choisissables.length === 0) return;
            const pas = e.key === 'ArrowDown' ? 1 : -1;
            setSurvol((i) => (i + pas + choisissables.length) % choisissables.length);
        } else if (e.key === 'Enter') {
            if (ouvert && choisissables[survol]) {
                e.preventDefault();
                choisir(choisissables[survol]);
            }
        } else if (e.key === 'Escape' || e.key === 'Tab') {
            setOuvert(false);
            setSaisie('');
        }
    };

    let groupePrecedent = null;

    return (
        <div ref={conteneur} className="relative">
            <input
                id={id}
                type="text"
                role="combobox"
                aria-expanded={ouvert}
                aria-controls={idListe}
                aria-autocomplete="list"
                aria-label={ariaLabel}
                autoComplete="off"
                disabled={disabled}
                value={ouvert ? saisie : (choisi?.libelle ?? (value ? String(value) : ''))}
                placeholder={ouvert && choisi ? choisi.libelle : (vide ?? placeholder ?? t('liste.choisir', 'Tapez pour chercher…'))}
                onChange={(e) => { setSaisie(e.target.value); setSurvol(0); setOuvert(true); }}
                onFocus={() => { setOuvert(true); setSurvol(0); }}
                onClick={() => setOuvert(true)}
                onKeyDown={auClavier}
                className={className + ' pr-8'}
            />
            <span className="pointer-events-none absolute inset-y-0 right-2 flex items-center text-slate-400">▾</span>

            {ouvert && ! disabled && (
                <ul
                    id={idListe}
                    ref={liste}
                    role="listbox"
                    className="absolute z-30 mt-1 max-h-72 w-full min-w-[14rem] overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg"
                >
                    {vide !== undefined && saisie === '' && (
                        <li
                            role="option"
                            aria-selected={! value}
                            onMouseDown={(e) => { e.preventDefault(); onChange(''); setOuvert(false); }}
                            className="cursor-pointer px-3 py-1.5 text-slate-500 hover:bg-surface"
                        >
                            {vide}
                        </li>
                    )}
                    {rangees.length === 0 && (
                        <li className="px-3 py-2 text-slate-500">{t('liste.aucun', 'Aucun résultat')}</li>
                    )}
                    {rangees.map((o) => {
                        const titre = o.groupe && o.groupe !== groupePrecedent ? o.groupe : null;
                        groupePrecedent = o.groupe ?? null;
                        const rang = choisissables.indexOf(o);

                        return (
                            <li key={(o.groupe ?? '') + '|' + o.valeur} role="presentation">
                                {titre && <div className="px-3 pb-1 pt-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">{titre}</div>}
                                <div
                                    role="option"
                                    aria-selected={String(o.valeur) === String(value ?? '')}
                                    aria-disabled={o.desactive || undefined}
                                    data-survol={rang === survol ? '1' : undefined}
                                    onMouseDown={(e) => { e.preventDefault(); choisir(o); }}
                                    onMouseEnter={() => rang >= 0 && setSurvol(rang)}
                                    className={
                                        'px-3 py-1.5 ' + (o.groupe ? 'pl-5 ' : '')
                                        + (o.desactive ? 'cursor-not-allowed text-slate-400 ' : 'cursor-pointer ')
                                        + (rang === survol ? 'bg-marine/10 ' : '')
                                        + (String(o.valeur) === String(value ?? '') ? 'font-semibold text-marine' : '')
                                    }
                                >
                                    {o.libelle}
                                    {o.detail && <span className="ml-1 text-xs text-slate-500">{o.detail}</span>}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
