import CarteTrajets from '@/Components/CarteTrajets';
import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STATUS = {
    PENDING: { label: 'En attente', cls: 'bg-status-pending/10 text-status-pending' },
    IN_PROGRESS: { label: 'En cours', cls: 'bg-status-progress/10 text-status-progress' },
    DELIVERED: { label: 'Livré', cls: 'bg-status-delivered/10 text-status-delivered' },
    CANCELLED: { label: 'Annulé', cls: 'bg-status-incident/10 text-status-incident' },
};

function StatCard({ label, value, unite, detail, icone, accent, lien }) {
    const contenu = (
        <>
            <span className={`inline-flex rounded-lg p-2 ${accent}`}>
                <Icone nom={icone} className="h-6 w-6" />
            </span>
            <div className="mt-4 flex items-baseline gap-1">
                <span className="text-3xl font-bold text-marine">{value}</span>
                {unite && <span className="text-sm font-semibold text-slate-600">{unite}</span>}
            </div>
            <div className="text-sm text-slate-600">{label}</div>
            {detail && <div className="mt-0.5 text-xs text-slate-600">{detail}</div>}
        </>
    );

    if (lien) {
        return (
            <Link href={lien} className="block rounded-2xl bg-white p-6 shadow-sm transition hover:shadow-md">
                {contenu}
            </Link>
        );
    }

    return <div className="rounded-2xl bg-white p-6 shadow-sm">{contenu}</div>;
}

/**
 * Histogramme dessine en CSS. Une bibliotheque de graphiques pour sept
 * barres ajouterait une dependance de plusieurs centaines de kilo-octets
 * pour ce que deux div font tres bien.
 */
function VolumeMensuel({ volume }) {
    // L'echelle tient compte des deux annees, sinon la barre de reference
    // deborderait des qu'un mois de l'an dernier a fait mieux.
    const maximum = Math.max(...volume.flatMap((m) => [m.nombre, m.nombre_n1]), 1);
    const total = volume.reduce((somme, m) => somme + m.nombre, 0);
    const totalN1 = volume.reduce((somme, m) => somme + m.nombre_n1, 0);
    const pic = volume.reduce((a, b) => (b.nombre > a.nombre ? b : a), volume[0]);

    const annee = volume[volume.length - 1]?.annee;
    const anneeN1 = String(Number(annee) - 1);

    // Une variation ne se calcule pas sur une base nulle : l'an dernier sans
    // activite ne fait pas une croissance infinie, il fait une absence de
    // comparaison.
    const variation = totalN1 > 0 ? Math.round(((total - totalN1) / totalN1) * 100) : null;

    return (
        <section className="flex h-full flex-col rounded-2xl bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-marine">Volume mensuel</h2>
                    <p className="text-sm text-slate-600">Sept derniers mois, comparés à la même période l'an dernier</p>
                </div>
                <div className="flex items-center gap-3 text-xs text-slate-600">
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-2.5 rounded-sm bg-brand-blue/30" />
                        {anneeN1}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-2.5 rounded-sm bg-marine" />
                        {annee}
                    </span>
                </div>
            </div>

            <div className="mt-5 flex h-36 items-end gap-2">
                {volume.map((mois) => (
                    <div
                        key={mois.cle}
                        className="flex h-full flex-1 flex-col justify-end"
                        title={`${mois.libelle} : ${mois.nombre} expédition${mois.nombre > 1 ? 's' : ''} (${mois.tonnes} t)`
                            + ` — ${mois.nombre_n1} l'an dernier (${mois.tonnes_n1} t)`}
                    >
                        <span className="mb-1 text-center text-xs font-semibold text-marine">{mois.nombre}</span>
                        <div className="flex h-full items-end justify-center gap-0.5">
                            <div
                                className="w-1/2 rounded-t bg-brand-blue/30"
                                style={{ height: `${Math.max((mois.nombre_n1 / maximum) * 100, 1)}%` }}
                            />
                            <div
                                className={`w-1/2 rounded-t transition-all ${
                                    mois.cle === pic.cle ? 'bg-action' : 'bg-marine'
                                }`}
                                style={{ height: `${Math.max((mois.nombre / maximum) * 100, 1)}%` }}
                            />
                        </div>
                    </div>
                ))}
            </div>
            <div className="mt-1 flex gap-2">
                {volume.map((mois) => (
                    <span key={mois.cle} className="flex-1 text-center text-xs capitalize text-slate-600">
                        {mois.libelle}
                    </span>
                ))}
            </div>

            <div className="mt-auto flex flex-wrap justify-between gap-4 border-t border-slate-100 pt-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-slate-600">Sur la période</p>
                    <p className="font-bold text-marine">
                        {total} expéditions
                        {variation !== null && (
                            <span className={`ml-2 text-sm font-bold ${
                                variation > 0 ? 'text-status-delivered' : variation < 0 ? 'text-status-incident' : 'text-slate-600'
                            }`}>
                                {variation > 0 ? '+' : ''}{variation} %
                            </span>
                        )}
                    </p>
                    <p className="text-xs text-slate-600">
                        {totalN1} sur la même période {anneeN1}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-xs uppercase tracking-wide text-slate-600">Mois le plus chargé</p>
                    <p className="font-bold capitalize text-marine">
                        {pic.libelle} — {pic.tonnes.toLocaleString('fr-FR')} t
                    </p>
                </div>
            </div>
        </section>
    );
}

