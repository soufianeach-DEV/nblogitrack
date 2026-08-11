import BoutonRetour from '@/Components/BoutonRetour';
import ChauffeurLayout from '@/Layouts/ChauffeurLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const STATUTS = {
    IN_PROGRESS: { libelle: 'En cours', pastille: 'bg-action/15 text-action-dark', barre: 'border-l-action' },
    PENDING: { libelle: 'À venir', pastille: 'bg-slate-100 text-slate-700', barre: 'border-l-slate-300' },
    DELIVERED: { libelle: 'Livrée', pastille: 'bg-status-delivered/10 text-status-delivered', barre: 'border-l-status-delivered' },
    CANCELLED: { libelle: 'Annulée', pastille: 'bg-status-incident/10 text-status-incident', barre: 'border-l-status-incident' },
};

function Etape({ intitule, heure, lieu }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                {intitule}{heure && ' • ' + heure}
            </p>
            <p className="font-bold text-marine">{lieu}</p>
        </div>
    );
}

function CarteMission({ mission, active, onClick }) {
    const statut = STATUTS[mission.statut] ?? STATUTS.PENDING;

    return (
        <li>
            <button
                type="button"
                onClick={onClick}
                aria-current={active ? 'true' : undefined}
                className={`w-full rounded-xl border border-l-4 bg-white p-4 text-left shadow-sm transition ${statut.barre} ${
                    active ? 'border-y-brand-blue border-r-brand-blue ring-1 ring-brand-blue' : 'border-y-slate-200 border-r-slate-200 hover:shadow-md'
                }`}
            >
                <div className="flex items-center justify-between gap-2">
                    <span className={`rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${statut.pastille}`}>
                        {statut.libelle}
                    </span>
                    <span className="font-mono text-xs text-slate-600">{mission.numero}</span>
                </div>

                <div className="mt-3 space-y-2">
                    <Etape intitule="Chargement" heure={mission.heure_enlevement} lieu={mission.enlevement} />
                    <Etape intitule="Livraison" heure={mission.date_livraison} lieu={mission.livraison} />
                </div>

                <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                    <span className="text-sm text-slate-600">
                        {mission.marchandise}
                        {mission.adr && (
                            <span className="ml-2 rounded bg-status-incident/10 px-1.5 py-0.5 text-[11px] font-bold text-status-incident">
                                ADR
                            </span>
                        )}
                    </span>
                    <span className="text-slate-600">›</span>
                </div>
            </button>
        </li>
    );
}

/**
 * Le bouton demande deux appuis. Au volant, un appui involontaire
 * declarerait une livraison qui n'a pas eu lieu, et rien dans l'application
 * ne permet a un chauffeur de revenir en arriere.
 */
function BoutonAvancement({ mission }) {
    const [arme, setArme] = useState(false);
    const { patch, processing } = useForm({ statut: mission.action.statut });

    const envoyer = () => {
        if (! arme) {
            setArme(true);

            return;
        }

        patch(route('missions.status', mission.id), {
            preserveScroll: true,
            onFinish: () => setArme(false),
        });
    };

    return (
        <div className="sticky bottom-20 mt-4 lg:bottom-0">
            <button
                type="button"
                onClick={envoyer}
                disabled={processing}
                className={`w-full rounded-xl px-6 py-4 text-base font-bold shadow-lg transition disabled:opacity-60 ${
                    arme
                        ? 'bg-status-delivered text-white hover:bg-green-800'
                        : 'bg-action text-marine-deep hover:bg-action-dark'
                }`}
            >
                {processing ? 'Enregistrement…' : arme ? 'Appuyez à nouveau pour confirmer' : mission.action.libelle}
            </button>
            {arme && ! processing && (
                <button
                    type="button"
                    onClick={() => setArme(false)}
                    className="mt-2 w-full py-1 text-sm font-semibold text-slate-600 transition hover:text-marine"
                >
                    Annuler
                </button>
            )}
        </div>
    );
}

