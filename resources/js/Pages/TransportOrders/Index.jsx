import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';

const STATUS = {
    PENDING: { label: 'En attente', cls: 'bg-status-pending/10 text-status-pending' },
    IN_PROGRESS: { label: 'En cours', cls: 'bg-status-progress/10 text-status-progress' },
    DELIVERED: { label: 'Livré', cls: 'bg-status-delivered/10 text-status-delivered' },
    CANCELLED: { label: 'Annulé', cls: 'bg-status-incident/10 text-status-incident' },
};

function StatusBadge({ status }) {
    const s = STATUS[status] ?? { label: status, cls: 'bg-slate-100 text-slate-600' };
    return (
        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${s.cls}`}>
            {s.label}
        </span>
    );
}

export default function Index({ orders, filters }) {
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
                        <h1 className="text-2xl font-bold text-marine">Ordres de transport</h1>
                        <p className="text-sm text-slate-500">{orders.total} résultat(s)</p>
                    </div>
                    <Link href={route('transport-orders.create')} className="rounded-lg bg-action px-4 py-2 text-sm font-semibold text-marine-deep hover:bg-action-dark">
                        + Nouvelle expédition
                    </Link>
                </div>
            }
        >
            <Head title="Ordres de transport" />

            <div className="overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                                <th className="px-6 py-4 font-semibold">Référence</th>
                                <th className="px-6 py-4 font-semibold">Client</th>
                                <th className="px-6 py-4 font-semibold">Destination</th>
                                <th className="px-6 py-4 font-semibold">Statut</th>
                                <th className="px-6 py-4 text-right font-semibold">Coût est.</th>
                            </tr>
                            <tr className="border-b border-slate-100">
                                <th className="px-6 py-2"><input value={search.tracking} onChange={(e) => update('tracking', e.target.value)} placeholder="TRK-…" className={inputCls} /></th>
                                <th className="px-6 py-2"><input value={search.client} onChange={(e) => update('client', e.target.value)} placeholder="Entreprise…" className={inputCls} /></th>
                                <th className="px-6 py-2"><input value={search.destination} onChange={(e) => update('destination', e.target.value)} placeholder="Ville, adresse…" className={inputCls} /></th>
                                <th className="px-6 py-2">
                                    <select value={search.status} onChange={(e) => update('status', e.target.value)} className={inputCls}>
                                        <option value="">Tous</option>
                                        <option value="PENDING">En attente</option>
                                        <option value="IN_PROGRESS">En cours</option>
                                        <option value="DELIVERED">Livré</option>
                                        <option value="CANCELLED">Annulé</option>
                                    </select>
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
                                <tr key={order.id} className="border-b border-slate-50 transition hover:bg-surface">
                                    <td className="px-6 py-4">
                                        <div className="font-semibold text-marine">{order.tracking_number}</div>
                                        <div className="text-xs text-slate-400">{order.goods_type ?? '—'}</div>
                                    </td>
                                    <td className="px-6 py-4 text-slate-700">{order.client?.company_name ?? '—'}</td>
                                    <td className="px-6 py-4 text-slate-500">{order.delivery_address}</td>
                                    <td className="px-6 py-4"><StatusBadge status={order.status} /></td>
                                    <td className="px-6 py-4 text-right font-medium text-marine">
                                        {order.estimated_cost ? `${order.estimated_cost} €` : '—'}
                                    </td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr><td colSpan="5" className="px-6 py-10 text-center text-slate-400">Aucun ordre ne correspond à ta recherche.</td></tr>
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