function ValidationsEnAttente({ validations }) {
    return (
        <section className="rounded-2xl bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-2">
                <h2 className="font-semibold text-marine">Validations en attente</h2>
                <Link href={route('clients.index')} className="text-sm font-medium text-action hover:underline">
                    Voir tout
                </Link>
            </div>

            {validations.length === 0 ? (
                <p className="text-sm text-slate-600">Aucune entreprise n'attend de décision.</p>
            ) : (
                <ul className="space-y-2">
                    {validations.map((entreprise) => (
                        <li key={entreprise.id} className="rounded-xl border border-slate-200 p-3">
                            <p className="truncate font-semibold text-marine">{entreprise.entreprise}</p>
                            <p className="truncate text-xs text-slate-600">
                                {[entreprise.secteur, entreprise.pays].filter(Boolean).join(' · ')}
                            </p>
                            <p className="mt-1 font-mono text-xs text-slate-600">{entreprise.tva}</p>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

const NIVEAUX = {
    grave: 'border-status-incident/30 bg-status-incident/5 text-status-incident',
    attention: 'border-action/40 bg-action/10 text-action-dark',
    info: 'border-brand-blue/30 bg-brand-blue/5 text-brand-blue',
};

/**
 * Les alertes sortent des donnees et disparaissent quand le probleme est
 * regle. Le prototype en montre deux ecrites en dur, qui resteraient
 * affichees quoi qu'il arrive.
 */
function Alertes({ alertes }) {
    return (
        <section className="rounded-2xl bg-white p-6 shadow-sm">
            <h2 className="mb-4 font-semibold text-marine">Alertes</h2>

            {alertes.length === 0 ? (
                <p className="rounded-xl border border-status-delivered/30 bg-status-delivered/5 px-3 py-4 text-sm text-status-delivered">
                    Rien à signaler.
                </p>
            ) : (
                <ul className="space-y-2">
                    {alertes.map((alerte, i) => {
                        const corps = (
                            <>
                                <p className="text-sm font-bold">{alerte.titre}</p>
                                <p className="mt-0.5 text-xs text-slate-600">{alerte.detail}</p>
                            </>
                        );

                        return (
                            <li key={i}>
                                {alerte.lien ? (
                                    <Link
                                        href={alerte.lien}
                                        className={`block rounded-xl border px-3 py-2.5 transition hover:brightness-95 ${NIVEAUX[alerte.niveau] ?? NIVEAUX.info}`}
                                    >
                                        {corps}
                                    </Link>
                                ) : (
                                    <div className={`rounded-xl border px-3 py-2.5 ${NIVEAUX[alerte.niveau] ?? NIVEAUX.info}`}>
                                        {corps}
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

function CarteEnCirculation({ carte, total }) {
    const [traces, setTraces] = useState({});

    // Les itineraires arrivent un par un, apres la page. Les demander tous en
    // meme temps saturerait le service, et la liaison directe tient la place
    // en attendant chacun d'eux.
    useEffect(() => {
        let vivant = true;

        (async () => {
            for (const trajet of carte) {
                try {
                    const reponse = await fetch(route('tracking.itineraire', trajet.id), {
                        headers: { Accept: 'application/json' },
                    });

                    if (! vivant) {
                        return;
                    }

                    const donnees = reponse.ok ? await reponse.json() : null;

                    if (vivant && donnees?.geometrie && ! donnees.direct) {
                        setTraces((precedentes) => ({ ...precedentes, [trajet.id]: donnees.geometrie }));
                    }
                } catch (erreur) {
                    // Un itineraire manquant laisse simplement la liaison directe.
                }
            }
        })();

        return () => {
            vivant = false;
        };
    }, [carte]);

    const trajets = carte.map((trajet) => ({ ...trajet, trace: traces[trajet.id] }));

    const ouvrir = (id) => {
        const cible = carte.find((trajet) => trajet.id === id);

        if (cible) {
            router.get(route('tracking.show'), { tracking_number: cible.numero });
        }
    };

    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
            <div className="relative h-56">
                <CarteTrajets trajets={trajets} onSelection={ouvrir} className="h-full w-full" />
                <span className="pointer-events-none absolute left-3 top-3 z-[1100] rounded-lg bg-marine px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white shadow">
                    {total > carte.length ? `${carte.length} des ${total}` : total} en circulation
                </span>
            </div>
            <Link
                href={route('tracking.show')}
                className="block border-t border-slate-100 px-4 py-2.5 text-center text-sm font-semibold text-action transition hover:bg-surface"
            >
                Ouvrir le suivi
            </Link>
        </section>
    );
}

function Facturation({ facturation }) {
    const euros = (montant) => Number(montant).toLocaleString('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    });

    return (
        <section className="flex h-full flex-col rounded-2xl bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-2">
                <h2 className="font-semibold text-marine">Facturation</h2>
                <Link href={route('invoices.index')} className="text-sm font-medium text-action hover:underline">
                    Voir tout
                </Link>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div className="rounded-xl bg-status-delivered/5 px-3 py-2.5">
                    <p className="text-[11px] uppercase tracking-wide text-slate-600">Réglé</p>
                    <p className="text-lg font-bold text-status-delivered">{euros(facturation.paye)}</p>
                </div>
                <div className={`rounded-xl px-3 py-2.5 ${facturation.en_retard > 0 ? 'bg-status-incident/5' : 'bg-surface'}`}>
                    <p className="text-[11px] uppercase tracking-wide text-slate-600">Reste dû</p>
                    <p className={`text-lg font-bold ${facturation.en_retard > 0 ? 'text-status-incident' : 'text-marine'}`}>
                        {euros(facturation.du)}
                    </p>
                </div>
            </div>

            {facturation.en_retard > 0 && (
                <p className="mt-2 text-xs font-semibold text-status-incident">
                    dont {facturation.en_retard} facture{facturation.en_retard > 1 ? 's' : ''} échue{facturation.en_retard > 1 ? 's' : ''}
                </p>
            )}

            {facturation.dernieres.length > 0 && (
                <>
                    <h3 className="mb-2 mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                        Dernières factures
                    </h3>
                    <ul className="space-y-1.5">
                        {facturation.dernieres.map((facture) => (
                            <li key={facture.reference}>
                                <Link
                                    href={route('invoices.show', facture.id)}
                                    className="flex items-center gap-2 rounded-lg bg-surface px-3 py-2 transition hover:bg-slate-200"
                                >
                                <span className="font-mono text-xs text-brand-blue">{facture.reference}</span>
                                <span className="ml-auto shrink-0 text-sm font-bold text-marine">{facture.montant}</span>
                                <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                    facture.etat === 'Payée' ? 'bg-status-delivered/10 text-status-delivered'
                                        : facture.etat === 'En retard' ? 'bg-status-incident/10 text-status-incident'
                                            : 'bg-slate-100 text-slate-700'
                                }`}>
                                    {facture.etat}
                                </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}

function DernieresTraces({ journal }) {
    return (
        <section className="rounded-2xl bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-2">
                <h2 className="font-semibold text-marine">Dernières traces</h2>
                <Link href={route('activity-logs.index')} className="text-sm font-medium text-action hover:underline">
                    Voir tout
                </Link>
            </div>

            {journal.length === 0 ? (
                <p className="text-sm text-slate-600">Le journal est vide.</p>
            ) : (
                <ol className="space-y-3">
                    {journal.map((ligne, i) => (
                        <li key={i} className="border-l-2 border-slate-200 pl-3">
                            <p className="text-xs text-slate-600">
                                {ligne.horodatage} — {ligne.auteur}
                            </p>
                            <p className="text-sm text-marine">{ligne.description}</p>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}

export default function Dashboard({
    stats,
    recent,
    performance,
    volume = [],
    carte = [],
    carteTotal = 0,
    alertes = [],
    facturation = null,
    exploitation = null,
    validations = null,
    journal = null,
}) {
    const { auth } = usePage().props;
    const personnel = Boolean(exploitation);

    const nombre = (valeur) => valeur === null || valeur === undefined
        ? '—'
        : Number(valeur).toLocaleString('fr-FR');

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">
                        {personnel ? "Vue d'ensemble" : 'Tableau de bord'}
                    </h1>
                    <p className="text-sm text-slate-600">
                        {personnel
                            ? "Activité de l'exploitation et dossiers en attente."
                            : 'Aperçu de vos ordres de transport.'}
                    </p>
                </div>
            }
        >
            <Head title="Tableau de bord" />

            {personnel ? (
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard
                        label="Entreprises à valider"
                        value={nombre(exploitation.entreprises_a_valider)}
                        icone="valide"
                        accent="bg-action/15 text-action-dark"
                        lien={auth.canValidateClients ? route('clients.index') : null}
                    />
                    <StatCard
                        label="Expéditions en cours"
                        value={nombre(stats.pending + stats.in_progress)}
                        detail={`${nombre(stats.in_progress)} en circulation`}
                        icone="camion"
                        accent="bg-marine/10 text-marine"
                        lien={route('transport-orders.index')}
                    />
                    <StatCard
                        label="Chauffeurs disponibles"
                        value={nombre(exploitation.chauffeurs_disponibles)}
                        detail={`sur ${nombre(exploitation.chauffeurs_total)}`}
                        icone="profil"
                        accent="bg-status-progress/10 text-status-progress"
                    />
                    <StatCard
                        label="Véhicules disponibles"
                        value={nombre(exploitation.vehicules_disponibles)}
                        detail={`sur ${nombre(exploitation.vehicules_total)}`}
                        icone="camion"
                        accent="bg-status-delivered/10 text-status-delivered"
                    />
                </div>
            ) : (
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard
                        label="Expéditions actives"
                        value={nombre(performance.actives)}
                        detail={`${nombre(stats.in_progress)} en circulation`}
                        icone="camion"
                        accent="bg-marine/10 text-marine"
                    />
                    <StatCard
                        label="Délai moyen"
                        value={performance.delai_moyen === null ? '—' : nombre(performance.delai_moyen)}
                        unite={performance.delai_moyen === null ? null : 'jrs'}
                        detail="de la commande à la livraison"
                        icone="horloge"
                        accent="bg-status-pending/10 text-status-pending"
                    />
                    <StatCard
                        label="Taux de livraison"
                        value={performance.taux_livraison === null ? '—' : nombre(performance.taux_livraison)}
                        unite={performance.taux_livraison === null ? null : '%'}
                        detail="des expéditions abouties"
                        icone="coche"
                        accent="bg-status-delivered/10 text-status-delivered"
                    />
                    <StatCard
                        label="Annulations"
                        value={nombre(performance.annulees)}
                        detail={`sur ${nombre(stats.total)} expéditions`}
                        icone="aide"
                        accent="bg-status-incident/10 text-status-incident"
                    />
                </div>
            )}

            {/* Chaque rangee aligne son bas : les derniers ordres finissent
                avec les alertes, le volume commence avec la facturation. Les
                positions sont explicites pour qu'un bloc absent ne fasse pas
                glisser les autres. */}
            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <div className="flex flex-col lg:col-span-2 lg:col-start-1 lg:row-start-1">
                    <section className="flex flex-1 flex-col overflow-hidden rounded-2xl bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                            <h2 className="font-semibold text-marine">Derniers ordres</h2>
                            <Link href={route('transport-orders.index')} className="text-sm font-medium text-action hover:underline">
                                Voir tout
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Référence</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Donneur d'ordre</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Destination</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recent.map((order) => (
                                        <tr
                                            key={order.id}
                                            onClick={() => router.get(route('transport-orders.show', order.id))}
                                            className="cursor-pointer border-b border-slate-50 transition last:border-0 hover:bg-surface"
                                        >
                                            <td className="whitespace-nowrap px-4 py-3 font-mono font-semibold text-marine">
                                                <Link
                                                    href={route('transport-orders.show', order.id)}
                                                    className="transition hover:text-brand-blue"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    {order.tracking_number}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                <span className="block max-w-[11rem] truncate" title={order.client?.company_name ?? ''}>
                                                    {order.client?.company_name ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                <span className="block max-w-[16rem] truncate" title={order.delivery_address}>
                                                    {order.delivery_address}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <span className={`inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold uppercase ${STATUS[order.status]?.cls ?? 'bg-slate-100 text-slate-600'}`}>
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
                    </section>
                </div>

                <div className="flex flex-col gap-4 lg:col-start-3 lg:row-start-1">
                    {carte.length > 0 && <CarteEnCirculation carte={carte} total={carteTotal} />}
                    <Alertes alertes={alertes} />
                </div>

                {/* Sept barres a zero ne racontent rien et donnent
                    l'impression d'un graphique casse. */}
                {volume.some((mois) => mois.nombre > 0) && (
                    <div className="flex flex-col lg:col-span-2 lg:col-start-1 lg:row-start-2">
                        <VolumeMensuel volume={volume} />
                    </div>
                )}

                {facturation && (
                    <div className="flex flex-col lg:col-start-3 lg:row-start-2">
                        <Facturation facturation={facturation} />
                    </div>
                )}

                {(validations || journal) && (
                    <div className="flex flex-col gap-4 lg:col-start-3 lg:row-start-3">
                        {validations && <ValidationsEnAttente validations={validations} />}
                        {journal && <DernieresTraces journal={journal} />}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