function Fiche({ mission, onRetour }) {
    const statut = STATUTS[mission.statut] ?? STATUTS.PENDING;

    const nombre = (valeur, unite) => valeur === null || valeur === undefined
        ? null
        : Number(valeur).toLocaleString('fr-FR') + ' ' + unite;

    const mesures = [
        ['Poids', nombre(mission.poids, 'kg')],
        ['Volume', nombre(mission.volume, 'm³')],
        ['Distance', nombre(mission.distance_km, 'km')],
    ].filter(([, valeur]) => valeur !== null);

    return (
        <div>
            <BoutonRetour onClick={onRetour} className="mb-3 lg:hidden">
                Mes missions
            </BoutonRetour>

            <div className="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                <div className="flex items-center justify-between gap-2">
                    <span className={`rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${statut.pastille}`}>
                        {statut.libelle}
                    </span>
                    <span className="font-mono text-xs text-slate-600">{mission.numero}</span>
                </div>

                <ol className="mt-4 space-y-4">
                    <li className="flex gap-3">
                        <span className="mt-1 flex h-3 w-3 shrink-0 rounded-full border-2 border-marine bg-white" />
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                Enlèvement{mission.enlevement_prevu && ' • ' + mission.enlevement_prevu}
                            </p>
                            <p className="font-bold leading-snug text-marine">{mission.adresse_enlevement}</p>
                        </div>
                    </li>
                    <li className="flex gap-3">
                        <span className="mt-1 h-3 w-3 shrink-0 rounded-sm bg-marine" />
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                Livraison{mission.livraison_prevue && ' • ' + mission.livraison_prevue}
                            </p>
                            <p className="font-bold leading-snug text-marine">{mission.adresse_livraison}</p>
                        </div>
                    </li>
                </ol>

                <div className="mt-4 border-t border-slate-100 pt-4">
                    <p className="text-sm font-semibold text-marine">
                        {mission.marchandise}
                        {mission.adr && (
                            <span className="ml-2 rounded bg-status-incident/10 px-1.5 py-0.5 text-[11px] font-bold text-status-incident">
                                ADR
                            </span>
                        )}
                    </p>
                    {mesures.length > 0 && (
                        <dl className="mt-2 flex flex-wrap gap-x-6 gap-y-1">
                            {mesures.map(([intitule, valeur]) => (
                                <div key={intitule} className="flex items-baseline gap-1.5">
                                    <dt className="text-xs text-slate-600">{intitule}</dt>
                                    <dd className="text-sm font-bold text-marine">{valeur}</dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </div>

                {mission.consignes && (
                    <div className="mt-4 rounded-lg bg-action/10 p-3">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-action-dark">
                            Consignes particulières
                        </p>
                        <p className="mt-0.5 text-sm text-marine">{mission.consignes}</p>
                    </div>
                )}

                <div className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Véhicule</p>
                        {mission.vehicule ? (
                            <p className="text-sm text-marine">
                                <span className="font-mono font-semibold">{mission.vehicule.immatriculation}</span>
                                {' — '}{mission.vehicule.modele}
                                {mission.vehicule.type && ' · ' + mission.vehicule.type}
                            </p>
                        ) : (
                            <p className="text-sm text-slate-600">Non affecté</p>
                        )}
                    </div>
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Donneur d'ordre</p>
                        <p className="text-sm text-marine">{mission.client ?? '—'}</p>
                    </div>
                </div>

                {mission.livree_le && (
                    <p className="mt-4 rounded-lg bg-status-delivered/10 px-3 py-2 text-sm font-semibold text-status-delivered">
                        Livrée le {mission.livree_le}
                    </p>
                )}
            </div>

            {mission.action && <BoutonAvancement mission={mission} />}
        </div>
    );
}

export default function Missions({ missions = [], mission = null, introuvable = false }) {
    const actives = missions.filter((m) => m.statut === 'IN_PROGRESS' || m.statut === 'PENDING').length;

    const aujourdhui = new Date().toLocaleDateString('fr-BE', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    const ouvrir = (numero) => router.get(
        route('missions.index'),
        numero ? { mission: numero } : {},
        { preserveState: true, preserveScroll: true, only: ['mission', 'introuvable'] },
    );

    return (
        <ChauffeurLayout>
            <Head title="Mes missions" />

            <div className="lg:flex lg:gap-4">
                <div className={`lg:w-[360px] lg:shrink-0 ${mission ? 'hidden lg:block' : ''}`}>
                    <div className="mb-4 flex items-end justify-between gap-3">
                        <div>
                            <h1 className="text-2xl font-bold text-marine">Mes missions</h1>
                            <p className="text-sm capitalize text-slate-600">{aujourdhui}</p>
                        </div>
                        <span className="shrink-0 rounded-full bg-brand-blue/10 px-3 py-1 text-sm font-bold text-brand-blue">
                            {actives} active{actives > 1 ? 's' : ''}
                        </span>
                    </div>

                    {introuvable && (
                        <p className="mb-3 rounded-lg bg-status-incident/10 p-3 text-sm text-status-incident">
                            Cette mission ne vous est pas affectée.
                        </p>
                    )}

                    {missions.length === 0 ? (
                        <div className="rounded-2xl bg-white p-8 text-center shadow-sm">
                            <p className="font-semibold text-marine">Aucune mission affectée</p>
                            <p className="mt-1 text-sm text-slate-600">
                                Le planificateur vous préviendra dès qu'une expédition vous est confiée.
                            </p>
                        </div>
                    ) : (
                        <ul className="space-y-3">
                            {missions.map((ligne) => (
                                <CarteMission
                                    key={ligne.id}
                                    mission={ligne}
                                    active={ligne.numero === mission?.numero}
                                    onClick={() => ouvrir(ligne.numero)}
                                />
                            ))}
                        </ul>
                    )}
                </div>

                <div className="lg:min-w-0 lg:flex-1">
                    {mission ? (
                        <Fiche mission={mission} onRetour={() => ouvrir(null)} />
                    ) : (
                        <div className="hidden h-full items-center justify-center rounded-2xl border border-dashed border-slate-300 p-8 text-center lg:flex">
                            <p className="text-sm text-slate-600">
                                Choisissez une mission pour voir son détail.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </ChauffeurLayout>
    );
}
