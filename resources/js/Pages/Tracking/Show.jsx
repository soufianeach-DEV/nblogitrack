import CarteTrajets from '@/Components/CarteTrajets';
import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const STATUTS = {
    PENDING: { libelle: 'En attente', classe: 'bg-slate-100 text-slate-700' },
    IN_PROGRESS: { libelle: 'En cours', classe: 'bg-brand-blue/10 text-brand-blue' },
    DELIVERED: { libelle: 'Livré', classe: 'bg-status-delivered/10 text-status-delivered' },
    CANCELLED: { libelle: 'Annulé', classe: 'bg-status-incident/10 text-status-incident' },
};

const PRIORITES = {
    URGENT: { libelle: 'Urgent', classe: 'bg-status-incident/10 text-status-incident' },
    HIGH: { libelle: 'Prioritaire', classe: 'bg-action/15 text-action-dark' },
};

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

/**
 * Le champ de recherche deroule tout ce que l'utilisateur peut consulter,
 * expeditions livrees comprises. Chercher a l'aveugle supposerait de
 * connaitre un numero par coeur.
 */
function ChoixExpedition({ catalogue, onChoisir }) {
    const [ouvert, setOuvert] = useState(false);
    const [saisie, setSaisie] = useState('');

    const resultats = useMemo(() => {
        const terme = saisie.trim().toLowerCase();

        if (terme === '') {
            return catalogue;
        }

        return catalogue.filter((e) => [e.numero, e.depart, e.arrivee, e.date]
            .some((champ) => champ && champ.toLowerCase().includes(terme)));
    }, [catalogue, saisie]);

    const choisir = (expedition) => {
        setSaisie('');
        setOuvert(false);
        onChoisir(expedition.numero);
    };

    return (
        <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-600">
                <Icone nom="recherche" className="h-5 w-5" />
            </span>
            <input
                value={saisie}
                onChange={(e) => {
                    setSaisie(e.target.value);
                    setOuvert(true);
                }}
                onFocus={() => setOuvert(true)}
                // Un clic sur une ligne passe par le survol : fermer aussitot
                // annulerait la selection avant qu'elle ne se produise.
                onBlur={() => setTimeout(() => setOuvert(false), 150)}
                onKeyDown={(e) => e.key === 'Escape' && setOuvert(false)}
                placeholder="Rechercher une expédition…"
                aria-label="Rechercher une expédition"
                aria-expanded={ouvert}
                role="combobox"
                aria-controls="liste-expeditions"
                className="w-full rounded-lg border-slate-300 py-2.5 pl-10 text-sm shadow-sm focus:border-marine focus:ring-marine"
            />

            {ouvert && (
                <div
                    id="liste-expeditions"
                    role="listbox"
                    className="absolute inset-x-0 top-full z-30 mt-1 max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                >
                    <p className="px-3 py-1.5 text-xs text-slate-600">
                        {resultats.length} expédition{resultats.length > 1 ? 's' : ''}
                        {saisie.trim() !== '' && ' sur ' + catalogue.length}
                    </p>

                    {resultats.length === 0 ? (
                        <p className="px-3 py-3 text-sm text-slate-600">Aucune expédition ne correspond.</p>
                    ) : resultats.map((expedition) => {
                        const statut = STATUTS[expedition.statut] ?? STATUTS.PENDING;

                        return (
                            <button
                                key={expedition.id}
                                type="button"
                                role="option"
                                aria-selected="false"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => choisir(expedition)}
                                className="flex w-full items-center gap-3 px-3 py-2 text-left transition hover:bg-surface"
                            >
                                <span className="w-32 shrink-0 font-mono text-xs text-brand-blue">{expedition.numero}</span>
                                <span className="min-w-0 flex-1 truncate text-sm text-marine">
                                    {expedition.depart} <span className="text-slate-600">→</span> {expedition.arrivee}
                                </span>
                                <span className="shrink-0 text-xs text-slate-600">{expedition.date}</span>
                                <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${statut.classe}`}>
                                    {statut.libelle}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

/**
 * Les jalons cote a cote plutot qu'empiles : la fiche tient alors dans la
 * hauteur de la page, sans defilement.
 */
function Frise({ etapes }) {
    return (
        <ol className="flex items-start">
            {etapes.map((etape, i) => (
                <li key={etape.libelle} className="flex-1">
                    <div className="flex items-center">
                        <span className={`h-0.5 flex-1 ${i === 0 ? 'bg-transparent' : etape.fait ? 'bg-status-delivered' : 'bg-slate-200'}`} />
                        <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${
                            etape.fait ? 'bg-status-delivered text-white' : 'bg-slate-200 text-slate-600'
                        }`}>
                            {etape.fait ? '✓' : i + 1}
                        </span>
                        <span className={`h-0.5 flex-1 ${
                            i === etapes.length - 1 ? 'bg-transparent' : etapes[i + 1].fait ? 'bg-status-delivered' : 'bg-slate-200'
                        }`} />
                    </div>

                    <div className="mt-2 px-2 text-center">
                        <p className={`text-sm font-semibold ${etape.fait ? 'text-marine' : 'text-slate-600'}`}>
                            {etape.libelle}
                        </p>
                        {etape.horodatage && (
                            <p className="text-xs font-medium text-brand-blue">{etape.horodatage}</p>
                        )}
                        <p className="mt-0.5 text-xs text-slate-600">{etape.detail}</p>
                    </div>
                </li>
            ))}
        </ol>
    );
}

/**
 * Une expedition dans la liste de gauche. La carte et la liste partagent la
 * meme selection : cliquer ici ou sur le trait revient au meme.
 */
function LigneExpedition({ expedition, active, onClick }) {
    const statut = STATUTS[expedition.statut] ?? STATUTS.PENDING;
    const priorite = PRIORITES[expedition.priorite];

    return (
        <li>
            <button
                type="button"
                onClick={onClick}
                aria-current={active ? 'true' : undefined}
                className={`w-full rounded-xl border p-3 text-left transition ${
                    active
                        ? 'border-brand-blue bg-brand-blue/5 shadow-sm'
                        : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm'
                }`}
            >
                <div className="flex items-center justify-between gap-2">
                    <span className="font-mono text-xs text-brand-blue">{expedition.numero}</span>
                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${statut.classe}`}>
                        {statut.libelle}
                    </span>
                </div>
                <p className="mt-1.5 truncate font-semibold text-marine">
                    {expedition.depart} <span className="text-slate-600">→</span> {expedition.arrivee}
                </p>
                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-600">
                    <span className="truncate">{expedition.marchandise}</span>
                    {expedition.livraison && <span>· {expedition.livraison}</span>}
                    {expedition.adr && (
                        <span className="rounded bg-status-incident/10 px-1.5 font-semibold text-status-incident">ADR</span>
                    )}
                    {priorite && (
                        <span className={`rounded px-1.5 font-semibold ${priorite.classe}`}>{priorite.libelle}</span>
                    )}
                </div>
            </button>
        </li>
    );
}

function SuiviConnecte({ order, searched, chauffeur, etapes, historique, expeditions = [], catalogue = [] }) {
    const { canPlan } = usePage().props.auth;
    const [agrandie, setAgrandie] = useState(false);
    const [itineraire, setItineraire] = useState(null);
    const [peages, setPeages] = useState([]);

    // Le trace routier et les peages arrivent apres la page, et chacun de son
    // cote : la recherche des peages demande une vingtaine de secondes la
    // premiere fois, le trace ne doit pas l'attendre.
    useEffect(() => {
        setItineraire(null);
        setPeages([]);

        if (! order?.id) {
            return undefined;
        }

        let vivant = true;

        const charger = (adresse, appliquer) => fetch(adresse, { headers: { Accept: 'application/json' } })
            .then((reponse) => (reponse.ok ? reponse.json() : null))
            .then((donnees) => {
                if (vivant && donnees) {
                    appliquer(donnees);
                }
            })
            .catch(() => {});

        charger(route('tracking.itineraire', order.id), setItineraire);
        charger(route('tracking.peages', order.id), (liste) => setPeages(Array.isArray(liste) ? liste : []));

        return () => {
            vivant = false;
        };
    }, [order?.id]);

    const ouvrir = (numero) => router.get(
        route('tracking.show'),
        numero ? { tracking_number: numero } : {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['order', 'chauffeur', 'etapes', 'historique', 'searched', 'expeditions'],
        },
    );

    const nombre = (valeur, unite) => valeur === null || valeur === undefined
        ? '—'
        : Number(valeur).toLocaleString('fr-FR') + ' ' + unite;

    const duree = (minutes) => {
        if (minutes === null || minutes === undefined) {
            return null;
        }

        const heures = Math.floor(minutes / 60);

        return heures > 0 ? `${heures} h ${String(minutes % 60).padStart(2, '0')}` : `${minutes} min`;
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">Suivi d'expédition</h1>}>
            <Head title="Suivi d'expédition" />

            <div className="flex flex-col gap-4 lg:h-[calc(100vh-11.5rem)] lg:flex-row">
                <div className={`flex w-full flex-col gap-3 lg:w-2/3 ${agrandie ? 'lg:hidden' : ''}`}>
                    <ChoixExpedition catalogue={catalogue} onChoisir={ouvrir} />

                    {order ? (
                        <section className="flex-1 overflow-y-auto rounded-2xl bg-white p-5 shadow-sm">
                            <button
                                type="button"
                                onClick={() => ouvrir(null)}
                                className="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-blue transition hover:text-marine"
                            >
                                ← Toutes les expéditions
                            </button>

                            <p className="font-mono text-sm text-brand-blue">{order.tracking_number}</p>
                            <h2 className="mt-1 text-lg font-bold leading-snug text-marine">
                                {order.pickup_address} → {order.delivery_address}
                            </h2>
                            <p className="mt-1 text-sm text-slate-600">{order.client?.company_name}</p>

                            <div className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-3">
                                <div className="rounded-lg bg-surface p-3">
                                    <p className="text-xs uppercase tracking-wide text-slate-600">Poids total</p>
                                    <p className="text-base font-bold text-marine">{nombre(order.weight, 'kg')}</p>
                                </div>
                                <div className="rounded-lg bg-surface p-3">
                                    <p className="text-xs uppercase tracking-wide text-slate-600">Distance routière</p>
                                    <p className="text-base font-bold text-marine">{nombre(order.distance_km, 'km')}</p>
                                </div>
                                <div className="rounded-lg bg-surface p-3">
                                    <p className="text-xs uppercase tracking-wide text-slate-600">Marchandise</p>
                                    <p className="text-base font-bold text-marine">
                                        {order.goods_type}{order.is_hazardous && ' · ADR'}
                                    </p>
                                </div>
                            </div>

                            {peages.length > 0 && (
                                <div className="mt-6 border-t border-slate-100 pt-4">
                                    <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Péages sur l'itinéraire
                                    </h3>
                                    <ol className="space-y-2">
                                        {peages.map((peage, i) => (
                                            <li key={i} className="flex items-center gap-3 rounded-lg bg-surface px-3 py-2">
                                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-action/20 text-action-dark">
                                                    <Icone nom="peage" className="h-4 w-4" />
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-semibold text-marine">{peage.nom}</span>
                                                    <span className="block text-xs text-slate-600">
                                                        {peage.portique ? 'Portique de péage' : 'Barrière de péage'}
                                                        {peage.route && ' · ' + peage.route}
                                                    </span>
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                </div>
                            )}

                            <h3 className="mb-4 mt-6 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                État de livraison
                            </h3>

                            {order.status === 'CANCELLED' ? (
                                <div className="rounded-lg bg-status-incident/10 p-4 text-status-incident">
                                    <p className="font-semibold">Expédition annulée</p>
                                </div>
                            ) : (
                                <Frise etapes={etapes} />
                            )}

                            <div className="mt-6 border-t border-slate-100 pt-4">
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
                                                className="mt-3 block rounded-lg border border-slate-200 px-4 py-2 text-center text-sm font-semibold text-marine transition hover:bg-surface"
                                            >
                                                {chauffeur.telephone}
                                            </a>
                                        )}
                                    </>
                                ) : (
                                    <p className="text-sm text-slate-600">Aucun chauffeur affecté pour l'instant.</p>
                                )}

                                {order.vehicle && (
                                    <p className="mt-3 text-sm text-slate-600">
                                        <span className="font-mono font-semibold text-marine">{order.vehicle.registration}</span>
                                        {' — '}{order.vehicle.brand} {order.vehicle.model}
                                    </p>
                                )}
                            </div>

                            {historique && historique.length > 0 && (
                                <div className="mt-6 border-t border-slate-100 pt-4">
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
                                </div>
                            )}
                        </section>
                    ) : (
                        <section className="flex flex-1 flex-col overflow-hidden rounded-2xl bg-white p-4 shadow-sm">
                            <div className="mb-3 flex items-baseline justify-between">
                                <h2 className="text-xs font-semibold uppercase tracking-wider text-slate-600">
                                    {canPlan ? 'Expéditions en cours' : 'Mes expéditions en cours'}
                                </h2>
                                <span className="text-xs text-slate-600">{expeditions.length}</span>
                            </div>

                            {searched && ! order && (
                                <p className="mb-3 rounded-lg bg-status-incident/10 p-3 text-sm text-status-incident">
                                    Aucune expédition ne porte ce numéro parmi celles que vous pouvez consulter.
                                </p>
                            )}

                            {expeditions.length === 0 ? (
                                <p className="py-8 text-center text-sm text-slate-600">
                                    {expeditions.length === 0
                                        ? 'Aucune expédition en circulation pour le moment.'
                                        : 'Aucune expédition ne correspond à cette recherche.'}
                                </p>
                            ) : (
                                <ul className="-mr-1 space-y-2 overflow-y-auto pr-1 lg:min-h-0 lg:flex-1">
                                    {expeditions.map((expedition) => (
                                        <LigneExpedition
                                            key={expedition.id}
                                            expedition={expedition}
                                            active={expedition.id === order?.id}
                                            onClick={() => ouvrir(expedition.numero)}
                                        />
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}
                </div>

                <div className={`relative h-[420px] overflow-hidden rounded-2xl shadow-sm lg:h-auto ${agrandie ? 'lg:w-full' : 'lg:w-1/3'}`}>
                    <CarteTrajets
                        trajets={expeditions}
                        selection={order?.id ?? null}
                        onSelection={(id) => {
                            const cible = expeditions.find((e) => e.id === id);

                            if (cible) {
                                ouvrir(cible.numero);
                            }
                        }}
                        itineraire={itineraire}
                        peages={peages}
                        className="h-full w-full"
                    />

                    <button
                        type="button"
                        onClick={() => setAgrandie(! agrandie)}
                        title={agrandie ? 'Réduire la carte' : 'Agrandir la carte'}
                        aria-label={agrandie ? 'Réduire la carte' : 'Agrandir la carte'}
                        aria-pressed={agrandie}
                        className="absolute right-3 top-3 z-[1100] rounded-lg bg-white p-2 text-marine shadow-md transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marine"
                    >
                        <Icone nom={agrandie ? 'reduire' : 'agrandir'} className="h-5 w-5" />
                    </button>

                    {order && (
                        <div className="pointer-events-none absolute inset-x-3 bottom-3 z-[1100] rounded-xl bg-white/95 px-3 py-2 shadow-lg backdrop-blur">
                            {itineraire === null ? (
                                <p className="text-xs text-slate-600">Calcul de l'itinéraire…</p>
                            ) : itineraire.direct ? (
                                <p className="text-xs text-slate-600">
                                    Itinéraire indisponible — liaison directe entre les deux points.
                                </p>
                            ) : (
                                <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                                    <span className="text-sm font-bold text-marine">
                                        {nombre(itineraire.distance_km, 'km')}
                                    </span>
                                    <span className="text-sm font-semibold text-brand-blue">
                                        {duree(itineraire.duree_min)}
                                    </span>
                                    <span className="text-xs text-slate-600">par la route</span>
                                    {peages.length > 0 && (
                                        <span className="ml-auto inline-flex items-center gap-1 rounded-full bg-action/15 px-2 py-0.5 text-xs font-semibold text-action-dark">
                                            <Icone nom="peage" className="h-3.5 w-3.5" />
                                            {peages.length} péage{peages.length > 1 ? 's' : ''}
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
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
