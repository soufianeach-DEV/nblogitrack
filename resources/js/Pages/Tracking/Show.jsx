import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const STEPS = [
    { key: 'PENDING', label: 'En attente', desc: 'Commande enregistrée.' },
    { key: 'IN_PROGRESS', label: 'En cours', desc: 'Colis en transit.' },
    { key: 'DELIVERED', label: 'Livré', desc: 'Livraison effectuée.' },
];

function Timeline({ status }) {
    if (status === 'CANCELLED') {
        return (
            <div className="rounded-lg bg-status-incident/10 p-4 text-status-incident">
                <p className="font-semibold">Envoi annulé</p>
                <p className="text-sm">Cet envoi a été annulé.</p>
            </div>
        );
    }
    const currentIdx = STEPS.findIndex((s) => s.key === status);
    return (
        <ol className="space-y-1">
            {STEPS.map((step, i) => {
                const done = i < currentIdx || status === 'DELIVERED';
                const active = i === currentIdx && status !== 'DELIVERED';
                return (
                    <li key={step.key} className="flex gap-4">
                        <div className="flex flex-col items-center">
                            <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${
                                done ? 'bg-status-delivered text-white'
                                : active ? 'bg-brand-blue text-white'
                                : 'bg-slate-200 text-slate-600'
                            }`}>
                                {done ? '✓' : i + 1}
                            </span>
                            {i < STEPS.length - 1 && (
                                <span className={`my-1 h-10 w-0.5 ${done ? 'bg-status-delivered' : 'bg-slate-200'}`} />
                            )}
                        </div>
                        <div className={`pt-1 ${active ? 'text-marine' : 'text-slate-600'}`}>
                            <div className="font-semibold">{step.label}</div>
                            <div className="text-sm text-slate-600">{step.desc}</div>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

function Recherche({ order, searched }) {
    const { data, setData, get, processing } = useForm({ tracking_number: '', code: '' });

    const submit = (e) => {
        e.preventDefault();
        get(route('tracking.show'), { preserveScroll: true });
    };

    return (
        <>
            <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                <input
                    type="text"
                    value={data.tracking_number}
                    onChange={(e) => setData('tracking_number', e.target.value)}
                    placeholder="Numéro de suivi (TRK-...)"
                    className="w-full rounded-lg border-slate-300 shadow-sm focus:border-marine focus:ring-marine"
                    required
                />
                <input
                    type="text"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    placeholder="Code"
                    className="w-full rounded-lg border-slate-300 shadow-sm focus:border-marine focus:ring-marine sm:w-48"
                    required
                />
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-lg bg-action px-6 py-2 font-semibold text-marine-deep transition hover:bg-action-dark disabled:opacity-50"
                >
                    Suivre
                </button>
            </form>

            {order && (
                <div className="mt-8 rounded-2xl bg-white p-6 shadow-sm">
                    <div className="mb-6 flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <div className="font-mono text-sm text-slate-600">{order.tracking_number}</div>
                            <div className="text-lg font-bold text-marine">{order.client?.company_name ?? 'Envoi'}</div>
                        </div>
                    </div>
                    <div className="grid gap-8 md:grid-cols-2">
                        <div>
                            <h3 className="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-600">État de livraison</h3>
                            <Timeline status={order.status} />
                        </div>
                        <dl className="space-y-3 text-sm">
                            <div><dt className="text-slate-600">Départ</dt><dd className="font-medium text-marine">{order.pickup_address}</dd></div>
                            <div><dt className="text-slate-600">Destination</dt><dd className="font-medium text-marine">{order.delivery_address}</dd></div>
                            <div><dt className="text-slate-600">Livraison prévue</dt><dd className="font-medium text-marine">{order.requested_delivery_date?.slice(0, 10) ?? '—'}</dd></div>
                        </dl>
                    </div>
                </div>
            )}

            {searched && !order && (
                <div className="mt-8 rounded-lg bg-status-incident/10 p-4 text-status-incident">
                    Aucun envoi trouvé. Vérifiez le numéro de suivi et le code.
                </div>
            )}
        </>
    );
}

export default function Show({ order, searched }) {
    const utilisateur = usePage().props.auth?.user;

    // Connecte, le suivi s'affiche dans l'application avec sa navigation.
    if (utilisateur) {
        return (
            <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">Suivi d'envoi</h1>}>
                <Head title="Suivi d'envoi" />

                <div className="max-w-3xl">
                    <p className="mb-6 text-sm text-slate-600">
                        Saisissez le numéro de suivi et le code communiqués au client.
                    </p>
                    <Recherche order={order} searched={searched} />
                </div>
            </AuthenticatedLayout>
        );
    }

    // Visiteur : page autonome, sans acces au reste de l'application.
    return (
        <>
            <Head title="Suivi d'envoi" />
            <div className="flex min-h-screen flex-col bg-surface">
                <header className="bg-marine">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
                        <img src="/images/logo-blanc.png" alt="NBLogiTrack" className="h-8 w-auto" />
                        <Link href={route('login')} className="text-sm font-medium text-slate-300 transition hover:text-white">
                            Espace client
                        </Link>
                    </div>
                </header>

                <div className="mx-auto w-full max-w-3xl flex-1 px-4 py-10">
                    <h1 className="text-2xl font-bold text-marine">Suivi d'envoi</h1>
                    <p className="mb-6 mt-1 text-slate-600">Entrez votre numéro de suivi et le code reçu par e-mail.</p>

                    <Recherche order={order} searched={searched} />
                </div>

                <footer className="border-t border-slate-200 py-6 text-center text-xs text-slate-600">
                    NBLogiTrack Belgium — suivi d'expédition
                </footer>
            </div>
        </>
    );
}
