import BoutonRetour from '@/Components/BoutonRetour';
import CarteTrajets from '@/Components/CarteTrajets';
import ChoixLangue from '@/Components/ChoixLangue';
import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction, useVocabulaire } from '@/traduire';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const STATUTS = {
    PENDING: { cle: 'statut.en_attente', libelle: 'En attente', classe: 'bg-slate-100 text-slate-700' },
    IN_PROGRESS: { cle: 'statut.en_cours', libelle: 'En cours', classe: 'bg-brand-blue/10 text-brand-blue' },
    DELIVERED: { cle: 'statut.livre', libelle: 'Livré', classe: 'bg-status-delivered/10 text-status-delivered' },
    CANCELLED: { cle: 'statut.annule', libelle: 'Annulé', classe: 'bg-status-incident/10 text-status-incident' },
};

const PRIORITES = {
    URGENT: { cle: 'suivi.urgent', libelle: 'Urgent', classe: 'bg-status-incident/10 text-status-incident' },
    HIGH: { cle: 'suivi.prioritaire', libelle: 'Prioritaire', classe: 'bg-action/15 text-action-dark' },
};

const ETAPES_PUBLIQUES = [
    { cle: 'PENDING', libelle: ['statut.en_attente', 'En attente'], detail: ['suivi.detail_enregistree', 'Commande enregistrée.'] },
    { cle: 'IN_PROGRESS', libelle: ['statut.en_cours', 'En cours'], detail: ['suivi.detail_transit', 'Marchandise en transit.'] },
    { cle: 'DELIVERED', libelle: ['statut.livre', 'Livré'], detail: ['suivi.detail_livree', 'Livraison effectuée.'] },
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

function ChoixExpedition({ expeditions, onChoisir }) {
    const t = useTraduction();
    const [ouvert, setOuvert] = useState(false);
    const [saisie, setSaisie] = useState('');

    const resultats = useMemo(() => {
        const terme = saisie.trim().toLowerCase();

        if (terme === '') {
            return expeditions;
        }

        return expeditions.filter((e) => [e.numero, e.depart, e.arrivee, e.client, e.marchandise]
            .some((champ) => champ && champ.toLowerCase().includes(terme)));
    }, [expeditions, saisie]);

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

                onBlur={() => setTimeout(() => setOuvert(false), 150)}
                onKeyDown={(e) => e.key === 'Escape' && setOuvert(false)}
                placeholder={t('suivi.choisir', 'Choisir une expédition en cours…')}
                aria-label={t('suivi.choisir', 'Choisir une expédition en cours…')}
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
                        {resultats.length} {resultats.length > 1
                            ? t('suivi.expeditions_en_cours', 'expéditions en cours')
                            : t('suivi.expedition_en_cours', 'expédition en cours')}
                        {saisie.trim() !== '' && ' ' + t('suivi.sur', 'sur') + ' ' + expeditions.length}
                    </p>

                    {resultats.length === 0 ? (
                        <p className="px-3 py-3 text-sm text-slate-600">{t('suivi.aucune_correspond', 'Aucune expédition ne correspond.')}</p>
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
                                <span className="shrink-0 text-xs text-slate-600">{expedition.livraison}</span>
                                <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${statut.classe}`}>
                                    {t(statut.cle, statut.libelle)}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function Frise({ etapes }) {
    return (
        <ol className="flex items-start">
            {etapes.map((etape, i) => (
                <li key={etape.libelle} className="flex-1">
                    <div className="flex items-center">
                        <span className={`h-0.5 flex-1 ${i === 0 ? 'bg-transparent' : etape.fait ? 'bg-status-delivered' : 'bg-slate-200'}`} />
                        <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                            etape.fait ? 'bg-status-delivered text-white' : 'bg-slate-200 text-slate-600'
                        }`}>
                            {etape.fait ? '✓' : i + 1}
                        </span>
                        <span className={`h-0.5 flex-1 ${
                            i === etapes.length - 1 ? 'bg-transparent' : etapes[i + 1].fait ? 'bg-status-delivered' : 'bg-slate-200'
                        }`} />
                    </div>

                    <div className="mt-1.5 px-1 text-center">
                        <p className={`text-xs font-semibold ${etape.fait ? 'text-marine' : 'text-slate-600'}`}>
                            {etape.libelle}
                        </p>
                        {etape.horodatage && (
                            <p className="text-[11px] font-medium text-brand-blue">{etape.horodatage}</p>
                        )}
                        <p className="text-[11px] leading-tight text-slate-600">{etape.detail}</p>
                    </div>
                </li>
            ))}
        </ol>
    );
}

