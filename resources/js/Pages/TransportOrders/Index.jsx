import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ orders }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Ordres de transport
                </h2>
            }
        >
            <Head title="Ordres de transport" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-gray-500">Suivi</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-500">Client</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-500">Trajet</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-500">Statut</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-500">Priorité</th>
                                    <th className="px-4 py-3 text-right font-medium text-gray-500">Coût est.</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {orders.data.map((order) => (
                                    <tr key={order.id}>
                                        <td className="px-4 py-3 font-mono text-gray-700">{order.tracking_number}</td>
                                        <td className="px-4 py-3 text-gray-900">{order.client?.company_name ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-600">{order.pickup_address} → {order.delivery_address}</td>
                                        <td className="px-4 py-3">{order.status}</td>
                                        <td className="px-4 py-3">{order.priority}</td>
                                        <td className="px-4 py-3 text-right">{order.estimated_cost ? `${order.estimated_cost} €` : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-1">
                        {orders.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`rounded px-3 py-1 ${
                                    link.active ? 'bg-gray-800 text-white' : 'bg-white text-gray-600'
                                } ${!link.url ? 'pointer-events-none opacity-50' : 'hover:bg-gray-100'}`}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}