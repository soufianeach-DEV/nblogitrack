import ChampRecherche from '@/Components/ChampRecherche';
import Icone from '@/Components/Icone';
import ListeRecherche from '@/Components/ListeRecherche';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function BarreFiltres({ adresse, filtres, placeholder, listes = [], compteurs = [], suggestions = [] }) {
    const [saisie, setSaisie] = useState(filtres.q ?? '');

    useEffect(() => {
        if ((filtres.q ?? '') === saisie) {
            return undefined;
        }

        const minuteur = setTimeout(() => {
            router.get(adresse, { ...filtres, q: saisie || undefined }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(minuteur);
    }, [saisie]);

    const appliquer = (champ, valeur) => router.get(
        adresse,
        { ...filtres, [champ]: valeur || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );

    return (
        <div className="rounded-2xl bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-center gap-3">
                <div className="min-w-[16rem] flex-1">
                    <ChampRecherche
                        value={saisie}
                        onChange={setSaisie}
                        suggestions={suggestions}
                        placeholder={placeholder}
                        icone={(
                            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-600">
                                <Icone nom="recherche" className="h-5 w-5" />
                            </span>
                        )}
                        className="w-full rounded-lg border-slate-300 py-2.5 pl-10 text-sm shadow-sm focus:border-marine focus:ring-marine"
                    />
                </div>

                {listes.map((liste) => (
                    <div key={liste.champ} className="min-w-[12rem]">
                        <ListeRecherche
                            value={filtres[liste.champ] ?? ''}
                            onChange={(valeur) => appliquer(liste.champ, valeur)}
                            vide={liste.intitule}
                            aria-label={liste.intitule}
                            trier={liste.trier ?? true}
                            options={liste.options.map((option) => (typeof option === 'string' ? { valeur: option, libelle: option } : option))}
                            className="w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                    </div>
                ))}
            </div>

            {compteurs.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {compteurs.map((compteur) => {
                        const actif = (filtres.etat ?? '') === (compteur.valeur ?? '');

                        return (
                            <button
                                key={compteur.libelle}
                                type="button"
                                onClick={() => appliquer('etat', compteur.valeur)}
                                aria-pressed={actif}
                                className={`rounded-full px-3 py-1 text-sm font-medium transition ${
                                    actif
                                        ? 'bg-marine text-white'
                                        : compteur.alerte
                                            ? 'bg-status-incident/10 text-status-incident hover:bg-status-incident/20'
                                            : 'bg-surface text-slate-700 hover:bg-slate-200'
                                }`}
                            >
                                {compteur.libelle}
                                <span className="ml-1.5 opacity-70">{compteur.nombre}</span>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