function LigneExpedition({ expedition, active, onClick }) {
    const t = useTraduction();
    const v = useVocabulaire();
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
                        {t(statut.cle, statut.libelle)}
                    </span>
                </div>
                <p className="mt-1.5 truncate font-semibold text-marine">
                    {expedition.depart} <span className="text-slate-600">→</span> {expedition.arrivee}
                </p>
                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-600">
                    <span className="truncate">{v('marchandise', expedition.marchandise)}</span>
                    {expedition.livraison && <span>· {expedition.livraison}</span>}
                    {expedition.adr && (
                        <span className="rounded bg-status-incident/10 px-1.5 font-semibold text-status-incident">ADR</span>
                    )}
                    {priorite && (
                        <span className={`rounded px-1.5 font-semibold ${priorite.classe}`}>{t(priorite.cle, priorite.libelle)}</span>
                    )}
                </div>
            </button>
        </li>
    );
}

function Reperes({ jalons = [], position = null }) {
    const t = useTraduction();

    if ((jalons ?? []).length === 0 && ! position) {
        return null;
    }

    return (
        <div className="mt-4 border-t border-slate-100 pt-3">
            <h3 className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                {t('suivi.reperes', 'Repères')}
            </h3>

            <ul className="space-y-1.5">
                {(jalons ?? []).map((j) => (
                    <li key={j.evenement} className="flex flex-wrap items-baseline gap-x-2 text-sm">
                        <span className={`h-2 w-2 shrink-0 rounded-full ${
                            j.evenement === 'DELIVERED' ? 'bg-status-delivered' : 'bg-brand-blue'
                        }`} />
                        <span className="font-semibold text-marine">{j.libelle}</span>
                        <span className="text-slate-600">{j.localite}</span>
                        <span className="ml-auto font-mono text-xs text-slate-600">{j.horodatage}</span>
                    </li>
                ))}

                {position && (
                    <li className="flex flex-wrap items-baseline gap-x-2 rounded-lg bg-brand-blue/5 px-2 py-1.5 text-sm">
                        <span className="h-2 w-2 shrink-0 animate-pulse rounded-full bg-action" />
                        <span className="font-semibold text-marine">{t('suivi.en_route', 'En route')}</span>
                        <span className="text-slate-600">
                            {position.minutes < 1
                                ? t('suivi.a_l_instant', 'à l\'instant')
                                : t('suivi.il_y_a', 'il y a :n min', { n: position.minutes })}
                        </span>
                        <span className="ml-auto font-mono text-xs text-slate-600">{position.horodatage}</span>
                    </li>
                )}
            </ul>

            {position && (
                <p className="mt-2 text-[11px] text-slate-600">
                    {t('suivi.position_aide', 'Position approximative, actualisée toutes les cinq minutes pendant le trajet. Elle cesse d\'être relevée à la livraison.')}
                </p>
            )}
        </div>
    );
}

