import BoutonRetour from '@/Components/BoutonRetour';
import ChauffeurLayout from '@/Layouts/ChauffeurLayout';
import { positionActuelle } from '@/position';
import { useLocale, useTraduction, useVocabulaire } from '@/traduire';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const STATUTS = {
    IN_PROGRESS: { cle: 'statut.en_cours', libelle: 'En cours', pastille: 'bg-action/15 text-action-dark', barre: 'border-l-action' },
    PENDING: { cle: 'mission.a_venir', libelle: 'À venir', pastille: 'bg-slate-100 text-slate-700', barre: 'border-l-slate-300' },
    DELIVERED: { cle: 'mission.livree', libelle: 'Livrée', pastille: 'bg-status-delivered/10 text-status-delivered', barre: 'border-l-status-delivered' },
    CANCELLED: { cle: 'mission.annulee', libelle: 'Annulée', pastille: 'bg-status-incident/10 text-status-incident', barre: 'border-l-status-incident' },
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
    const t = useTraduction();
    const v = useVocabulaire();
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
                        {t(statut.cle, statut.libelle)}
                    </span>
                    <span className="font-mono text-xs text-slate-600">{mission.numero}</span>
                </div>

                <div className="mt-3 space-y-2">
                    <Etape intitule={t('ordres.chargement', 'Chargement')} heure={mission.heure_enlevement} lieu={mission.enlevement} />
                    <Etape intitule={t('commun.livraison', 'livraison')} heure={mission.date_livraison} lieu={mission.livraison} />
                </div>

                <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                    <span className="text-sm text-slate-600">
                        {v('marchandise', mission.marchandise)}
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

function BoutonAvancement({ mission }) {
    const t = useTraduction();
    const [arme, setArme] = useState(false);
    const [processing, setProcessing] = useState(false);

    const envoyer = async () => {
        if (! arme) {
            setArme(true);

            return;
        }

        setProcessing(true);

        const point = await positionActuelle();

        router.patch(route('missions.status', mission.id), {
            statut: mission.action.statut,
            ...(point ?? {}),
        }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setArme(false);
            },
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
                {processing
                    ? t('action.enregistrement', 'Enregistrement…')
                    : arme
                        ? t('mission.confirmer', 'Appuyez à nouveau pour confirmer')
                        : mission.action.libelle}
            </button>
            {arme && ! processing && (
                <button
                    type="button"
                    onClick={() => setArme(false)}
                    className="mt-2 w-full py-1 text-sm font-semibold text-slate-600 transition hover:text-marine"
                >
                    {t('action.annuler', 'Annuler')}
                </button>
            )}
        </div>
    );
}

function NoteInformation({ note }) {
    const t = useTraduction();
    const [envoi, setEnvoi] = useState(false);

    if (! note) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-marine-deep/60 p-4 sm:items-center">
            <div className="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-xl sm:p-7">
                <h2 className="text-xl font-bold text-marine">{note.titre}</h2>
                <p className="mt-1 text-xs text-slate-600">
                    {t('note.version', 'Version du :date', { date: note.mise_a_jour })}
                </p>

                <div className="mt-4 whitespace-pre-line border-t border-slate-100 pt-4 text-sm leading-relaxed text-slate-700">
                    {note.corps}
                </div>

                <p className="mt-4 rounded-lg bg-surface px-3 py-2 text-xs text-slate-600">
                    {t('note.portee', 'Cette déclaration atteste que vous avez été informé. Elle ne vous demande pas votre accord : le traitement repose sur l\'exécution de votre contrat de travail et sur l\'intérêt légitime de l\'entreprise, pas sur votre consentement.')}
                </p>

                <button
                    type="button"
                    disabled={envoi}
                    onClick={() => {
                        setEnvoi(true);
                        router.post(route('missions.notice'), {}, {
                            preserveScroll: true,
                            onFinish: () => setEnvoi(false),
                        });
                    }}
                    className="mt-5 w-full rounded-xl bg-marine px-6 py-3.5 text-base font-bold text-white shadow-lg transition hover:bg-marine-deep disabled:opacity-60"
                >
                    {envoi
                        ? t('action.enregistrement', 'Enregistrement…')
                        : t('note.accuser', 'J\'ai pris connaissance')}
                </button>
            </div>
        </div>
    );
}

