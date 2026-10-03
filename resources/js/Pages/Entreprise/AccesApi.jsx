import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTraduction } from '@/traduire';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const STATUTS = {
    PENDING: ['acces_api.en_attente', 'En cours d\'examen', 'bg-action/15 text-marine'],
    GRANTED: ['acces_api.accordee', 'Accordée', 'bg-status-delivered/10 text-status-delivered'],
    REFUSED: ['acces_api.refusee', 'Refusée', 'bg-status-incident/10 text-status-incident'],
};

export default function AccesApi({ demandes = [], cles = [], permissions = {} }) {
    const t = useTraduction();
    const flash = usePage().props.flash ?? {};
    const cleEnClair = flash.cle_en_clair ?? null;
    const [copie, setCopie] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ permissions: ['lecture'], ips: '', message: '' });
    const champ = 'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine';
    const enAttente = demandes.some((d) => d.statut === 'PENDING');

    const demander = (e) => {
        e.preventDefault();
        post(route('company.api.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    const basculer = (cle) => setData('permissions', data.permissions.includes(cle)
        ? data.permissions.filter((p) => p !== cle)
        : [...data.permissions, cle]);

    const afficher = (d) => {
        if (! window.confirm(t('acces_api.confirmer_affichage', 'La clé ne s\'affichera qu\'une seule fois. Préparez-vous à la copier. Continuer ?'))) return;
        router.post(route('company.api.reveal', d.id), {}, { preserveScroll: true });
    };

    const libellePermission = (p) => t('api_permission.' + p, permissions[p] ?? p);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-marine">{t('acces_api.titre', 'Accès à l\'API')}</h1>
                        <p className="text-sm text-slate-600">
                            {t('acces_api.sous_titre', 'Reliez votre logiciel (ERP, WMS, boutique en ligne) à NBLogiTrack pour déposer et suivre vos expéditions.')}
                        </p>
                    </div>
                    <a
                        href="/api/docs"
                        target="_blank"
                        rel="noopener"
                        className="rounded-lg border border-marine px-4 py-2 text-sm font-bold text-marine transition hover:bg-marine hover:text-white"
                    >
                        {t('api.documentation', 'Documentation de l\'API')}
                    </a>
                </div>
            }
        >
            <Head title={t('acces_api.titre', 'Accès à l\'API')} />

            {cleEnClair && (
                <div className="mb-4 rounded-2xl border-2 border-action bg-action/5 p-5">
                    <p className="font-bold text-marine">{t('acces_api.votre_cle', 'Votre clé d\'accès')}</p>
                    <p className="mt-1 text-sm text-slate-700">
                        {t('api.copiez', 'Copiez-la maintenant : elle ne sera plus jamais affichée. Seule son empreinte est conservée.')}
                    </p>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <code className="flex-1 overflow-x-auto rounded-lg bg-marine px-4 py-3 font-mono text-sm text-white">{cleEnClair.valeur}</code>
                        <button
                            type="button"
                            onClick={() => { navigator.clipboard?.writeText(cleEnClair.valeur); setCopie(true); }}
                            className="rounded-lg bg-action px-4 py-3 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                        >
                            {copie ? t('api.copiee', 'Copiée') : t('api.copier', 'Copier')}
                        </button>
                    </div>
                    <p className="mt-3 text-xs text-slate-600">
                        {t('acces_api.usage', 'À envoyer dans l\'en-tête Authorization: Bearer de chaque appel. Ne la partagez pas par e-mail.')}
                    </p>
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <h2 className="border-b border-slate-100 px-5 py-4 font-semibold text-marine">{t('acces_api.mes_demandes', 'Mes demandes')}</h2>
                        <ul className="divide-y divide-slate-100">
                            {demandes.length === 0 && (
                                <li className="p-5 text-sm text-slate-600">{t('acces_api.aucune_demande', 'Aucune demande pour le moment.')}</li>
                            )}
                            {demandes.map((d) => {
                                const [cle, defaut, couleur] = STATUTS[d.statut] ?? STATUTS.PENDING;
                                return (
                                    <li key={d.id} className="flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0 text-sm">
                                            <p className="font-semibold text-marine">
                                                {t('acces_api.demandee_le', 'Demande du :date', { date: d.demandee_le })}
                                                {d.demandeur && <span className="font-normal text-slate-600"> · {d.demandeur}</span>}
                                            </p>
                                            <p className="mt-1 text-slate-700">
                                                {d.permissions.map(libellePermission).join(', ')}
                                                {d.ips.length > 0 && <span className="font-mono text-xs text-slate-600"> · {d.ips.join(', ')}</span>}
                                            </p>
                                            {d.message && <p className="mt-1 text-slate-600">« {d.message} »</p>}
                                            {d.motif_refus && (
                                                <p className="mt-1 text-status-incident">{t('acces_api.motif', 'Motif : :motif', { motif: d.motif_refus })}</p>
                                            )}
                                            {d.affichee_le && (
                                                <p className="mt-1 text-xs text-slate-600">{t('acces_api.affichee_le', 'Clé affichée le :date', { date: d.affichee_le })}</p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${couleur}`}>{t(cle, defaut)}</span>
                                            {d.cle_a_afficher && (
                                                <button
                                                    type="button"
                                                    onClick={() => afficher(d)}
                                                    className="rounded-lg bg-action px-4 py-2 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                                                >
                                                    {t('acces_api.afficher_cle', 'Afficher ma clé')}
                                                </button>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <h2 className="border-b border-slate-100 px-5 py-4 font-semibold text-marine">{t('acces_api.mes_cles', 'Mes clés')}</h2>
                        <ul className="divide-y divide-slate-100">
                            {cles.length === 0 && (
                                <li className="p-5 text-sm text-slate-600">{t('acces_api.aucune_cle', 'Aucune clé pour le moment.')}</li>
                            )}
                            {cles.map((c) => (
                                <li key={c.id} className="flex flex-wrap items-center justify-between gap-3 p-5 text-sm">
                                    <div>
                                        <p className="font-semibold text-marine">{c.nom}</p>
                                        <p className="font-mono text-xs text-brand-blue">{c.prefixe}…</p>
                                        <p className="mt-1 text-xs text-slate-600">
                                            {c.permissions.map(libellePermission).join(', ')}
                                            {' · '}
                                            {c.dernier_usage
                                                ? t('acces_api.dernier_usage', ':n appels, dernier le :date', { n: c.appels, date: c.dernier_usage })
                                                : t('api.jamais', 'jamais utilisée')}
                                        </p>
                                    </div>
                                    {c.active ? (
                                        <span className="rounded-full bg-status-delivered/10 px-3 py-1 text-xs font-semibold text-status-delivered">
                                            {c.expire_le ? t('api.jusquau', 'jusqu\'au :date', { date: c.expire_le }) : t('api.active', 'Active')}
                                        </span>
                                    ) : (
                                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                            {c.revoquee_le
                                                ? t('api.revoquee_le', 'Révoquée le :date', { date: c.revoquee_le })
                                                : t('api.expiree_le', 'Expirée le :date', { date: c.expire_le })}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                        <p className="border-t border-slate-100 px-5 py-3 text-xs text-slate-600">
                            {t('acces_api.perte', 'Clé perdue ou divulguée ? Faites une nouvelle demande et signalez-le : l\'ancienne clé sera révoquée.')}
                        </p>
                    </section>
                </div>

                <section className="h-fit rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="text-sm font-bold text-marine">{t('acces_api.demander', 'Demander un accès')}</h2>
                    {enAttente ? (
                        <p className="mt-2 text-sm text-slate-600">
                            {t('acces_api.attente_aide', 'Votre demande est en cours d\'examen. Vous recevrez un e-mail dès qu\'elle sera traitée.')}
                        </p>
                    ) : (
                        <form onSubmit={demander} className="mt-3 space-y-4">
                            <fieldset>
                                <legend className="text-sm font-medium text-marine">{t('api.permissions', 'Permissions')}</legend>
                                {Object.entries(permissions).map(([cle, libelle]) => (
                                    <label key={cle} className="mt-2 flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.permissions.includes(cle)}
                                            onChange={() => basculer(cle)}
                                            className="mt-0.5 rounded border-gray-300 text-marine focus:ring-marine"
                                        />
                                        <span>
                                            <span className="font-semibold text-marine">{t('api_permission.' + cle, libelle)}</span>
                                            <span className="block text-xs text-slate-600">
                                                {cle === 'lecture'
                                                    ? t('acces_api.lecture_aide', 'Lister et suivre vos expéditions')
                                                    : t('acces_api.ecriture_aide', 'Déposer des expéditions depuis votre logiciel')}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                                {errors.permissions && <span className="mt-1 block text-xs text-status-incident">{errors.permissions}</span>}
                            </fieldset>
                            <label className="block text-sm font-medium text-marine">
                                {t('api.restriction_ip', 'Adresses autorisées')}
                                <input
                                    value={data.ips}
                                    onChange={(e) => setData('ips', e.target.value)}
                                    placeholder="203.0.113.7, 198.51.100.24"
                                    maxLength={500}
                                    className={champ + ' font-mono'}
                                />
                                <span className="mt-1 block text-xs font-normal text-slate-600">
                                    {t('acces_api.ip_aide', 'Facultatif, recommandé : les adresses de vos serveurs. La clé ne fonctionnera que depuis celles-ci.')}
                                </span>
                                {errors.ips && <span className="mt-1 block text-xs text-status-incident">{errors.ips}</span>}
                            </label>
                            <label className="block text-sm font-medium text-marine">
                                {t('acces_api.message', 'Message')}
                                <textarea
                                    value={data.message}
                                    onChange={(e) => setData('message', e.target.value)}
                                    rows={3}
                                    maxLength={500}
                                    placeholder={t('acces_api.message_ex', 'Ex : intégration de notre ERP Odoo')}
                                    className={champ}
                                />
                                {errors.message && <span className="mt-1 block text-xs text-status-incident">{errors.message}</span>}
                            </label>
                            <button type="submit" disabled={processing} className="w-full rounded-lg bg-action px-4 py-2 text-sm font-bold text-marine-deep hover:bg-action-dark disabled:opacity-60">
                                {processing ? t('action.enregistrement', 'Enregistrement…') : t('acces_api.envoyer', 'Envoyer la demande')}
                            </button>
                        </form>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
