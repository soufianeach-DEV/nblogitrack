import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTraduction, useVocabulaire } from '@/traduire';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

const STATUS = {
    PENDING: { cle: 'statut.en_attente', label: 'En attente', cls: 'bg-status-pending/10 text-status-pending' },
    IN_PROGRESS: { cle: 'statut.en_cours', label: 'En cours', cls: 'bg-status-progress/10 text-status-progress' },
    DELIVERED: { cle: 'statut.livre', label: 'Livré', cls: 'bg-status-delivered/10 text-status-delivered' },
    CANCELLED: { cle: 'statut.annule', label: 'Annulé', cls: 'bg-status-incident/10 text-status-incident' },
};

const ATTENTE_PAIEMENT = {
    cle: 'statut.attente_paiement',
    label: 'En attente de paiement',
    cls: 'bg-status-pending/10 text-status-pending',
};

function StatusBadge({ status, enAttenteDePaiement = false }) {
    const t = useTraduction();
    const s = enAttenteDePaiement ? ATTENTE_PAIEMENT : STATUS[status];

    return (
        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${s?.cls ?? 'bg-slate-100 text-slate-600'}`}>
            {s ? t(s.cle, s.label) : status}
        </span>
    );
}

export default function Index({ orders, filters }) {
    const t = useTraduction();
    const v = useVocabulaire();
    const estClient = usePage().props.auth.user.role === 'CLIENT';
    const [search, setSearch] = useState({
        tracking: filters.tracking ?? '',
        client: filters.client ?? '',
        destination: filters.destination ?? '',
        status: filters.status ?? '',
    });
    const timeout = useRef();

    const update = (key, value) => {
        const next = { ...search, [key]: value };
        setSearch(next);
        clearTimeout(timeout.current);
        timeout.current = setTimeout(() => {
            router.get(route('transport-orders.index'), next, {
                only: ['orders', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    };

    const inputCls = 'w-full rounded-md border-slate-200 text-sm shadow-sm focus:border-marine focus:ring-marine';

    return (
        <AuthenticatedLayout
             header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-marine">{t('nav.ordres', 'Ordres de transport')}</h1>
                        <p className="text-sm text-slate-600">
                            {orders.total} {orders.total > 1 ? t('ordres.resultats', 'résultats') : t('ordres.resultat', 'résultat')}
                        </p>
                    </div>
                    {estClient && (
                        <Link href={route('transport-orders.create')} className="rounded-lg bg-action px-4 py-2 text-sm font-semibold text-marine-deep hover:bg-action-dark">
                            + {t('commande.titre', 'Nouvelle expédition')}
                        </Link>
                    )}
                </div>
            }
        >
            <Head title={t('nav.ordres', 'Ordres de transport')} />

            <div className="overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-600">
                                <th className="px-6 py-4 font-semibold">{t('commun.reference', 'Référence')}</th>
                                <th className="px-6 py-4 font-semibold">{t('ordres.client', 'Client')}</th>
                                <th className="px-6 py-4 font-semibold">{t('suivi.destination', 'Destination')}</th>
                                <th className="px-6 py-4 font-semibold">{t('commun.statut', 'Statut')}</th>
                                <th className="px-6 py-4 text-right font-semibold">{t('ordres.cout', 'Coût est.')}</th>
                            </tr>
                            <tr className="border-b border-slate-100">
                                <th className="px-6 py-2"><input value={search.tracking} onChange={(e) => update('tracking', e.target.value)} placeholder="TRK-…" className={inputCls} /></th>
                                <th className="px-6 py-2"><input value={search.client} onChange={(e) => update('client', e.target.value)} placeholder={t('ordres.filtre_entreprise', 'Entreprise…')} className={inputCls} /></th>
                                <th className="px-6 py-2"><input value={search.destination} onChange={(e) => update('destination', e.target.value)} placeholder={t('ordres.filtre_ville', 'Ville, adresse…')} className={inputCls} /></th>
                                <th className="px-6 py-2">
                                    <select value={search.status} onChange={(e) => update('status', e.target.value)} className={inputCls}>
                                        <option value="">{t('ordres.tous', 'Tous')}</option>
                                        <option value="PENDING">{t('statut.en_attente', 'En attente')}</option>
                                        <option value="IN_PROGRESS">{t('statut.en_cours', 'En cours')}</option>
                                        <option value="DELIVERED">{t('statut.livre', 'Livré')}</option>
                                        <option value="CANCELLED">{t('statut.annule', 'Annulé')}</option>
                                    </select>
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
                                <tr
                                    key={order.id}
                                    onClick={() => router.visit(route('transport-orders.show', order.id))}
                                    className="cursor-pointer border-b border-slate-50 transition hover:bg-surface"
                                >
                                    <td className="px-6 py-4">
                                        <Link
                                            href={route('transport-orders.show', order.id)}
                                            className="font-semibold text-marine hover:underline"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            {order.tracking_number}
                                        </Link>
                                        <div className="text-xs text-slate-600">{v('marchandise', order.goods_type) ?? '—'}</div>
                                    </td>
                                    <td className="px-6 py-4 text-slate-700">{order.client?.company_name ?? '—'}</td>
                                    <td className="px-6 py-4 text-slate-600">{order.delivery_address}</td>
                                    <td className="px-6 py-4">
                                        <StatusBadge status={order.status} enAttenteDePaiement={order.en_attente_de_paiement} />
                                    </td>
                                    <td className="px-6 py-4 text-right font-medium text-marine">
                                        {order.estimated_cost ? `${order.estimated_cost} €` : '—'}
                                    </td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr><td colSpan="5" className="px-6 py-10 text-center text-slate-600">{t('ordres.aucun', 'Aucun ordre ne correspond à votre recherche.')}</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1 border-t border-slate-100 px-6 py-4">
                    {orders.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url ?? '#'}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                            className={`rounded-md px-3 py-1 text-sm ${
                                link.active ? 'bg-marine text-white' : 'text-slate-600 hover:bg-surface'
                            } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                        />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
