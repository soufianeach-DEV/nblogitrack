import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function Carte({ intitule, valeur, detail, alerte = false }) {
    return (
        <div className="rounded-2xl bg-white p-5 shadow-sm">
            <p className="text-xs uppercase tracking-wide text-slate-600">{intitule}</p>
            <p className={`text-2xl font-bold ${alerte ? 'text-status-incident' : 'text-marine'}`}>{valeur}</p>
            {detail && <p className="mt-0.5 text-xs text-slate-600">{detail}</p>}
        </div>
    );
}

export default function Index({ cles, journal, filtres, permissions, entreprises, statistiques }) {
    const t = useTraduction();
    const locale = useLocale();
    const flash = usePage().props.flash ?? {};
    const [creation, setCreation] = useState(false);
    const [aRevoquer, setARevoquer] = useState(null);
    const [copie, setCopie] = useState(false);

    const nouvelleCle = flash.cle_en_clair ?? null;

    const { data, setData, post, processing, errors, reset } = useForm({
        nom: '',
        client_id: '',
        permissions: ['lecture'],
        ips: '',
        expire_le: '',
    });

    const soumettre = (e) => {
        e.preventDefault();
        post(route('api-keys.store'), {
            preserveScroll: true,
            onSuccess: () => { setCreation(false); reset(); },
        });
    };

    const basculerPermission = (cle) => {
        setData('permissions', data.permissions.includes(cle)
            ? data.permissions.filter((p) => p !== cle)
            : [...data.permissions, cle]);
    };

    const filtrer = (champ, valeur) => {
        router.get(route('api-keys.index'), { ...filtres, [champ]: valeur || undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const codeStatut = (statut) => statut < 300
        ? 'bg-status-delivered/10 text-status-delivered'
        : statut < 500 ? 'bg-status-incident/10 text-status-incident' : 'bg-slate-200 text-slate-700';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-marine">{t('api.titre', 'API REST')}</h1>
                        <p className="text-sm text-slate-600">
                            {t('api.sous_titre', 'Les clés d\'accès des partenaires et leur activité.')}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setCreation(true)}
                        className="rounded-lg bg-marine px-4 py-2 text-sm font-bold text-white transition hover:bg-marine-deep"
                    >
                        {t('api.nouvelle', 'Générer une clé')}
                    </button>
                </div>
            }
        >
            <Head title={t('api.titre', 'API REST')} />

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

            {/* La valeur en clair n'existe qu'ici, une fois. La reafficher
                supposerait de l'avoir enregistree, ce qu'on refuse. */}
            {nouvelleCle && (
                <div className="mb-4 rounded-2xl border-2 border-action bg-action/5 p-5">
                    <p className="font-bold text-marine">
                        {t('api.creee', 'Clé « :nom » créée', { nom: nouvelleCle.nom })}
                    </p>
                    <p className="mt-1 text-sm text-slate-700">
                        {t('api.copiez', 'Copiez-la maintenant : elle ne sera plus jamais affichée. Seule son empreinte est conservée.')}
                    </p>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <code className="flex-1 overflow-x-auto rounded-lg bg-marine px-4 py-3 font-mono text-sm text-white">
                            {nouvelleCle.valeur}
                        </code>
                        <button
                            type="button"
                            onClick={() => {
                                navigator.clipboard?.writeText(nouvelleCle.valeur);
                                setCopie(true);
                            }}
                            className="rounded-lg bg-action px-4 py-3 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                        >
                            {copie ? t('api.copiee', 'Copiée') : t('api.copier', 'Copier')}
                        </button>
                    </div>
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Carte
                    intitule={t('api.cles_actives', 'Clés actives')}
                    valeur={statistiques.cles_actives}
                    detail={t('api.sur_total', 'sur :total au total', { total: statistiques.cles_total })}
                />
                <Carte
                    intitule={t('api.appels_24h', 'Appels sur 24 h')}
                    valeur={statistiques.appels_24h.toLocaleString(locale)}
                    detail={t('api.sur_7j', ':n sur 7 jours', { n: statistiques.appels_7j.toLocaleString(locale) })}
                />
                <Carte
                    intitule={t('api.refuses_24h', 'Refusés sur 24 h')}
                    valeur={statistiques.refus_24h.toLocaleString(locale)}
                    detail={t('api.sur_7j', ':n sur 7 jours', { n: statistiques.refus_7j.toLocaleString(locale) })}
                    alerte={statistiques.refus_24h > 0}
                />
                <Carte
                    intitule={t('api.duree_moyenne', 'Temps de réponse moyen')}
                    valeur={`${statistiques.duree_moyenne} ms`}
                    detail={t('api.appels_servis', 'appels servis, 7 jours')}
                />
            </div>

            {statistiques.motifs.length > 0 && (
                <div className="mt-4 rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        {t('api.motifs_refus', 'Motifs de refus, 7 derniers jours')}
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        {statistiques.motifs.map((m) => (
                            <span key={m.motif} className="rounded-full bg-status-incident/10 px-3 py-1 text-xs font-medium text-status-incident">
                                {t('api_motif.' + m.motif, m.libelle)} · {m.total}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            <div className="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
                <h2 className="border-b border-slate-100 px-5 py-4 font-semibold text-marine">
                    {t('api.les_cles', 'Les clés')}
                </h2>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.nom', 'Nom')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.prefixe', 'Préfixe')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.entreprise', 'Entreprise')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.permissions', 'Permissions')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.restriction_ip', 'Adresses autorisées')}</th>
                                <th scope="col" className="px-4 py-3 text-right font-semibold">{t('api.appels', 'Appels')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.etat', 'État')}</th>
                                <th scope="col" className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {cles.map((c) => (
                                <tr key={c.id} className="border-b border-slate-50 last:border-0">
                                    <td className="px-4 py-3 font-semibold text-marine">
                                        {c.nom}
                                        <span className="block text-xs font-normal text-slate-600">
                                            {t('api.creee_par', 'par :qui le :date', { qui: c.creee_par, date: c.creee_le })}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-brand-blue">{c.prefixe}</td>
                                    <td className="px-4 py-3 text-slate-700">
                                        {c.entreprise ?? <span className="text-slate-500">{t('api.interne', 'Interne — accès complet')}</span>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="flex flex-wrap gap-1">
                                            {c.permissions.map((p) => (
                                                <span key={p} className="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                                    {t('api_permission.' + p, permissions[p] ?? p)}
                                                </span>
                                            ))}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-600">
                                        {c.ips.length > 0
                                            ? c.ips.join(', ')
                                            : <span className="font-sans text-slate-500">{t('api.sans_restriction', 'Aucune')}</span>}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        <span className="font-bold text-marine">{c.appels.toLocaleString(locale)}</span>
                                        <span className="block text-xs text-slate-600">
                                            {c.dernier_usage ?? t('api.jamais', 'jamais utilisée')}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        {c.active ? (
                                            <span className="rounded-full bg-status-delivered/10 px-3 py-1 text-xs font-semibold text-status-delivered">
                                                {t('api.active', 'Active')}
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                                {c.revoquee_le
                                                    ? t('api.revoquee_le', 'Révoquée le :date', { date: c.revoquee_le })
                                                    : t('api.expiree_le', 'Expirée le :date', { date: c.expire_le })}
                                            </span>
                                        )}
                                        {c.active && c.expire_le && (
                                            <span className="block text-xs text-slate-600">
                                                {t('api.jusquau', 'jusqu\'au :date', { date: c.expire_le })}
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        {c.active && (
                                            <button
                                                type="button"
                                                onClick={() => setARevoquer(c)}
                                                className="text-xs font-semibold text-status-incident hover:underline"
                                            >
                                                {t('api.revoquer', 'Révoquer')}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {cles.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan={8}>
                                        {t('api.aucune_cle', 'Aucune clé n\'a encore été générée.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <h2 className="font-semibold text-marine">{t('api.journal', 'Journal d\'accès')}</h2>
                    <div className="flex flex-wrap gap-2">
                        <select
                            value={filtres.cle ?? ''}
                            onChange={(e) => filtrer('cle', e.target.value)}
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        >
                            <option value="">{t('api.toutes_cles', 'Toutes les clés')}</option>
                            {cles.map((c) => <option key={c.id} value={c.id}>{c.nom}</option>)}
                        </select>
                        <select
                            value={filtres.etat ?? ''}
                            onChange={(e) => filtrer('etat', e.target.value)}
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        >
                            <option value="">{t('api.tout', 'Tout')}</option>
                            <option value="servis">{t('api.servis', 'Servis')}</option>
                            <option value="refuses">{t('api.refuses', 'Refusés')}</option>
                        </select>
                    </div>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="px-4 py-3 font-semibold">{t('journal.date', 'Date')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.cle', 'Clé')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.appel', 'Appel')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.code', 'Code')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('journal.ip', 'Adresse IP')}</th>
                                <th scope="col" className="px-4 py-3 text-right font-semibold">{t('api.duree', 'Durée')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {journal.data.map((l) => (
                                <tr key={l.id} className="border-b border-slate-50 last:border-0">
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{l.horodatage}</td>
                                    <td className="px-4 py-3">
                                        {l.cle
                                            ? <><span className="text-marine">{l.cle}</span>
                                                <span className="block font-mono text-xs text-slate-500">{l.prefixe}</span></>
                                            : <span className="text-slate-500">—</span>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="font-mono text-xs font-bold text-slate-700">{l.methode}</span>
                                        <span className="ml-2 font-mono text-xs text-slate-600">/{l.chemin}</span>
                                        {l.refus && (
                                            <span className="block text-xs font-medium text-status-incident">
                                                {t('api_motif.' + l.refus, l.refus_libelle)}
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className={`rounded px-2 py-0.5 font-mono text-xs font-bold ${codeStatut(l.statut)}`}>
                                            {l.statut}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600">{l.ip ?? '—'}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">
                                        {l.duree !== null ? `${l.duree} ms` : '—'}
                                    </td>
                                </tr>
                            ))}
                            {journal.data.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan={6}>
                                        {t('api.journal_vide', 'Aucun appel enregistré.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {journal.last_page > 1 && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {journal.links.map((lien, i) => (
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

            <Modal show={creation} onClose={() => setCreation(false)} maxWidth="lg">
                <form onSubmit={soumettre} className="p-6">
                    <h2 className="text-lg font-bold text-marine">{t('api.nouvelle', 'Générer une clé')}</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {t('api.nouvelle_aide', 'La valeur ne s\'affichera qu\'une seule fois, juste après la création.')}
                    </p>

                    <div className="mt-4 space-y-4">
                        <div>
                            <label htmlFor="nom" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.nom', 'Nom')}
                            </label>
                            <input
                                id="nom"
                                value={data.nom}
                                onChange={(e) => setData('nom', e.target.value)}
                                placeholder={t('api.nom_ex', 'Ex : portail logistique Peeters')}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            {errors.nom && <p className="mt-1 text-sm text-status-incident">{errors.nom}</p>}
                        </div>

                        <div>
                            <label htmlFor="entreprise" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.entreprise', 'Entreprise')}
                            </label>
                            <select
                                id="entreprise"
                                value={data.client_id}
                                onChange={(e) => setData('client_id', e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            >
                                <option value="">{t('api.interne', 'Interne — accès complet')}</option>
                                {entreprises.map((e) => <option key={e.valeur} value={e.valeur}>{e.libelle}</option>)}
                            </select>
                            <p className="mt-1 text-xs text-slate-600">
                                {t('api.entreprise_aide', 'Une clé rattachée ne voit que les expéditions de cette entreprise.')}
                            </p>
                        </div>

                        <div>
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.permissions', 'Permissions')}
                            </span>
                            <div className="flex flex-wrap gap-4">
                                {Object.entries(permissions).map(([cle, libelle]) => (
                                    <label key={cle} className="flex items-center gap-2 text-sm text-marine">
                                        <input
                                            type="checkbox"
                                            checked={data.permissions.includes(cle)}
                                            onChange={() => basculerPermission(cle)}
                                            className="rounded border-gray-300 text-marine focus:ring-marine"
                                        />
                                        {t('api_permission.' + cle, libelle)}
                                    </label>
                                ))}
                            </div>
                            {errors.permissions && <p className="mt-1 text-sm text-status-incident">{errors.permissions}</p>}
                        </div>

                        <div>
                            <label htmlFor="ips" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.restriction_ip', 'Adresses autorisées')}
                            </label>
                            <input
                                id="ips"
                                value={data.ips}
                                onChange={(e) => setData('ips', e.target.value)}
                                placeholder="203.0.113.7, 198.51.100.24"
                                className="w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            <p className="mt-1 text-xs text-slate-600">
                                {t('api.ip_aide', 'Séparées par des virgules. Laisser vide autorise toutes les adresses.')}
                            </p>
                            {errors.ips && <p className="mt-1 text-sm text-status-incident">{errors.ips}</p>}
                        </div>

                        <div>
                            <label htmlFor="expire" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.expiration', 'Expiration')}
                            </label>
                            <input
                                id="expire"
                                type="date"
                                value={data.expire_le}
                                onChange={(e) => setData('expire_le', e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            <p className="mt-1 text-xs text-slate-600">
                                {t('api.expiration_aide', 'Facultatif. Sans date, la clé reste valable jusqu\'à sa révocation.')}
                            </p>
                            {errors.expire_le && <p className="mt-1 text-sm text-status-incident">{errors.expire_le}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setCreation(false)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button disabled={processing} className="rounded-lg bg-marine px-4 py-2 text-sm font-semibold text-white transition hover:bg-marine-deep disabled:opacity-50">
                            {t('api.generer', 'Générer')}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal show={aRevoquer !== null} onClose={() => setARevoquer(null)} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-bold text-marine">{t('api.revoquer', 'Révoquer')}</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        {t('api.revoquer_aide', 'La clé « :nom » cessera immédiatement de fonctionner. Cette opération ne s\'annule pas : il faudra en générer une autre.', { nom: aRevoquer?.nom ?? '' })}
                    </p>
                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setARevoquer(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                router.patch(route('api-keys.revoke', aRevoquer.id), {}, {
                                    preserveScroll: true,
                                    onFinish: () => setARevoquer(null),
                                });
                            }}
                            className="rounded-lg bg-status-incident px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                        >
                            {t('api.revoquer', 'Révoquer')}
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
