import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTraduction } from '@/traduire';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function Ligne({ traduction, langues }) {
    const t = useTraduction();
    const [ouverte, setOuverte] = useState(false);
    const { data, setData, patch, processing, errors, isDirty } = useForm({
        fr: traduction.fr ?? '',
        nl: traduction.nl ?? '',
        en: traduction.en ?? '',
    });

    const enregistrer = (e) => {
        e.preventDefault();
        patch(route('translations.update', traduction.id), {
            preserveScroll: true,
            onSuccess: () => setOuverte(false),
        });
    };

    const manquantes = Object.keys(langues).filter((c) => ! (traduction[c] ?? '').trim());

    return (
        <div className="rounded-xl border border-slate-200 bg-white">
            <button
                type="button"
                onClick={() => setOuverte(! ouverte)}
                className="flex w-full items-center justify-between gap-4 px-4 py-3 text-left transition hover:bg-surface"
            >
                <span className="min-w-0">
                    <span className="block truncate font-mono text-xs text-slate-500">{traduction.cle}</span>
                    <span className="mt-0.5 block truncate text-sm text-marine">{traduction.fr}</span>
                </span>
                <span className="flex shrink-0 items-center gap-2">
                    {manquantes.length > 0 && (
                        <span className="rounded-full bg-status-incident/10 px-2.5 py-0.5 text-xs font-medium uppercase text-status-incident">
                            {manquantes.join(' ')} {manquantes.length > 1
                                ? t('trad.manquants', 'manquants')
                                : t('trad.manquant', 'manquant')}
                        </span>
                    )}
                    <span className="text-xs text-slate-500">
                        {ouverte ? t('action.fermer', 'Fermer') : t('trad.modifier', 'Modifier')}
                    </span>
                </span>
            </button>

            {ouverte && (
                <form onSubmit={enregistrer} className="border-t border-slate-100 px-4 py-4">
                    <div className="grid gap-3 lg:grid-cols-3">
                        {Object.entries(langues).map(([code, nom]) => (
                            <label key={code} className="block">
                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    {nom}
                                    {code === 'fr' && <span className="ml-1 text-status-incident">*</span>}
                                </span>
                                <textarea
                                    value={data[code]}
                                    onChange={(e) => setData(code, e.target.value)}
                                    rows={2}
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                                    placeholder={code === 'fr' ? '' : data.fr}
                                />
                                {errors[code] && <p className="mt-1 text-xs text-status-incident">{errors[code]}</p>}
                            </label>
                        ))}
                    </div>

                    <p className="mt-3 text-xs text-slate-600">
                        {t('trad.francais_fait_foi', 'Le français fait foi. Une langue laissée vide affiche le texte français plutôt qu\'une clé technique.')}
                    </p>

                    <div className="mt-3 flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setOuverte(false)}
                            className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-surface"
                        >
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button
                            type="submit"
                            disabled={processing || ! isDirty}
                            className="rounded-lg bg-action px-4 py-2 text-sm font-semibold text-marine-deep transition hover:bg-action-dark disabled:opacity-50"
                        >
                            {t('action.enregistrer', 'Enregistrer')}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function Index({ traductions, groupes, langues, recherche, groupe, manquantes }) {
    const t = useTraduction();
    const [terme, setTerme] = useState(recherche ?? '');

    const chercher = (e) => {
        e.preventDefault();
        router.get(route('translations.index'), { recherche: terme, groupe }, { preserveState: true });
    };

    const couverture = (code) => Math.round(((manquantes.total - manquantes[code]) / manquantes.total) * 100);

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">{t('nav.traductions', 'Traductions')}</h1>}>
            <Head title={t('nav.traductions', 'Traductions')} />

            <div className="mb-5 grid gap-4 sm:grid-cols-3">
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('trad.cles', 'Clés')}</p>
                    <p className="mt-1 text-2xl font-bold text-marine">{manquantes.total}</p>
                </div>
                {['nl', 'en'].map((code) => (
                    <div key={code} className="rounded-2xl bg-white p-5 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-slate-600">{langues[code]}</p>
                        <p className={`mt-1 text-2xl font-bold ${manquantes[code] > 0 ? 'text-status-incident' : 'text-status-delivered'}`}>
                            {couverture(code)} %
                        </p>
                        <p className="text-xs text-slate-600">
                            {manquantes[code] > 0
                                ? `${manquantes[code]} ${t('trad.a_traduire', 'à traduire')}`
                                : t('trad.complet', 'Complet')}
                        </p>
                    </div>
                ))}
            </div>

            <form onSubmit={chercher} className="mb-4 flex flex-wrap gap-2">
                <input
                    value={terme}
                    onChange={(e) => setTerme(e.target.value)}
                    placeholder={t('trad.chercher', 'Chercher une clé ou un texte…')}
                    className="min-w-[16rem] flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                />
                <button type="submit" className="rounded-lg bg-marine px-4 py-2 text-sm font-semibold text-white transition hover:bg-marine-deep">
                    {t('action.rechercher', 'Rechercher')}
                </button>
            </form>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <Link
                    href={route('translations.index', { recherche })}
                    preserveScroll
                    className={`rounded-full px-3 py-1 text-sm font-medium transition ${
                        ! groupe ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50'
                    }`}
                >
                    {t('parc.tous', 'Tous')}
                </Link>
                {groupes.map((g) => (
                    <Link
                        key={g.groupe}
                        href={route('translations.index', { groupe: g.groupe, recherche })}
                        preserveScroll
                        className={`rounded-full px-3 py-1 text-sm font-medium transition ${
                            groupe === g.groupe ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50'
                        }`}
                    >
                        {g.groupe}
                        <span className="ml-1.5 text-xs opacity-70">{g.total}</span>
                    </Link>
                ))}
            </div>

            <div className="space-y-2">
                {traductions.data.length === 0 && (
                    <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-600">{t('trad.aucune', 'Aucune traduction.')}</p>
                )}
                {traductions.data.map((t) => (
                    <Ligne key={t.id} traduction={t} langues={langues} />
                ))}
            </div>

            {traductions.links.length > 3 && (
                <div className="mt-5 flex flex-wrap gap-1">
                    {traductions.links.map((lien, i) => (
                        <Link
                            key={i}
                            href={lien.url ?? '#'}
                            preserveScroll
                            className={`rounded-lg px-3 py-1.5 text-sm ${
                                lien.active ? 'bg-marine text-white' : lien.url ? 'bg-white text-marine hover:bg-slate-50' : 'text-slate-400'
                            }`}
                            dangerouslySetInnerHTML={{ __html: lien.label }}
                        />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
