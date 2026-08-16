import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTraduction } from '@/traduire';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const VIDE = {
    slug: '', titre_fr: '', titre_nl: '', titre_en: '',
    corps_fr: '', corps_nl: '', corps_en: '', au_pied: false, rang: 0,
};

export default function Index({ pages, documents, types }) {
    const t = useTraduction();
    const flash = usePage().props.flash ?? {};
    const [edition, setEdition] = useState(null);
    const [langue, setLangue] = useState('fr');
    const [aSupprimer, setASupprimer] = useState(null);

    const { data, setData, post, patch, processing, errors, reset } = useForm(VIDE);
    const fichier = useForm({ titre: '', fichier: null });

    const ouvrir = (page) => {
        reset();
        setLangue('fr');
        setData(page ? { ...VIDE, ...page } : VIDE);
        setEdition(page ?? 'nouvelle');
    };

    const enregistrer = (e) => {
        e.preventDefault();
        const fin = { preserveScroll: true, onSuccess: () => setEdition(null) };

        if (edition === 'nouvelle') {
            post(route('pages.store'), fin);
        } else {
            patch(route('pages.update', edition.id), fin);
        }
    };

    const deposer = (e) => {
        e.preventDefault();
        fichier.post(route('pages.documents.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => fichier.reset(),
        });
    };

    const champ = (suffixe) => `${suffixe}_${langue}`;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-marine">{t('nav.pages', 'Pages du site')}</h1>
                        <p className="text-sm text-slate-600">
                            {t('pages.sous_titre', 'Le contenu public, modifiable sans livraison de code.')}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => ouvrir(null)}
                        className="rounded-lg bg-marine px-4 py-2 text-sm font-bold text-white transition hover:bg-marine-deep"
                    >
                        {t('pages.nouvelle', 'Nouvelle page')}
                    </button>
                </div>
            }
        >
            <Head title={t('nav.pages', 'Pages du site')} />

            {flash.success && (
                <div className="mb-4 rounded-lg bg-status-delivered/10 px-4 py-3 text-sm font-medium text-status-delivered">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-lg bg-status-incident/10 px-4 py-3 text-sm font-medium text-status-incident">
                    {flash.error}
                </div>
            )}

            <div className="space-y-3">
                {pages.length === 0 && (
                    <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-600">
                        {t('pages.aucune', 'Aucune page n\'a encore été rédigée.')}
                    </p>
                )}

                {pages.map((p) => (
                    <article key={p.id} className="rounded-2xl bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-lg font-bold text-marine">{p.titre_fr}</h2>
                                    <span className={
                                        'rounded-full px-3 py-0.5 text-xs font-semibold ' +
                                        (p.publiee
                                            ? 'bg-status-delivered/10 text-status-delivered'
                                            : 'bg-slate-100 text-slate-600')
                                    }>
                                        {p.publiee
                                            ? t('pages.publiee', 'Publiée')
                                            : t('pages.brouillon', 'Brouillon')}
                                    </span>
                                    {p.au_pied && (
                                        <span className="rounded-full bg-brand-blue/10 px-3 py-0.5 text-xs font-medium text-brand-blue">
                                            {t('pages.au_pied', 'Au pied de page')}
                                        </span>
                                    )}
                                </div>
                                <p className="mt-1 font-mono text-xs text-brand-blue">/{p.slug}</p>
                                <p className="mt-2 text-xs text-slate-600">
                                    {/* Ce qui manque se dit, sinon une page part en
                                        ligne avec deux langues sur trois sans que
                                        personne ne s'en apercoive. */}
                                    {['nl', 'en'].filter((l) => ! p.traduite[l]).length === 0
                                        ? t('pages.trois_langues', 'Rédigée dans les trois langues')
                                        : t('pages.manque', 'À traduire : :langues', {
                                            langues: ['nl', 'en'].filter((l) => ! p.traduite[l]).join(', ').toUpperCase(),
                                        })}
                                    {p.modifiee_le && ' · ' + t('pages.modifiee', 'modifiée le :date par :qui', {
                                        date: p.modifiee_le, qui: p.modifiee_par || '—',
                                    })}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {p.publiee && (
                                    <a
                                        href={route('pages.show', p.slug)}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-marine transition hover:bg-surface"
                                    >
                                        {t('pages.voir', 'Voir')}
                                    </a>
                                )}
                                <button
                                    type="button"
                                    onClick={() => ouvrir(p)}
                                    className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-marine transition hover:bg-surface"
                                >
                                    {t('action.modifier', 'Modifier')}
                                </button>
                                {/* La note aux conducteurs se remet aussi par
                                    courriel. L'accusé, lui, se donne dans
                                    l'application : un envoi prouve la remise,
                                    pas la lecture. */}
                                {p.slug === 'information-chauffeurs' && (
                                    <button
                                        type="button"
                                        onClick={() => router.post(route('pages.notice.send', p.id), {}, { preserveScroll: true })}
                                        className="rounded-lg border border-brand-blue px-3 py-2 text-sm font-semibold text-brand-blue transition hover:bg-brand-blue/5"
                                        title={t('pages.envoyer_aide', 'Chaque conducteur la reçoit dans sa langue. L\'accusé de prise de connaissance reste demandé dans l\'application.')}
                                    >
                                        {t('pages.envoyer', 'Envoyer aux conducteurs')}
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => router.patch(route('pages.publish', p.id), {}, { preserveScroll: true })}
                                    className={
                                        'rounded-lg px-3 py-2 text-sm font-semibold text-white transition ' +
                                        (p.publiee ? 'bg-slate-500 hover:bg-slate-600' : 'bg-status-delivered hover:opacity-90')
                                    }
                                >
                                    {p.publiee ? t('pages.retirer', 'Retirer') : t('pages.publier', 'Publier')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setASupprimer(p)}
                                    className="rounded-lg px-3 py-2 text-sm font-semibold text-status-incident transition hover:bg-status-incident/5"
                                >
                                    {t('action.supprimer', 'Supprimer')}
                                </button>
                            </div>
                        </div>
                    </article>
                ))}
            </div>

            <section className="mt-8 rounded-2xl bg-white p-5 shadow-sm">
                <h2 className="font-semibold text-marine">{t('pages.documents', 'Documents mis à disposition')}</h2>
                <p className="mt-1 text-sm text-slate-600">
                    {t('pages.documents_aide', 'Fichiers téléchargeables par les visiteurs et les entreprises. Types acceptés : :types, 10 Mo maximum.', {
                        types: types.join(', '),
                    })}
                </p>

                <form onSubmit={deposer} className="mt-4 flex flex-wrap items-end gap-3 border-b border-slate-100 pb-5">
                    <div className="min-w-[14rem] flex-1">
                        <label htmlFor="doc-titre" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            {t('pages.doc_titre', 'Intitulé')}
                        </label>
                        <input
                            id="doc-titre"
                            value={fichier.data.titre}
                            onChange={(e) => fichier.setData('titre', e.target.value)}
                            placeholder={t('pages.doc_titre_ex', 'Ex : grille tarifaire 2026')}
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                        {fichier.errors.titre && <p className="mt-1 text-sm text-status-incident">{fichier.errors.titre}</p>}
                    </div>
                    <div className="min-w-[14rem] flex-1">
                        <label htmlFor="doc-fichier" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            {t('pages.doc_fichier', 'Fichier')}
                        </label>
                        <input
                            id="doc-fichier"
                            type="file"
                            onChange={(e) => fichier.setData('fichier', e.target.files[0])}
                            className="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-marine file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white"
                        />
                        {fichier.errors.fichier && <p className="mt-1 text-sm text-status-incident">{fichier.errors.fichier}</p>}
                    </div>
                    <button
                        disabled={fichier.processing}
                        className="rounded-lg bg-marine px-4 py-2 text-sm font-semibold text-white transition hover:bg-marine-deep disabled:opacity-50"
                    >
                        {t('pages.deposer', 'Déposer')}
                    </button>
                </form>

                <ul className="mt-4 divide-y divide-slate-100">
                    {documents.length === 0 && (
                        <li className="py-6 text-center text-sm text-slate-600">
                            {t('pages.aucun_document', 'Aucun document déposé.')}
                        </li>
                    )}
                    {documents.map((d) => (
                        <li key={d.id} className="flex flex-wrap items-center gap-3 py-3">
                            <span className="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{d.type}</span>
                            <div className="min-w-0 flex-1">
                                <a href={d.url} target="_blank" rel="noreferrer" className="font-semibold text-brand-blue hover:underline">
                                    {d.titre}
                                </a>
                                <p className="truncate text-xs text-slate-600">
                                    {d.nom} · {d.taille} · {t('pages.depose', 'déposé le :date par :qui', { date: d.depose_le, qui: d.depose_par })}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => router.delete(route('pages.documents.destroy', d.id), { preserveScroll: true })}
                                className="text-xs font-semibold text-status-incident hover:underline"
                            >
                                {t('action.supprimer', 'Supprimer')}
                            </button>
                        </li>
                    ))}
                </ul>
            </section>

            <Modal show={edition !== null} onClose={() => setEdition(null)} maxWidth="2xl">
                <form onSubmit={enregistrer} className="p-6">
                    <h2 className="text-lg font-bold text-marine">
                        {edition === 'nouvelle' ? t('pages.nouvelle', 'Nouvelle page') : t('action.modifier', 'Modifier')}
                    </h2>

                    <div className="mt-4 grid gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <label htmlFor="slug" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('pages.adresse', 'Adresse')}
                            </label>
                            <div className="flex items-center gap-1">
                                <span className="font-mono text-sm text-slate-500">/p/</span>
                                <input
                                    id="slug"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    placeholder="conditions-generales"
                                    className="w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-marine focus:ring-marine"
                                />
                            </div>
                            {errors.slug && <p className="mt-1 text-sm text-status-incident">{errors.slug}</p>}
                        </div>
                        <div>
                            <label htmlFor="rang" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('pages.rang', 'Ordre')}
                            </label>
                            <input
                                id="rang"
                                type="number"
                                min="0"
                                value={data.rang}
                                onChange={(e) => setData('rang', e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                        </div>
                    </div>

                    <label className="mt-4 flex items-center gap-2 text-sm text-marine">
                        <input
                            type="checkbox"
                            checked={data.au_pied}
                            onChange={(e) => setData('au_pied', e.target.checked)}
                            className="rounded border-gray-300 text-marine focus:ring-marine"
                        />
                        {t('pages.au_pied_aide', 'Afficher dans le pied de page du site')}
                    </label>

                    {/* Une langue a la fois : trois zones de texte cote a cote
                        seraient illisibles, et on n'ecrit pas trois versions
                        en meme temps. */}
                    <div className="mt-5 flex gap-1 border-b border-slate-100">
                        {['fr', 'nl', 'en'].map((l) => (
                            <button
                                key={l}
                                type="button"
                                onClick={() => setLangue(l)}
                                className={
                                    'rounded-t-lg px-4 py-2 text-sm font-semibold uppercase transition ' +
                                    (langue === l ? 'bg-marine text-white' : 'text-slate-600 hover:bg-surface')
                                }
                            >
                                {l}
                                {l !== 'fr' && ! data[`titre_${l}`] && (
                                    <span className="ml-1 text-xs opacity-70">•</span>
                                )}
                            </button>
                        ))}
                    </div>

                    <div className="mt-4 space-y-4">
                        <div>
                            <label htmlFor="titre" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('pages.titre', 'Titre')}
                                {langue !== 'fr' && (
                                    <span className="ml-2 font-normal normal-case text-slate-500">
                                        {t('pages.repli_aide', 'vide = le français sera affiché')}
                                    </span>
                                )}
                            </label>
                            <input
                                id="titre"
                                value={data[champ('titre')] ?? ''}
                                onChange={(e) => setData(champ('titre'), e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            {errors[champ('titre')] && <p className="mt-1 text-sm text-status-incident">{errors[champ('titre')]}</p>}
                        </div>

                        <div>
                            <label htmlFor="corps" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('pages.corps', 'Contenu')}
                            </label>
                            <textarea
                                id="corps"
                                rows="14"
                                value={data[champ('corps')] ?? ''}
                                onChange={(e) => setData(champ('corps'), e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            <p className="mt-1 text-xs text-slate-600">
                                {t('pages.corps_aide', 'Texte simple. Les sauts de ligne sont conservés ; le HTML n\'est pas interprété.')}
                            </p>
                            {errors[champ('corps')] && <p className="mt-1 text-sm text-status-incident">{errors[champ('corps')]}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setEdition(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button disabled={processing} className="rounded-lg bg-marine px-4 py-2 text-sm font-semibold text-white transition hover:bg-marine-deep disabled:opacity-50">
                            {t('action.enregistrer', 'Enregistrer')}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal show={aSupprimer !== null} onClose={() => setASupprimer(null)} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-bold text-marine">{t('action.supprimer', 'Supprimer')}</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        {t('pages.supprimer_aide', 'La page « :titre » et son contenu seront perdus. Pour la retirer du site sans l\'effacer, utilisez plutôt « Retirer ».', {
                            titre: aSupprimer?.titre_fr ?? '',
                        })}
                    </p>
                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setASupprimer(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                router.delete(route('pages.destroy', aSupprimer.id), {
                                    preserveScroll: true,
                                    onFinish: () => setASupprimer(null),
                                });
                            }}
                            className="rounded-lg bg-status-incident px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                        >
                            {t('action.supprimer', 'Supprimer')}
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