function SuiviDirect({ mission }) {
    const t = useTraduction();
    const locale = useLocale();
    const [dernier, setDernier] = useState(null);
    const [refuse, setRefuse] = useState(false);
    const actif = useRef(true);

    const partage = mission.suivi_direct && mission.statut === 'IN_PROGRESS';

    useEffect(() => {
        if (! partage) return undefined;

        actif.current = true;

        const envoyer = async () => {
            const point = await positionActuelle();

            if (! actif.current) return;

            if (! point) {
                setRefuse(true);

                return;
            }

            setRefuse(false);

            try {
                const reponse = await fetch(route('missions.position', mission.id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(
                            document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
                        ),
                    },
                    body: JSON.stringify(point),
                });

                const resultat = await reponse.json();

                if (resultat.suivi === false) {
                    actif.current = false;
                } else if (resultat.retenu) {
                    setDernier(new Date());
                }
            } catch {

            }
        };

        envoyer();
        const minuteur = setInterval(envoyer, 5 * 60 * 1000);

        return () => {
            actif.current = false;
            clearInterval(minuteur);
        };
    }, [partage, mission.id]);

    if (! partage) return null;

    return (
        <div className={`mt-4 rounded-lg px-3 py-2.5 text-xs ${refuse ? 'bg-status-incident/10' : 'bg-brand-blue/10'}`}>
            <p className={`font-semibold ${refuse ? 'text-status-incident' : 'text-brand-blue'}`}>
                {refuse
                    ? t('suivi_direct.refuse', 'Position non partagée')
                    : t('suivi_direct.actif', 'Votre position est partagée pour cette mission')}
            </p>
            <p className="mt-0.5 text-slate-600">
                {refuse
                    ? t('suivi_direct.refuse_aide', 'Le client ne voit pas votre progression. La mission se déclare normalement.')
                    : dernier
                        ? t('suivi_direct.dernier_envoi', 'Dernier envoi à :heure. Le partage s\'arrête à la livraison.', {
                            heure: dernier.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }),
                        })
                        : t('suivi_direct.attente', 'Le partage s\'arrête automatiquement à la livraison.')}
            </p>
        </div>
    );
}

