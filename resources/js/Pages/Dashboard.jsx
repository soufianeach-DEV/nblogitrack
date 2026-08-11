import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const STATUS = {
    PENDING: { label: 'En attente', cls: 'bg-status-pending/10 text-status-pending' },
    IN_PROGRESS: { label: 'En cours', cls: 'bg-status-progress/10 text-status-progress' },
    DELIVERED: { label: 'Livré', cls: 'bg-status-delivered/10 text-status-delivered' },
    CANCELLED: { label: 'Annulé', cls: 'bg-status-incident/10 text-status-incident' },
};

function StatCard({ label, value, icone, accent }) {
    return (
        <div className="rounded-2xl bg-white p-6 shadow-sm">
            <span className={`inline-flex rounded-lg p-2 ${accent}`}>
                <Icone nom={icone} className="h-6 w-6" />
            </span>
            <div className="mt-4 text-3xl font-bold text-marine">{value}</div>
            <div className="text-sm text-slate-600">{label}</div>
        </div>
    );
}

export default function Dashboard({ stats, recent }) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">Tableau de bord</h1>
                    <p className="text-sm text-slate-600">Aperçu de vos ordres de transport.</p>
                </div>
            }
        >
            <Head title="Tableau de bord" />

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Total ordres" value={stats.total} icone="camion" accent="bg-marine/10 text-marine" />
                <StatCard label="En attente" value={stats.pending} icone="horloge" accent="bg-status-pending/10 text-status-pending" />
                <StatCard label="En cours" value={stats.in_progress} icone="rotation" accent="bg-status-progress/10 text-status-progress" />
                <StatCard label="Livrés" value={stats.delivered} icone="coche" accent="bg-status-delivered/10 text-status-delivered" />
            </div>

            <div className="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 className="font-semibold text-marine">Derniers ordres</h2>
                    <Link href={route('transport-orders.index')} className="text-sm font-medium text-action hover:underline">
                        Voir tout
                    </Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <tbody>
                            {recent.map((order) => (
                                <tr key={order.id} className="border-b border-slate-50 last:border-0">
                                    <td className="px-6 py-4 font-mono font-semibold text-marine">{order.tracking_number}</td>
                                    <td className="px-6 py-4 text-slate-700">{order.client?.company_name ?? '—'}</td>
                                    <td className="px-6 py-4 text-slate-600">{order.delivery_address}</td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase ${STATUS[order.status]?.cls ?? 'bg-slate-100 text-slate-600'}`}>
                                            {STATUS[order.status]?.label ?? order.status}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                            {recent.length === 0 && (
                                <tr><td className="px-6 py-8 text-center text-slate-600" colSpan="4">Aucun ordre pour l'instant.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}