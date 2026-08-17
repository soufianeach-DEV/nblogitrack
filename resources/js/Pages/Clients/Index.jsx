import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, usePays, useTraduction, useVocabulaire } from '@/traduire';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

const ONGLETS = {
    tout: ['entreprises.tout', 'Tout'],
    attente: ['statut.en_attente', 'En attente'],
    validees: ['entreprises.validees', 'Validées'],
    refusees: ['entreprises.refusees', 'Refusées'],
};

export default function Index({ clients, etat, filtres, suggestions, compteurs }) {
    const flash = usePage().props.flash ?? {};
    const t = useTraduction();
    const v = useVocabulaire();
    const p = usePays();
    const locale = useLocale();
    const [refus, setRefus] = useState(null);
    const [champs, setChamps] = useState({
        q: filtres.q ?? '',
        pays: filtres.pays ?? '',
        secteur: filtres.secteur ?? '',
    });
    const minuteur = useRef(null);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ motif: '' });

    const filtrer = (cle, valeur) => {
        const suivant = { ...champs, [cle]: valeur };
        setChamps(suivant);
        clearTimeout(minuteur.current);
        minuteur.current = setTimeout(() => {
            router.get(route('clients.index'), { etat, ...suivant }, {
                only: ['clients', 'filtres', 'compteurs'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 250);
    };

    const reinitialiser = () => {
        setChamps({ q: '', pays: '', secteur: '' });
        router.get(route('clients.index', { etat }), {}, { preserveScroll: true, replace: true });
    };

    const valider = (client) => {
        router.post(route('clients.approve', client.id), {}, { preserveScroll: true });
    };

    const ouvrirRefus = (client) => {
        clearErrors();
        reset();
        setData('motif', client.rejection_reason ?? '');
        setRefus(client);
    };

    const envoyerRefus = (e) => {
        e.preventDefault();
        post(route('clients.reject', refus.id), {
            preserveScroll: true,
            onSuccess: () => setRefus(null),
        });
    };

    const dateCourte = (valeur) => valeur
        ? new Date(valeur).toLocaleDateString(locale, { day: '2-digit', month: '2-digit', year: 'numeric' })
        : '—';

    const ligne = (libelle, valeur) => (
        <div>
            <dt className="text-xs uppercase tracking-wide text-slate-600">{libelle}</dt>
            <dd className="text-sm text-marine">{valeur || '—'}</dd>
        </div>
    );

    const champCls = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine';

    const statut = (client) => {
        if (client.is_validated) {
            return 'validee';
        }

        return client.rejection_reason ? 'refusee' : 'attente';
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">{t('entreprises.titre', 'Entreprises inscrites')}</h1>}>
            <Head title={t('entreprises.titre', 'Entreprises inscrites')} />

            {flash.success && (
                <div className="mb-4 rounded-lg bg-status-delivered/10 px-4 py-3 text-sm font-medium text-status-delivered">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap gap-2">
                {Object.entries(ONGLETS).map(([cle, etiquette]) => (
                    <Link
                        key={cle}
                        href={route('clients.index', { etat: cle, ...champs })}
                        preserveScroll
                        className={
                            'rounded-lg px-4 py-2 text-sm font-medium transition ' +
                            (etat === cle ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50')
                        }
                    >
                        {t(...etiquette)}
                        <span className="ml-2 text-xs opacity-70">{compteurs[cle] ?? 0}</span>
                    </Link>
                ))}
            </div>

            <div className="mb-4 rounded-2xl bg-white p-5 shadow-sm">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <input
                            list="liste-entreprises"
                            value={champs.q}
                            onChange={(e) => filtrer('q', e.target.value)}
                            placeholder={t('entreprises.filtre', 'Entreprise, numéro de TVA, Peppol ou localité')}
                            className={champCls}
                        />
                        <datalist id="liste-entreprises">
                            {suggestions.entreprises.map((nom) => <option key={nom} value={nom} />)}
                        </datalist>
                    </div>

                    <div>
                        <input
                            list="liste-pays"
                            value={champs.pays}
                            onChange={(e) => filtrer('pays', e.target.value)}
                            placeholder={t('auth.pays', 'Pays')}
                            className={champCls}
                        />
                        <datalist id="liste-pays">
                            {suggestions.pays.map((p) => <option key={p} value={p} />)}
                        </datalist>
                    </div>

                    <div>
                        <input
                            list="liste-secteurs"
                            value={champs.secteur}
                            onChange={(e) => filtrer('secteur', e.target.value)}
                            placeholder={t('auth.secteur', 'Secteur d\'activité')}
                            className={champCls}
                        />
                        <datalist id="liste-secteurs">
                            {suggestions.secteurs.map((s) => <option key={s} value={s} />)}
                        </datalist>
                    </div>
                </div>

                {(champs.q || champs.pays || champs.secteur) && (
                    <button type="button" onClick={reinitialiser} className="mt-3 text-xs text-brand-blue hover:underline">
                        {t('journal.reinitialiser', 'Réinitialiser les filtres')}
                    </button>
                )}
            </div>

            <div className="space-y-3">
                {clients.data.length === 0 && (
                    <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-600">
                        {t('entreprises.aucune', 'Aucune entreprise ne correspond.')}
                    </p>
                )}

                {clients.data.map((client) => {
                    const contact = client.contacts?.[0];
                    const etatClient = statut(client);

                    return (
                        <article key={client.id} className="rounded-2xl bg-white p-5 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-lg font-bold text-marine">{client.company_name}</h2>
                                        {etatClient !== 'attente' && (
                                            <span
                                                className={
                                                    'rounded-full px-3 py-0.5 text-xs font-medium ' +
                                                    (etatClient === 'validee'
                                                        ? 'bg-status-delivered/10 text-status-delivered'
                                                        : 'bg-status-incident/10 text-status-incident')
                                                }
                                            >
                                                {etatClient === 'validee'
                                                    ? t('entreprises.validee', 'Validée')
                                                    : t('entreprises.refusee', 'Refusée')} {t('entreprises.le', 'le')} {dateCourte(client.validated_at)}
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-slate-600">
                                        {}
                                        {client.billing_address} · {client.postal_code} {client.city} · {p(client.country)}
                                    </p>
                                </div>

                                {etatClient !== 'validee' && (
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() => ouvrirRefus(client)}
                                            className="rounded-lg border border-status-incident px-4 py-2 text-sm font-semibold text-status-incident transition hover:bg-status-incident/5"
                                        >
                                            {etatClient === 'refusee'
                                                ? t('entreprises.modifier_motif', 'Modifier le motif')
                                                : t('entreprises.refuser', 'Refuser')}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => valider(client)}
                                            className="rounded-lg bg-status-delivered px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                        >
                                            {etatClient === 'refusee'
                                                ? t('entreprises.revalider', 'Revalider')
                                                : t('entreprises.valider', 'Valider')}
                                        </button>
                                    </div>
                                )}
                            </div>

                            <dl className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-3">
                                {ligne(t('compte.numero_tva', 'Numéro de TVA'), client.vat_number)}
                                {ligne(t('ent.peppol', 'Identifiant Peppol'), client.peppol_id)}
                                {ligne(t('auth.secteur', 'Secteur d\'activité'), v('secteur', client.business_sector))}
                                {ligne(t('nav.contact', 'Contact'), contact
                                    ? `${contact.first_name} ${contact.last_name}${contact.position ? ' — ' + v('fonction', contact.position) : ''}`
                                    : null)}
                                {ligne(t('devis.email', 'Adresse e-mail'), client.user?.email)}
                                {ligne(t('auth.telephone', 'Téléphone'), contact?.phone)}
                            </dl>

                            {client.rejection_reason && (
                                <p className="mt-3 rounded-lg bg-status-incident/5 px-3 py-2 text-xs text-status-incident">
                                    {t('entreprises.motif_refus', 'Motif du refus :')} {client.rejection_reason}
                                    {client.validator && ` — ${client.validator.first_name} ${client.validator.last_name}`}
                                </p>
                            )}
                        </article>
                    );
                })}
            </div>

            {clients.last_page > 1 && (
                <div className="mt-6 flex flex-wrap gap-1">
                    {clients.links.map((lien, i) => (
                        <Link
                            key={i}
                            href={lien.url ?? '#'}
                            preserveScroll
                            className={
                                'rounded-lg px-3 py-2 text-sm ' +
                                (lien.active ? 'bg-marine text-white' : lien.url ? 'bg-white text-marine hover:bg-slate-50' : 'bg-white text-slate-300')
                            }
                            dangerouslySetInnerHTML={{ __html: lien.label }}
                        />
                    ))}
                </div>
            )}

            <Modal show={refus !== null} onClose={() => setRefus(null)} maxWidth="lg">
                <form onSubmit={envoyerRefus} className="p-6">
                    <h2 className="text-lg font-bold text-marine">{t('entreprises.refuser', 'Refuser')} {refus?.company_name}</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {t('entreprises.motif_envoye', 'Le motif est envoyé par e-mail au contact et conservé dans le journal.')}
                    </p>

                    <textarea
                        value={data.motif}
                        onChange={(e) => setData('motif', e.target.value)}
                        rows="3"
                        placeholder={t('entreprises.motif_ex', 'ex. Le numéro de TVA ne correspond pas à l\'adresse du siège.')}
                        className="mt-4 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                    />
                    {errors.motif && <p className="mt-1 text-sm text-status-incident">{errors.motif}</p>}

                    <div className="mt-4 flex justify-end gap-2">
                        <button type="button" onClick={() => setRefus(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button disabled={processing} className="rounded-lg bg-status-incident px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50">
                            {t('entreprises.confirmer_refus', 'Confirmer le refus')}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
