import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

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
    const maximum = Math.max(...volume.map((m) => m.nombre), 1);
    const total = volume.reduce((somme, m) => somme + m.nombre, 0);
    const pic = volume.reduce((a, b) => (b.nombre > a.nombre ? b : a), volume[0]);

    return (
        <section className="rounded-2xl bg-white p-6 shadow-sm">
            <h2 className="font-semibold text-marine">Volume mensuel</h2>
            <p className="text-sm text-slate-600">Expéditions enregistrées sur les sept derniers mois</p>

            <div className="mt-6 flex h-44 items-end gap-2">
                {volume.map((mois) => (
                    <div key={mois.cle} className="flex flex-1 flex-col items-center gap-1">
                        <span className="text-xs font-semibold text-marine">{mois.nombre}</span>
                        <div
                            className={`w-full rounded-t transition-all ${
                                mois.cle === pic.cle ? 'bg-action' : 'bg-brand-blue/15'
                            }`}
                            style={{ height: `${Math.max((mois.nombre / maximum) * 100, 2)}%` }}
                            title={`${mois.nombre} expédition${mois.nombre > 1 ? 's' : ''} · ${mois.tonnes} t`}
                        />
                        <span className="text-xs capitalize text-slate-600">{mois.libelle}</span>
                    </div>
                ))}
            </div>

            <div className="mt-4 flex flex-wrap justify-between gap-4 border-t border-slate-100 pt-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-slate-600">Sur la période</p>
                    <p className="font-bold text-marine">{total} expéditions</p>
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

function RepartitionMarchandises({ marchandises }) {
    return (
        <section className="rounded-2xl bg-white p-6 shadow-sm">
            <h2 className="font-semibold text-marine">Répartition des marchandises</h2>
            <p className="mb-4 text-sm text-slate-600">Les cinq premières natures expédiées</p>

            {marchandises.length === 0 ? (
                <p className="text-sm text-slate-600">Aucune expédition enregistrée.</p>
            ) : (
                <ul className="space-y-3">
                    {marchandises.map((ligne) => (
                        <li key={ligne.libelle}>
                            <div className="flex items-baseline justify-between gap-3 text-sm">
                                <span className="truncate text-marine">{ligne.libelle}</span>
                                <span className="shrink-0 font-bold text-marine">{ligne.part} %</span>
                            </div>
                            <div className="mt-1 h-2 overflow-hidden rounded-full bg-surface">
                                <div className="h-full rounded-full bg-brand-blue" style={{ width: `${ligne.part}%` }} />
                            </div>
                        </li>
                    ))}
                </ul>
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
    marchandises = [],
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

            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    {/* Sept barres a zero ne racontent rien et donnent
                        l'impression d'un graphique casse. */}
                    {volume.some((mois) => mois.nombre > 0) && <VolumeMensuel volume={volume} />}

                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
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
                                        <th scope="col" className="px-6 py-3 font-semibold">Référence</th>
                                        <th scope="col" className="px-6 py-3 font-semibold">Donneur d'ordre</th>
                                        <th scope="col" className="px-6 py-3 font-semibold">Destination</th>
                                        <th scope="col" className="px-6 py-3 font-semibold">Statut</th>
                                    </tr>
                                </thead>
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
                    </section>
                </div>

                <div className="space-y-4">
                    {validations && <ValidationsEnAttente validations={validations} />}
                    {marchandises.length > 0 && <RepartitionMarchandises marchandises={marchandises} />}
                    {journal && <DernieresTraces journal={journal} />}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
