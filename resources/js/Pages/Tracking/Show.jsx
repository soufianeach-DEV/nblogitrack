import { Head, useForm } from '@inertiajs/react';

export default function Show({ order, searched }) {
    const { data, setData, get, processing } = useForm({
        tracking_number: '',
        code: '',
    });

    const submit = (e) => {
        e.preventDefault();
        get(route('tracking.show'), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Suivi d'envoi" />
            <div className="min-h-screen bg-gray-100 py-12">
                <div className="mx-auto max-w-xl px-4">
                    <h1 className="mb-2 text-2xl font-bold text-gray-800">Suivi d'envoi</h1>
                    <p className="mb-6 text-gray-500">
                        Entrez votre numéro de suivi et le code reçu par email.
                    </p>

                    <form onSubmit={submit} className="space-y-3">
                        <input
                            type="text"
                            value={data.tracking_number}
                            onChange={(e) => setData('tracking_number', e.target.value)}
                            placeholder="Numéro de suivi (TRK-...)"
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            required
                        />
                        <input
                            type="text"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            placeholder="Code de suivi"
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            required
                        />
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-md bg-gray-800 px-5 py-2 font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                        >
                            Suivre mon envoi
                        </button>
                    </form>

                    {order && (
                        <div className="mt-8 rounded-lg bg-white p-6 shadow-sm">
                            <div className="mb-4 flex items-center justify-between">
                                <span className="font-mono text-gray-700">{order.tracking_number}</span>
                                <span className="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                                    {order.status}
                                </span>
                            </div>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Client</dt>
                                    <dd className="text-gray-900">{order.client?.company_name ?? '—'}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Départ</dt>
                                    <dd className="text-gray-900">{order.pickup_address}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Destination</dt>
                                    <dd className="text-gray-900">{order.delivery_address}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Livraison prévue</dt>
                                    <dd className="text-gray-900">{order.requested_delivery_date ?? '—'}</dd>
                                </div>
                            </dl>
                        </div>
                    )}

                    {searched && !order && (
                        <div className="mt-8 rounded-lg bg-red-50 p-4 text-red-700">
                            Aucun envoi trouvé. Vérifiez le numéro de suivi et le code.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}