function SuiviConnecte({ order, searched, chauffeur, etapes, jalons, position, historique, expeditions = [] }) {
    const { canPlan } = usePage().props.auth;
    const t = useTraduction();
    const v = useVocabulaire();
    const locale = useLocale();
    const [agrandie, setAgrandie] = useState(false);
    const [itineraire, setItineraire] = useState(null);
    const [peages, setPeages] = useState([]);

    const suit = position !== null && order?.status === 'IN_PROGRESS';

    useEffect(() => {
        if (! suit) return undefined;

        const minuteur = setInterval(() => {
            router.reload({ only: ['position'], preserveScroll: true, preserveState: true });
        }, 60 * 1000);

        return () => clearInterval(minuteur);
    }, [suit, order?.id]);

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
            only: ['order', 'chauffeur', 'etapes', 'jalons', 'position', 'historique', 'searched', 'expeditions'],
        },
    );

    const nombre = (valeur, unite) => valeur === null || valeur === undefined
        ? '—'
        : Number(valeur).toLocaleString(locale) + ' ' + unite;

    const duree = (minutes) => {
        if (minutes === null || minutes === undefined) {
            return null;
        }

        const heures = Math.floor(minutes / 60);

        return heures > 0 ? `${heures} h ${String(minutes % 60).padStart(2, '0')}` : `${minutes} min`;
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('suivi.titre_interne', 'Suivi d\'expédition')} />

            {}
            <h1 className="sr-only">{t('suivi.titre_interne', 'Suivi d\'expédition')}</h1>

            <div className="flex flex-col gap-3 lg:h-[calc(100vh-8rem)] lg:flex-row">
                <div className={`flex w-full flex-col gap-3 lg:w-2/3 ${agrandie ? 'lg:hidden' : ''}`}>
                    <ChoixExpedition expeditions={expeditions} onChoisir={ouvrir} />

                    {order ? (
                        <section className="min-h-0 flex-1 overflow-y-auto rounded-2xl bg-white p-4 shadow-sm">
                            <BoutonRetour onClick={() => ouvrir(null)} className="mb-2">
                                {t('suivi.toutes', 'Toutes les expéditions')}
                            </BoutonRetour>

                            <p className="font-mono text-xs text-brand-blue">{order.tracking_number}</p>
                            <h2 className="mt-0.5 text-sm font-bold leading-snug text-marine">
                                {order.pickup_address} → {order.delivery_address}
                            </h2>
                            <p className="text-xs text-slate-600">{order.client?.company_name}</p>

                            <div className="mt-3 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-3">
                                <div className="rounded-lg bg-surface px-3 py-2">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-600">{t('suivi.poids_total', 'Poids total')}</p>
                                    <p className="text-sm font-bold text-marine">{nombre(order.weight, 'kg')}</p>
                                </div>
                                <div className="rounded-lg bg-surface px-3 py-2">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-600">{t('suivi.distance_routiere', 'Distance routière')}</p>
                                    <p className="text-sm font-bold text-marine">{nombre(order.distance_km, 'km')}</p>
                                </div>
                                <div className="rounded-lg bg-surface px-3 py-2">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-600">{t('devis.marchandise', 'Marchandise')}</p>
                                    <p className="truncate text-sm font-bold text-marine">
                                        {v('marchandise', order.goods_type)}{order.is_hazardous && ' · ADR'}
                                    </p>
                                </div>
                            </div>

                            <h3 className="mb-2 mt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                                {t('suivi.etat', 'État de livraison')}
                            </h3>

                            {order.status === 'CANCELLED' ? (
                                <div className="rounded-lg bg-status-incident/10 px-3 py-2 text-sm text-status-incident">
                                    <p className="font-semibold">{t('suivi.expedition_annulee', 'Expédition annulée')}</p>
                                </div>
                            ) : (
                                <Frise etapes={etapes} />
                            )}

                            <Reperes jalons={jalons} position={position} />

                            <div className="mt-4 border-t border-slate-100 pt-3">
                                <h3 className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                                    {t('suivi.prise_en_charge', 'Prise en charge')}
                                </h3>

                                {! chauffeur && ! order.vehicle ? (
                                    <p className="text-xs text-slate-600">{t('suivi.aucun_chauffeur', 'Aucun chauffeur affecté pour l\'instant.')}</p>
                                ) : (
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        <div className="flex items-start gap-2.5 rounded-lg bg-surface px-3 py-2">
                                            {chauffeur ? (
                                                <>
                                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-blue/10 text-xs font-bold text-brand-blue">
                                                        {chauffeur.nom.split(' ').map((m) => m[0]).slice(0, 2).join('')}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-semibold leading-tight text-marine">
                                                            {chauffeur.nom}
                                                        </p>
                                                        <p className="text-[11px] leading-tight text-slate-600">
                                                            {[chauffeur.permis && t('suivi.permis', 'Permis') + ' ' + chauffeur.permis, chauffeur.adr && 'ADR']
                                                                .filter(Boolean).join(' · ') || t('suivi.chauffeur', 'Chauffeur')}
                                                        </p>
                                                        {chauffeur.numero_permis && (
                                                            <p className="truncate font-mono text-[11px] leading-tight text-slate-600">
                                                                {chauffeur.numero_permis}
                                                            </p>
                                                        )}
                                                        {chauffeur.trajets > 0 && (
                                                            <p className="text-[11px] leading-tight text-slate-600">
                                                                {chauffeur.trajets} {chauffeur.trajets > 1
                                                                    ? t('suivi.trajets_confies', 'trajets confiés')
                                                                    : t('suivi.trajet_confie', 'trajet confié')}
                                                            </p>
                                                        )}
                                                    </div>
                                                </>
                                            ) : (
                                                <p className="text-xs text-slate-600">{t('suivi.chauffeur_non_affecte', 'Chauffeur non affecté.')}</p>
                                            )}
                                        </div>

                                        <div className="flex flex-col items-center justify-center rounded-lg bg-surface px-3 py-2 text-center">
                                            {order.vehicle ? (
                                                <>
                                                    <p className="truncate text-sm font-semibold leading-tight text-marine">
                                                        {[order.vehicle.brand, order.vehicle.model].filter(Boolean).join(' ')}
                                                    </p>
                                                    <p className="font-mono text-[11px] leading-tight text-marine">
                                                        {order.vehicle.registration}
                                                    </p>
                                                    {}
                                                    {[
                                                        [order.vehicle.vehicle_type, order.vehicle.capacity_tonnes && Math.round(order.vehicle.capacity_tonnes) + ' t'],
                                                        [order.vehicle.euro_standard, order.vehicle.fuel_type],
                                                    ].map((ligne, i) => {
                                                        const texte = ligne.filter(Boolean).join(' · ');

                                                        return texte === '' ? null : (
                                                            <p key={i} className="truncate text-[11px] leading-tight text-slate-600">
                                                                {texte}
                                                            </p>
                                                        );
                                                    })}
                                                </>
                                            ) : (
                                                <p className="text-xs text-slate-600">{t('suivi.vehicule_non_affecte', 'Véhicule non affecté.')}</p>
                                            )}
                                        </div>

                                        {chauffeur?.telephone ? (
                                            <a
                                                href={'tel:' + chauffeur.telephone.replace(/\s/g, '')}
                                                className="flex flex-col items-center justify-center rounded-lg bg-surface px-3 py-2 transition hover:bg-slate-200"
                                            >
                                                <span className="text-[11px] uppercase tracking-wide text-slate-600">
                                                    {t('suivi.joindre', 'Joindre le chauffeur')}
                                                </span>
                                                <span className="text-sm font-bold text-marine">{chauffeur.telephone}</span>
                                            </a>
                                        ) : (
                                            <div className="flex items-center justify-center rounded-lg bg-surface px-3 py-2">
                                                <span className="text-xs text-slate-600">{t('suivi.pas_numero', 'Pas de numéro')}</span>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            {peages.length > 0 && (
                                <div className="mt-4 border-t border-slate-100 pt-3">
                                    <h3 className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                                        {t('suivi.peages_titre', 'Péages sur l\'itinéraire')}
                                    </h3>
                                    <ol className="flex flex-wrap gap-1.5">
                                        {peages.map((peage, i) => (
                                            <li
                                                key={i}
                                                title={(peage.portique ? t('suivi.portique', 'Portique de péage') : t('suivi.barriere', 'Barrière de péage')) + (peage.route ? ' · ' + peage.route : '')}
                                                className="inline-flex items-center gap-1.5 rounded-full bg-surface py-1 pl-1 pr-2.5"
                                            >
                                                <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-action/20 text-action-dark">
                                                    <Icone nom="peage" className="h-3.5 w-3.5" />
                                                </span>
                                                <span className="text-xs font-semibold text-marine">{peage.nom}</span>
                                                {peage.route && <span className="text-[11px] text-slate-600">{peage.route}</span>}
                                            </li>
                                        ))}
                                    </ol>
                                </div>
                            )}

                            {historique && historique.length > 0 && (
                                <div className="mt-4 border-t border-slate-100 pt-3">
                                    <h3 className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                                        {t('suivi.historique', 'Historique horodaté')}
                                    </h3>
                                    <ol className="space-y-1.5">
                                        {historique.map((ligne, i) => (
                                            <li key={i} className="border-l-2 border-slate-200 pl-2.5">
                                                <p className="text-[11px] text-slate-600">{ligne.horodatage}</p>
                                                <p className="text-xs text-marine">{ligne.description}</p>
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
                                    {canPlan
                                        ? t('suivi.expeditions_titre', 'Expéditions en cours')
                                        : t('suivi.mes_expeditions', 'Mes expéditions en cours')}
                                </h2>
                                <span className="text-xs text-slate-600">{expeditions.length}</span>
                            </div>

                            {searched && ! order && (
                                <p className="mb-3 rounded-lg bg-status-incident/10 p-3 text-sm text-status-incident">
                                    {t('suivi.numero_introuvable', 'Aucune expédition ne porte ce numéro parmi celles que vous pouvez consulter.')}
                                </p>
                            )}

                            {expeditions.length === 0 ? (
                                <p className="py-8 text-center text-sm text-slate-600">
                                    {t('suivi.aucune_circulation', 'Aucune expédition en circulation pour le moment.')}
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

                {}
                <div className={`relative isolate h-[420px] overflow-hidden rounded-2xl shadow-sm lg:h-auto ${agrandie ? 'lg:w-full' : 'lg:w-1/3'}`}>
                    <CarteTrajets
                        trajets={expeditions.map((expedition) => expedition.id === order?.id
                            ? { ...expedition, trace: itineraire?.geometrie }
                            : expedition)}
                        selection={order?.id ?? null}
                        jalons={jalons ?? []}
                        position={position}
                        onSelection={(id) => {
                            const cible = expeditions.find((e) => e.id === id);

                            if (cible) {
                                ouvrir(cible.numero);
                            }
                        }}
                        peages={peages}
                        className="h-full w-full"
                    />

                    <button
                        type="button"
                        onClick={() => setAgrandie(! agrandie)}
                        title={agrandie ? t('suivi.reduire', 'Réduire la carte') : t('suivi.agrandir', 'Agrandir la carte')}
                        aria-label={agrandie ? t('suivi.reduire', 'Réduire la carte') : t('suivi.agrandir', 'Agrandir la carte')}
                        aria-pressed={agrandie}
                        className="absolute right-3 top-3 z-[1100] rounded-lg bg-white p-2 text-marine shadow-md transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marine"
                    >
                        <Icone nom={agrandie ? 'reduire' : 'agrandir'} className="h-5 w-5" />
                    </button>

                    {order && (
                        <div className="pointer-events-none absolute inset-x-3 bottom-3 z-[1100] rounded-xl bg-white/95 px-3 py-2 shadow-lg backdrop-blur">
                            {itineraire === null ? (
                                <p className="text-xs text-slate-600">{t('suivi.calcul_itineraire', 'Calcul de l\'itinéraire…')}</p>
                            ) : itineraire.direct ? (
                                <p className="text-xs text-slate-600">
                                    {t('suivi.itineraire_indispo', 'Itinéraire indisponible — liaison directe entre les deux points.')}
                                </p>
                            ) : (
                                <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                                    <span className="text-sm font-bold text-marine">
                                        {nombre(itineraire.distance_km, 'km')}
                                    </span>
                                    <span className="text-sm font-semibold text-brand-blue">
                                        {duree(itineraire.duree_min)}
                                    </span>
                                    <span className="text-xs text-slate-600">{t('suivi.par_route', 'par la route')}</span>
                                    {peages.length > 0 && (
                                        <span className="ml-auto inline-flex items-center gap-1 rounded-full bg-action/15 px-2 py-0.5 text-xs font-semibold text-action-dark">
                                            <Icone nom="peage" className="h-3.5 w-3.5" />
                                            {peages.length} {peages.length > 1 ? t('suivi.peages', 'péages') : t('suivi.peage', 'péage')}
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
    const t = useTraduction();
    const { data, setData, get, processing } = useForm({ tracking_number: '', code: '' });

    const chercher = (e) => {
        e.preventDefault();
        get(route('tracking.show'), { preserveScroll: true });
    };

    const courant = ETAPES_PUBLIQUES.findIndex((e) => e.cle === order?.status);

    return (
        <>
            <Head title={t('suivi.envoi_titre', 'Suivi d\'envoi')} />
            <div className="flex min-h-screen flex-col bg-surface">
                <header className="bg-marine">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
                        <img src="/images/logo-blanc.png" alt="NBLogiTrack" className="h-8 w-auto" />
                        <div className="flex items-center gap-4">
                            <ChoixLangue sombre />
                            <Link href={route('login')} className="text-sm font-medium text-slate-300 transition hover:text-white">
                                {t('suivi.espace_client', 'Espace client')}
                            </Link>
                        </div>
                    </div>
                </header>

                <div className="mx-auto w-full max-w-3xl flex-1 px-4 py-10">
                    <h1 className="text-2xl font-bold text-marine">{t('suivi.envoi_titre', 'Suivi d\'envoi')}</h1>
                    <p className="mb-6 mt-1 text-slate-600">
                        {t('suivi.entrez', 'Entrez votre numéro de suivi et le code reçu par e-mail.')}
                    </p>

                    <form onSubmit={chercher} className="flex flex-col gap-3 sm:flex-row">
                        <input
                            value={data.tracking_number}
                            onChange={(e) => setData('tracking_number', e.target.value)}
                            placeholder={t('suivi.numero_ph', 'Numéro de suivi (TRK-…)')}
                            className="w-full rounded-lg border-slate-300 shadow-sm focus:border-marine focus:ring-marine"
                            required
                        />
                        <input
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            placeholder={t('suivi.code', 'Code')}
                            className="w-full rounded-lg border-slate-300 shadow-sm focus:border-marine focus:ring-marine sm:w-48"
                            required
                        />
                        <button
                            disabled={processing}
                            className="rounded-lg bg-action px-6 py-2 font-semibold text-marine-deep transition hover:bg-action-dark disabled:opacity-50"
                        >
                            {t('suivi.suivre', 'Suivre')}
                        </button>
                    </form>

                    {order && (
                        <div className="mt-8 rounded-2xl bg-white p-6 shadow-sm">
                            <div className="mb-6 border-b border-slate-100 pb-4">
                                <div className="font-mono text-sm text-slate-600">{order.tracking_number}</div>
                                <div className="text-lg font-bold text-marine">{order.client?.company_name ?? t('suivi.envoi', 'Envoi')}</div>
                            </div>
                            <div className="grid gap-8 md:grid-cols-2">
                                <div>
                                    <h3 className="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        {t('suivi.etat', 'État de livraison')}
                                    </h3>
                                    {order.status === 'CANCELLED' ? (
                                        <div className="rounded-lg bg-status-incident/10 p-4 text-status-incident">
                                            <p className="font-semibold">{t('suivi.envoi_annule', 'Envoi annulé')}</p>
                                        </div>
                                    ) : (
                                        <ol>
                                            {ETAPES_PUBLIQUES.map((etape, i) => (
                                                <Jalon
                                                    key={etape.cle}
                                                    libelle={t(...etape.libelle)}
                                                    detail={t(...etape.detail)}
                                                    fait={i < courant || order.status === 'DELIVERED'}
                                                    actif={i === courant && order.status !== 'DELIVERED'}
                                                    dernier={i === ETAPES_PUBLIQUES.length - 1}
                                                />
                                            ))}
                                        </ol>
                                    )}
                                </div>
                                <dl className="space-y-3 text-sm">
                                    <div><dt className="text-slate-600">{t('suivi.depart', 'Départ')}</dt><dd className="font-medium text-marine">{order.pickup_address}</dd></div>
                                    <div><dt className="text-slate-600">{t('suivi.destination', 'Destination')}</dt><dd className="font-medium text-marine">{order.delivery_address}</dd></div>
                                    <div><dt className="text-slate-600">{t('suivi.livraison_prevue', 'Livraison prévue')}</dt><dd className="font-medium text-marine">{order.requested_delivery_date?.slice(0, 10) ?? '—'}</dd></div>
                                </dl>
                            </div>
                        </div>
                    )}

                    {searched && ! order && (
                        <div className="mt-8 rounded-lg bg-status-incident/10 p-4 text-status-incident">
                            {t('suivi.introuvable_code', 'Aucun envoi trouvé. Vérifiez le numéro de suivi et le code.')}
                        </div>
                    )}
                </div>

                <footer className="border-t border-slate-200 py-6 text-center text-xs text-slate-600">
                    {t('suivi.pied', 'NBLogiTrack Belgium — suivi d\'expédition')}
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