function Fiche({ mission, onRetour }) {
    const t = useTraduction();
    const v = useVocabulaire();
    const locale = useLocale();
    const statut = STATUTS[mission.statut] ?? STATUTS.PENDING;

    const nombre = (valeur, unite) => valeur === null || valeur === undefined
        ? null
        : Number(valeur).toLocaleString(locale) + ' ' + unite;

    const mesures = [
        [t('commande.poids', 'Poids'), nombre(mission.poids, 'kg')],
        [t('commande.volume', 'Volume'), nombre(mission.volume, 'm³')],
        [t('commande.distance', 'Distance'), nombre(mission.distance_km, 'km')],
    ].filter(([, valeur]) => valeur !== null);

    return (
        <div>
            <BoutonRetour onClick={onRetour} className="mb-3 lg:hidden">
                {t('mission.mes_missions', 'Mes missions')}
            </BoutonRetour>

            <div className="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                <div className="flex items-center justify-between gap-2">
                    <span className={`rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${statut.pastille}`}>
                        {t(statut.cle, statut.libelle)}
                    </span>
                    <span className="font-mono text-xs text-slate-600">{mission.numero}</span>
                </div>

                <ol className="mt-4 space-y-4">
                    <li className="flex gap-3">
                        <span className="mt-1 flex h-3 w-3 shrink-0 rounded-full border-2 border-marine bg-white" />
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                {t('commun.enlevement', 'enlèvement')}{mission.enlevement_prevu && ' • ' + mission.enlevement_prevu}
                            </p>
                            <p className="font-bold leading-snug text-marine">{mission.adresse_enlevement}</p>
                        </div>
                    </li>
                    <li className="flex gap-3">
                        <span className="mt-1 h-3 w-3 shrink-0 rounded-sm bg-marine" />
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                {t('commun.livraison', 'livraison')}{mission.livraison_prevue && ' • ' + mission.livraison_prevue}
                            </p>
                            <p className="font-bold leading-snug text-marine">{mission.adresse_livraison}</p>
                        </div>
                    </li>
                </ol>

                <div className="mt-4 border-t border-slate-100 pt-4">
                    <p className="text-sm font-semibold text-marine">
                        {v('marchandise', mission.marchandise)}
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
                            {t('ordres.consignes', 'Consignes particulières')}
                        </p>
                        <p className="mt-0.5 text-sm text-marine">{mission.consignes}</p>
                    </div>
                )}

                <div className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">{t('ordres.vehicule', 'Véhicule')}</p>
                        {mission.vehicule ? (
                            <p className="text-sm text-marine">
                                <span className="font-mono font-semibold">{mission.vehicule.immatriculation}</span>
                                {' — '}{mission.vehicule.modele}
                                {mission.vehicule.type && ' · ' + mission.vehicule.type}
                            </p>
                        ) : (
                            <p className="text-sm text-slate-600">{t('mission.non_affecte', 'Non affecté')}</p>
                        )}
                    </div>
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">{t('commun.donneur_ordre', 'Donneur d\'ordre')}</p>
                        <p className="text-sm text-marine">{mission.client ?? '—'}</p>
                    </div>
                </div>

                {mission.livree_le && (
                    <p className="mt-4 rounded-lg bg-status-delivered/10 px-3 py-2 text-sm font-semibold text-status-delivered">
                        {t('mission.livree_le', 'Livrée le')} {mission.livree_le}
                    </p>
                )}

                <SuiviDirect mission={mission} />
            </div>

            {mission.action && <BoutonAvancement mission={mission} />}
        </div>
    );
}

export default function Missions({ missions = [], mission = null, introuvable = false, note = null }) {
    const t = useTraduction();
    const locale = useLocale();
    const actives = missions.filter((m) => m.statut === 'IN_PROGRESS' || m.statut === 'PENDING').length;

    const aujourdhui = new Date().toLocaleDateString(locale, {
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
            <Head title={t('mission.mes_missions', 'Mes missions')} />

            <NoteInformation note={note} />

            <div className="lg:flex lg:gap-4">
                <div className={`lg:w-[360px] lg:shrink-0 ${mission ? 'hidden lg:block' : ''}`}>
                    <div className="mb-4 flex items-end justify-between gap-3">
                        <div>
                            <h1 className="text-2xl font-bold text-marine">{t('mission.mes_missions', 'Mes missions')}</h1>
                            <p className="text-sm capitalize text-slate-600">{aujourdhui}</p>
                        </div>
                        <span className="shrink-0 rounded-full bg-brand-blue/10 px-3 py-1 text-sm font-bold text-brand-blue">
                            {actives} {actives > 1 ? t('mission.actives', 'actives') : t('mission.active', 'active')}
                        </span>
                    </div>

                    {introuvable && (
                        <p className="mb-3 rounded-lg bg-status-incident/10 p-3 text-sm text-status-incident">
                            {t('mission.pas_affectee', 'Cette mission ne vous est pas affectée.')}
                        </p>
                    )}

                    {missions.length === 0 ? (
                        <div className="rounded-2xl bg-white p-8 text-center shadow-sm">
                            <p className="font-semibold text-marine">{t('mission.aucune', 'Aucune mission affectée')}</p>
                            <p className="mt-1 text-sm text-slate-600">
                                {t('mission.aucune_texte', 'Le planificateur vous préviendra dès qu\'une expédition vous est confiée.')}
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
                                {t('mission.choisir', 'Choisissez une mission pour voir son détail.')}
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </ChauffeurLayout>
    );
}
