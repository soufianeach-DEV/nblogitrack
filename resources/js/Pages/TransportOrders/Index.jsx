import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

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

export default function Index({ orders }) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">Ordres de transport</h1>
                    <p className="text-sm text-slate-500">{orders.total} ordres au total</p>
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