import BoutonRetour from '@/Components/BoutonRetour';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction, useVocabulaire } from '@/traduire';
import { Head, Link } from '@inertiajs/react';

const ETAPES = [
    { cle: 'PENDING', libelle: ['statut.en_attente', 'En attente'], detail: ['suivi.detail_enregistree', 'Commande enregistrée.'] },
    { cle: 'IN_PROGRESS', libelle: ['statut.en_cours', 'En cours'], detail: ['suivi.detail_transit', 'Marchandise en transit.'] },
    { cle: 'DELIVERED', libelle: ['statut.livre', 'Livré'], detail: ['suivi.detail_livree', 'Livraison effectuée.'] },
];

const PRIORITE = {
    LOW: ['priorite.basse', 'Basse'],
    NORMAL: ['priorite.normale', 'Normale'],
    HIGH: ['priorite.haute', 'Haute'],
    URGENT: ['priorite.urgente', 'Urgente'],
};

function Progression({ statut }) {
    const t = useTraduction();

    if (statut === 'CANCELLED') {
        return (
            <div className="rounded-lg bg-status-incident/10 p-4 text-status-incident">
                <p className="font-semibold">{t('suivi.expedition_annulee', 'Expédition annulée')}</p>
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
                            <div className="font-semibold">{t(...etape.libelle)}</div>
                            <div className="text-sm text-slate-600">{t(...etape.detail)}</div>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

export default function Show({ order, chauffeur, facture = null }) {
    const t = useTraduction();
    const v = useVocabulaire();
    const locale = useLocale();

    const nombre = (valeur, unite, decimales = 0) => valeur === null || valeur === undefined
        ? '—'
        : Number(valeur).toLocaleString(locale, { minimumFractionDigits: decimales, maximumFractionDigits: decimales }) + ' ' + unite;

    const date = (valeur, avecHeure = false) => {
        if (! valeur) {
            return '—';
        }

        const options = { day: '2-digit', month: '2-digit', year: 'numeric' };

        if (avecHeure) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }

        return new Date(valeur).toLocaleString(locale, options);
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
                            {t('nav.ordres', 'Ordres de transport')}
                        </BoutonRetour>
                        <h1 className="mt-1 text-2xl font-bold text-marine">{order.tracking_number}</h1>
                        <p className="text-sm text-slate-600">{order.client?.company_name}</p>
                    </div>
                    <span className="rounded-full bg-marine px-4 py-1.5 text-sm font-semibold text-white">
                        {PRIORITE[order.priority] ? t(...PRIORITE[order.priority]) : order.priority}
                    </span>
                </div>
            }
        >
            <Head title={t('ordres.expedition', 'Expédition') + ' ' + order.tracking_number} />

            <div className="grid gap-4 lg:grid-cols-3">
                {carte(t('suivi.etat', 'État de livraison'), (
                    <>
                        <Progression statut={order.status} />
                        <dl className="mt-4 border-t border-slate-100 pt-3">
                            {ligne(t('ordres.livraison_souhaitee', 'Livraison souhaitée'), date(order.requested_delivery_date))}
                            {ligne(t('ordres.livraison_effective', 'Livraison effective'), date(order.actual_delivery_date))}
                        </dl>
                    </>
                ))}

                {carte(t('devis.trajet', 'Trajet'), (
                    <dl>
                        {ligne(t('suivi.depart', 'Départ'), order.pickup_address)}
                        {ligne(t('suivi.destination', 'Destination'), order.delivery_address)}
                        {ligne(t('suivi.distance_routiere', 'Distance routière'), nombre(order.distance_km, 'km'))}
                        {ligne(t('ordres.chargement', 'Chargement'), date(order.pickup_date, true))}
                    </dl>
                ))}

                {carte(t('devis.marchandise', 'Marchandise'), (
                    <dl>
                        {ligne(t('ordres.nature', 'Nature'), v('marchandise', order.goods_type))}
                        {ligne(t('commande.poids', 'Poids'), nombre(order.weight, 'kg'))}
                        {ligne(t('ordres.dangereuse', 'Matière dangereuse'), order.is_hazardous
                            ? t('ordres.oui_adr', 'Oui — ADR')
                            : t('ordres.non', 'Non'))}
                        {ligne(t('ordres.formule', 'Formule'), order.tariff_grid
                            ? order.tariff_grid.label + ' — ' + order.tariff_grid.delivery_days + ' ' + t('ordres.j', 'j')
                            : null)}
                        {ligne(t('commande.estimation', 'Prix estimé'), nombre(order.estimated_cost, '€', 2))}
                    </dl>
                ))}

                {carte(t('ordres.affectation', 'Affectation'), (
                    <dl>
                        {ligne(t('ordres.vehicule', 'Véhicule'), order.vehicle
                            ? order.vehicle.registration + ' — ' + order.vehicle.brand + ' ' + order.vehicle.model
                            : t('ordres.pas_affecte', 'Pas encore affecté'))}
                        {ligne(t('ordres.capacite', 'Capacité'), order.vehicle ? nombre(order.vehicle.capacity_tonnes, 't', 1) : null)}
                        {ligne(t('suivi.chauffeur', 'Chauffeur'), chauffeur ?? t('ordres.pas_affecte', 'Pas encore affecté'))}
                        {ligne(t('ordres.affecte_le', 'Affecté le'), date(order.assigned_at, true))}
                    </dl>
                ))}

                {carte(t('ordres.suivi_client', 'Suivi client'), (
                    <dl>
                        {ligne(t('suivi.reference', 'Numéro de suivi'), <span className="font-mono">{order.tracking_number}</span>)}
                        {ligne(t('ordres.code_acces', 'Code d\'accès'), <span className="font-mono tracking-widest">{order.tracking_code}</span>)}
                        {ligne(t('ordres.creee_le', 'Créée le'), date(order.created_date))}
                    </dl>
                ))}

                {carte(t('nav.facturation', 'Facturation'), facture ? (
                    <dl>
                        {ligne(t('facture.titre', 'Facture'), (
                            <Link
                                href={route('invoices.show', facture.id)}
                                className="font-mono text-brand-blue transition hover:text-marine"
                            >
                                {facture.reference}
                            </Link>
                        ))}
                        {ligne(t('ordres.montant_ttc', 'Montant TTC'), nombre(facture.ttc, '€', 2))}
                        {facture.payee_le
                            ? ligne(t('ordres.payee_le', 'Payée le'), facture.payee_le)
                            : ligne(t('facture.echeance', 'Échéance'), facture.echeance)}
                        {ligne(t('ordres.etat', 'État'), facture.etat)}
                    </dl>
                ) : (
                    <p className="text-sm text-slate-600">
                        {order.status === 'DELIVERED'
                            ? t('ordres.fact_apres', 'Sera portée sur la facture du mois de livraison, émise le mois suivant.')
                            : order.status === 'CANCELLED'
                                ? t('ordres.fact_annulee', 'Expédition annulée — rien à facturer.')
                                : t('ordres.fact_livraison', 'Facturée après livraison.')}
                    </p>
                ))}

                {carte(t('ordres.consignes', 'Consignes particulières'), (
                    <p className="text-sm text-slate-600">
                        {order.special_instructions || t('ordres.aucune_consigne', 'Aucune consigne particulière.')}
                    </p>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
