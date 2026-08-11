import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const ETAPES_PUBLIQUES = [
    { cle: 'PENDING', libelle: 'En attente', detail: 'Commande enregistrée.' },
    { cle: 'IN_PROGRESS', libelle: 'En cours', detail: 'Marchandise en transit.' },
    { cle: 'DELIVERED', libelle: 'Livré', detail: 'Livraison effectuée.' },
];

function Jalon({ libelle, detail, horodatage, fait, actif, dernier }) {
    return (
        <li className="flex gap-4">
            <div className="flex flex-col items-center">
                <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${
                    fait ? 'bg-status-delivered text-white'
                        : actif ? 'bg-brand-blue text-white'
                            : 'bg-slate-200 text-slate-600'
                }`}>
                    {fait ? '✓' : '•'}
                </span>
                {! dernier && <span className={`my-1 h-12 w-0.5 ${fait ? 'bg-status-delivered' : 'bg-slate-200'}`} />}
            </div>
            <div className={`pt-1 ${actif ? 'text-marine' : 'text-slate-600'}`}>
                <div className="flex flex-wrap items-baseline gap-3">
                    <span className="font-semibold">{libelle}</span>
                    {horodatage && <span className="text-xs text-slate-600">{horodatage}</span>}
                </div>
                <div className="text-sm text-slate-600">{detail}</div>
            </div>
        </li>
    );
}

function SuiviConnecte({ order, searched, chauffeur, etapes, historique }) {
    const { data, setData, get, processing } = useForm({ tracking_number: '' });

    const chercher = (e) => {
        e.preventDefault();
        get(route('tracking.show'), { preserveScroll: true });
    };

    const nombre = (valeur, unite) => valeur === null || valeur === undefined
        ? '—'
        : Number(valeur).toLocaleString('fr-FR') + ' ' + unite;

    const encours = etapes ? etapes.filter((e) => e.fait).length : 0;

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">Suivi d'expédition</h1>}>
            <Head title="Suivi d'expédition" />

            <form onSubmit={chercher} className="mb-6 flex max-w-xl gap-3">
                <div className="relative w-full">
                    <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-600">
                        <Icone nom="recherche" className="h-5 w-5" />
                    </span>
                    <input
                        value={data.tracking_number}
                        onChange={(e) => setData('tracking_number', e.target.value.toUpperCase())}
                        placeholder="Rechercher un numéro de suivi…"
                        aria-label="Numéro de suivi"
                        className="w-full rounded-lg border-slate-300 py-2.5 pl-10 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        required
                    />
                </div>
                <button
                    disabled={processing}
                    className="shrink-0 rounded-lg bg-marine px-6 text-sm font-bold text-white transition hover:bg-marine-deep disabled:opacity-50"
                >
                    Rechercher
                </button>
            </form>

            {searched && ! order && (
                <p className="max-w-xl rounded-lg bg-status-incident/10 p-4 text-sm text-status-incident">
                    Aucune expédition ne porte ce numéro parmi celles que vous pouvez consulter.
                </p>
            )}

            {order && (
                <div className="grid gap-4 lg:grid-cols-3">
                    <section className="rounded-2xl bg-white p-6 shadow-sm lg:col-span-2">
                        <p className="font-mono text-sm text-brand-blue">{order.tracking_number}</p>
                        <h2 className="mt-1 text-xl font-bold text-marine">
                            {order.pickup_address} → {order.delivery_address}
                        </h2>
                        <p className="mt-1 text-sm text-slate-600">{order.client?.company_name}</p>

                        <div className="mt-5 grid gap-3 border-t border-slate-100 pt-5 sm:grid-cols-3">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-600">Poids total</p>
                                <p className="text-lg font-bold text-marine">{nombre(order.weight, 'kg')}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-600">Distance routière</p>
                                <p className="text-lg font-bold text-marine">{nombre(order.distance_km, 'km')}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-600">Marchandise</p>
                                <p className="text-lg font-bold text-marine">
                                    {order.goods_type}{order.is_hazardous && ' · ADR'}
                                </p>
                            </div>
                        </div>

                        <h3 className="mb-4 mt-8 text-xs font-semibold uppercase tracking-wider text-slate-600">
                            État de livraison
                        </h3>

                        {order.status === 'CANCELLED' ? (
                            <div className="rounded-lg bg-status-incident/10 p-4 text-status-incident">
                                <p className="font-semibold">Expédition annulée</p>
                            </div>
                        ) : (
                            <ol>
                                {etapes.map((etape, i) => (
                                    <Jalon
                                        key={etape.libelle}
                                        {...etape}
                                        actif={i === encours}
                                        dernier={i === etapes.length - 1}
                                    />
                                ))}
                            </ol>
                        )}
                    </section>

                    <div className="space-y-4">
                        <section className="rounded-2xl bg-white p-6 shadow-sm">
                            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Prise en charge
                            </h3>
                            {chauffeur ? (
                                <>
                                    <div className="flex items-center gap-3">
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-blue/10 text-sm font-bold text-brand-blue">
                                            {chauffeur.nom.split(' ').map((m) => m[0]).slice(0, 2).join('')}
                                        </span>
                                        <div>
                                            <p className="font-semibold text-marine">{chauffeur.nom}</p>
                                            <p className="text-xs text-slate-600">
                                                Chauffeur{chauffeur.adr && ' · certifié ADR'}
                                            </p>
                                        </div>
                                    </div>
                                    {chauffeur.telephone && (
                                        <a
                                            href={'tel:' + chauffeur.telephone.replace(/\s/g, '')}
                                            className="mt-4 block rounded-lg border border-slate-200 px-4 py-2 text-center text-sm font-semibold text-marine transition hover:bg-surface"
                                        >
                                            {chauffeur.telephone}
                                        </a>
                                    )}
                                </>
                            ) : (
                                <p className="text-sm text-slate-600">Aucun chauffeur affecté pour l'instant.</p>
                            )}

                            {order.vehicle && (
                                <p className="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-600">
                                    <span className="font-mono font-semibold text-marine">{order.vehicle.registration}</span>
                                    {' — '}{order.vehicle.brand} {order.vehicle.model}
                                </p>
                            )}
                        </section>

                        {historique && historique.length > 0 && (
                            <section className="rounded-2xl bg-white p-6 shadow-sm">
                                <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                    Historique horodaté
                                </h3>
                                <ol className="space-y-3">
                                    {historique.map((ligne, i) => (
                                        <li key={i} className="border-l-2 border-slate-200 pl-3">
                                            <p className="text-xs text-slate-600">{ligne.horodatage}</p>
                                            <p className="text-sm text-marine">{ligne.description}</p>
                                        </li>
                                    ))}
                                </ol>
                            </section>
                        )}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}

function SuiviVisiteur({ order, searched }) {
    const { data, setData, get, processing } = useForm({ tracking_number: '', code: '' });

    const chercher = (e) => {
        e.preventDefault();
        get(route('tracking.show'), { preserveScroll: true });
    };

    const courant = ETAPES_PUBLIQUES.findIndex((e) => e.cle === order?.status);

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
                    <p className="mb-6 mt-1 text-slate-600">
                        Entrez votre numéro de suivi et le code reçu par e-mail.
                    </p>

                    <form onSubmit={chercher} className="flex flex-col gap-3 sm:flex-row">
                        <input
                            value={data.tracking_number}
                            onChange={(e) => setData('tracking_number', e.target.value)}
                            placeholder="Numéro de suivi (TRK-…)"
                            className="w-full rounded-lg border-slate-300 shadow-sm focus:border-marine focus:ring-marine"
                            required
                        />
                        <input
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            placeholder="Code"
                            className="w-full rounded-lg border-slate-300 shadow-sm focus:border-marine focus:ring-marine sm:w-48"
                            required
                        />
                        <button
                            disabled={processing}
                            className="rounded-lg bg-action px-6 py-2 font-semibold text-marine-deep transition hover:bg-action-dark disabled:opacity-50"
                        >
                            Suivre
                        </button>
                    </form>

                    {order && (
                        <div className="mt-8 rounded-2xl bg-white p-6 shadow-sm">
                            <div className="mb-6 border-b border-slate-100 pb-4">
                                <div className="font-mono text-sm text-slate-600">{order.tracking_number}</div>
                                <div className="text-lg font-bold text-marine">{order.client?.company_name ?? 'Envoi'}</div>
                            </div>
                            <div className="grid gap-8 md:grid-cols-2">
                                <div>
                                    <h3 className="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        État de livraison
                                    </h3>
                                    {order.status === 'CANCELLED' ? (
                                        <div className="rounded-lg bg-status-incident/10 p-4 text-status-incident">
                                            <p className="font-semibold">Envoi annulé</p>
                                        </div>
                                    ) : (
                                        <ol>
                                            {ETAPES_PUBLIQUES.map((etape, i) => (
                                                <Jalon
                                                    key={etape.cle}
                                                    libelle={etape.libelle}
                                                    detail={etape.detail}
                                                    fait={i < courant || order.status === 'DELIVERED'}
                                                    actif={i === courant && order.status !== 'DELIVERED'}
                                                    dernier={i === ETAPES_PUBLIQUES.length - 1}
                                                />
                                            ))}
                                        </ol>
                                    )}
                                </div>
                                <dl className="space-y-3 text-sm">
                                    <div><dt className="text-slate-600">Départ</dt><dd className="font-medium text-marine">{order.pickup_address}</dd></div>
                                    <div><dt className="text-slate-600">Destination</dt><dd className="font-medium text-marine">{order.delivery_address}</dd></div>
                                    <div><dt className="text-slate-600">Livraison prévue</dt><dd className="font-medium text-marine">{order.requested_delivery_date?.slice(0, 10) ?? '—'}</dd></div>
                                </dl>
                            </div>
                        </div>
                    )}

                    {searched && ! order && (
                        <div className="mt-8 rounded-lg bg-status-incident/10 p-4 text-status-incident">
                            Aucun envoi trouvé. Vérifiez le numéro de suivi et le code.
                        </div>
                    )}
                </div>

                <footer className="border-t border-slate-200 py-6 text-center text-xs text-slate-600">
                    NBLogiTrack Belgium — suivi d'expédition
                </footer>
            </div>
        </>
    );
}

export default function Show(props) {
    return usePage().props.auth?.user
        ? <SuiviConnecte {...props} />
        : <SuiviVisiteur {...props} />;
}
