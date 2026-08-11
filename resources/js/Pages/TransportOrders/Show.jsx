import BoutonRetour from '@/Components/BoutonRetour';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const ETAPES = [
    { cle: 'PENDING', libelle: 'En attente', detail: 'Commande enregistrée.' },
    { cle: 'IN_PROGRESS', libelle: 'En cours', detail: 'Marchandise en transit.' },
    { cle: 'DELIVERED', libelle: 'Livré', detail: 'Livraison effectuée.' },
];

const PRIORITE = {
    LOW: 'Basse',
    NORMAL: 'Normale',
    HIGH: 'Haute',
    URGENT: 'Urgente',
};

function Progression({ statut }) {
    if (statut === 'CANCELLED') {
        return (
            <div className="rounded-lg bg-status-incident/10 p-4 text-status-incident">
                <p className="font-semibold">Expédition annulée</p>
            </div>
        );
    }

    const courant = ETAPES.findIndex((e) => e.cle === statut);

    return (
        <ol className="space-y-1">
            {ETAPES.map((etape, i) => {
                const fait = i < courant || statut === 'DELIVERED';
                const actif = i === courant && statut !== 'DELIVERED';

                return (
                    <li key={etape.cle} className="flex gap-4">
                        <div className="flex flex-col items-center">
                            <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${
                                fait ? 'bg-status-delivered text-white'
                                    : actif ? 'bg-brand-blue text-white'
                                        : 'bg-slate-200 text-slate-600'
                            }`}>
                                {fait ? '✓' : i + 1}
                            </span>
                            {i < ETAPES.length - 1 && (
                                <span className={`my-1 h-10 w-0.5 ${fait ? 'bg-status-delivered' : 'bg-slate-200'}`} />
                            )}
                        </div>
                        <div className={`pt-1 ${actif ? 'text-marine' : 'text-slate-600'}`}>
                            <div className="font-semibold">{etape.libelle}</div>
                            <div className="text-sm text-slate-600">{etape.detail}</div>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

export default function Show({ order, chauffeur }) {
    const nombre = (valeur, unite, decimales = 0) => valeur === null || valeur === undefined
        ? '—'
        : Number(valeur).toLocaleString('fr-FR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales }) + ' ' + unite;

    const date = (valeur, avecHeure = false) => {
        if (! valeur) {
            return '—';
        }

        const options = { day: '2-digit', month: '2-digit', year: 'numeric' };

        if (avecHeure) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }

        return new Date(valeur).toLocaleString('fr-FR', options);
    };

    const ligne = (libelle, valeur) => (
        <div className="flex justify-between gap-4 border-b border-slate-100 py-2 last:border-0">
            <dt className="text-sm text-slate-600">{libelle}</dt>
            <dd className="text-right text-sm font-medium text-marine">{valeur || '—'}</dd>
        </div>
    );

    const carte = (titre, contenu) => (
        <section className="rounded-2xl bg-white p-5 shadow-sm">
            <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">{titre}</h2>
            {contenu}
        </section>
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <BoutonRetour href={route('transport-orders.index')}>
                            Ordres de transport
                        </BoutonRetour>
                        <h1 className="mt-1 text-2xl font-bold text-marine">{order.tracking_number}</h1>
                        <p className="text-sm text-slate-600">{order.client?.company_name}</p>
                    </div>
                    <span className="rounded-full bg-marine px-4 py-1.5 text-sm font-semibold text-white">
                        {PRIORITE[order.priority] ?? order.priority}
                    </span>
                </div>
            }
        >
            <Head title={'Expédition ' + order.tracking_number} />

            <div className="grid gap-4 lg:grid-cols-3">
                {carte('État de livraison', (
                    <>
                        <Progression statut={order.status} />
                        <dl className="mt-4 border-t border-slate-100 pt-3">
                            {ligne('Livraison souhaitée', date(order.requested_delivery_date))}
                            {ligne('Livraison effective', date(order.actual_delivery_date))}
                        </dl>
                    </>
                ))}

                {carte('Trajet', (
                    <dl>
                        {ligne('Départ', order.pickup_address)}
                        {ligne('Destination', order.delivery_address)}
                        {ligne('Distance routière', nombre(order.distance_km, 'km'))}
                        {ligne('Chargement', date(order.pickup_date, true))}
                    </dl>
                ))}

                {carte('Marchandise', (
                    <dl>
                        {ligne('Nature', order.goods_type)}
                        {ligne('Poids', nombre(order.weight, 'kg'))}
                        {ligne('Matière dangereuse', order.is_hazardous ? 'Oui — ADR' : 'Non')}
                        {ligne('Formule', order.tariff_grid
                            ? order.tariff_grid.label + ' — ' + order.tariff_grid.delivery_days + ' j'
                            : null)}
                        {ligne('Prix estimé', nombre(order.estimated_cost, '€', 2))}
                    </dl>
                ))}

                {carte('Affectation', (
                    <dl>
                        {ligne('Véhicule', order.vehicle
                            ? order.vehicle.registration + ' — ' + order.vehicle.brand + ' ' + order.vehicle.model
                            : 'Pas encore affecté')}
                        {ligne('Capacité', order.vehicle ? nombre(order.vehicle.capacity_tonnes, 't', 1) : null)}
                        {ligne('Chauffeur', chauffeur ?? 'Pas encore affecté')}
                        {ligne('Affecté le', date(order.assigned_at, true))}
                    </dl>
                ))}

                {carte('Suivi client', (
                    <dl>
                        {ligne('Numéro de suivi', <span className="font-mono">{order.tracking_number}</span>)}
                        {ligne('Code d\'accès', <span className="font-mono tracking-widest">{order.tracking_code}</span>)}
                        {ligne('Créée le', date(order.created_date))}
                    </dl>
                ))}

                {carte('Consignes particulières', (
                    <p className="text-sm text-slate-600">
                        {order.special_instructions || 'Aucune consigne particulière.'}
                    </p>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
