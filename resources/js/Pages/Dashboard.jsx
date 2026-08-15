import CarteTrajets from '@/Components/CarteTrajets';
import Icone from '@/Components/Icone';
import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STATUS = {
    PENDING: { cle: 'statut.en_attente', label: 'En attente', cls: 'bg-status-pending/10 text-status-pending' },
    IN_PROGRESS: { cle: 'statut.en_cours', label: 'En cours', cls: 'bg-status-progress/10 text-status-progress' },
    DELIVERED: { cle: 'statut.livre', label: 'Livré', cls: 'bg-status-delivered/10 text-status-delivered' },
    CANCELLED: { cle: 'statut.annule', label: 'Annulé', cls: 'bg-status-incident/10 text-status-incident' },
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
    const t = useTraduction();
    const locale = useLocale();
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
                    <h2 className="font-semibold text-marine">{t('tdb.volume_titre', 'Volume mensuel')}</h2>
                    <p className="text-sm text-slate-600">{t('tdb.volume_sous_titre', 'Sept derniers mois, comparés à la même période l\'an dernier')}</p>
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
                        title={`${mois.libelle} : ${mois.nombre} ${mois.nombre > 1 ? t('commun.expeditions', 'expéditions') : t('commun.expedition', 'expédition')} (${mois.tonnes} t)`
                            + ` — ${mois.nombre_n1} ${t('tdb.an_dernier', 'l\'an dernier')} (${mois.tonnes_n1} t)`}
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
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('tdb.sur_periode', 'Sur la période')}</p>
                    <p className="font-bold text-marine">
                        {total} {t('commun.expeditions', 'expéditions')}
                        {variation !== null && (
                            <span className={`ml-2 text-sm font-bold ${
                                variation > 0 ? 'text-status-delivered' : variation < 0 ? 'text-status-incident' : 'text-slate-600'
                            }`}>
                                {variation > 0 ? '+' : ''}{variation} %
                            </span>
                        )}
                    </p>
                    <p className="text-xs text-slate-600">
                        {totalN1} {t('tdb.meme_periode', 'sur la même période')} {anneeN1}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('tdb.mois_charge', 'Mois le plus chargé')}</p>
                    <p className="font-bold capitalize text-marine">
                        {pic.libelle} — {pic.tonnes.toLocaleString(locale)} t
                    </p>
                </div>
            </div>
        </section>
    );
}

/**
 * Le plan de charge du planificateur. Il ne se demande pas combien d'ordres
 * attendent, il se demande s'il peut accepter une livraison jeudi : chaque
 * jour confronte donc ce qui est promis a ce qui peut le tenir.
 */
function PlanDeCharge({ calendrier }) {
    const t = useTraduction();
    const { jours, capacite, camions, conducteurs, a_affecter: aAffecter } = calendrier;

    return (
        <section className="flex h-full flex-col rounded-2xl bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-marine">{t('tdb.plan_titre', 'Plan de charge')}</h2>
                    <p className="text-sm text-slate-600">{t('tdb.plan_sous_titre', 'Deux prochaines semaines — enlèvements et livraisons promises')}</p>
                </div>
                <div className="flex items-center gap-4 text-xs text-slate-600">
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-2.5 rounded-sm bg-marine" />
                        {t('tdb.enlevements', 'Enlèvements')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-2.5 rounded-sm bg-brand-blue/40" />
                        {t('tdb.livraisons', 'Livraisons')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-2.5 rounded-sm bg-status-incident" />
                        {t('tdb.camion_manquant', 'Camion manquant')}
                    </span>
                </div>
            </div>

            {/* Quatorze cases sur deux rangees de sept. Le planificateur lit
                une quinzaine d'un coup d'oeil, comme un agenda.

                content-start empeche les rangees de s'etirer sur toute la
                hauteur : la carte reste alignee sur sa voisine, mais les
                cases gardent la taille de leur contenu. */}
            <div className="mt-5 grid flex-1 content-start grid-cols-7 gap-1.5">
                {jours.map((j) => (
                    <Link
                        key={j.date}
                        href={route('planning.index', { status: 'PENDING' })}
                        title={`${j.jour} ${j.numero} ${j.mois}`
                            + (j.ferie ? ` — ${j.ferie}, ${t('tdb.quai_ferme', 'quai fermé')}` : '')
                            + ` — ${j.enlevements} ${j.enlevements > 1 ? t('commun.enlevements', 'enlèvements') : t('commun.enlevement', 'enlèvement')}`
                            + `, ${j.livraisons} ${j.livraisons > 1 ? t('tdb.livraisons_promises', 'livraisons promises') : t('tdb.livraison_promise', 'livraison promise')}`
                            + (j.a_affecter > 0 ? ` — ${j.a_affecter} ${t('tdb.sans_vehicule', 'sans véhicule')}` : '')
                            + (j.sature ? ` — ${t('tdb.au_dela', 'au-delà de la capacité de')} ${capacite}` : '')}
                        className={`flex min-h-[92px] min-w-0 flex-col gap-1 rounded-lg border p-1.5 transition ${
                            j.aujourdhui ? 'border-action bg-action/5'
                                : j.sature ? 'border-status-incident/40 bg-status-incident/5'
                                : j.chome ? 'border-slate-200 bg-slate-100 hover:bg-slate-200'
                                : 'border-slate-100 hover:bg-surface'
                        }`}
                    >
                        <span className="flex items-baseline justify-between gap-1">
                            <span className={`truncate text-[11px] capitalize ${
                                j.aujourdhui ? 'font-bold text-action-dark' : 'text-slate-500'
                            }`}>
                                {j.jour}
                            </span>
                            <span className={`text-sm font-bold leading-none ${
                                j.aujourdhui ? 'text-action-dark' : 'text-marine'
                            }`}>
                                {j.numero}
                            </span>
                        </span>

                        {/* Pas de truncate : a sept colonnes le mot depasse la
                            case et se ferait couper. Il passe a la ligne. */}
                        {j.enlevements > 0 && (
                            <span className={`rounded px-1.5 py-0.5 text-[11px] font-semibold leading-tight text-white ${
                                j.sature ? 'bg-status-incident' : 'bg-marine'
                            }`}>
                                {j.enlevements} {j.enlevements > 1 ? t('commun.enlevements', 'enlèvements') : t('commun.enlevement', 'enlèvement')}
                            </span>
                        )}

                        {j.livraisons > 0 && (
                            <span className="rounded bg-brand-blue/25 px-1.5 py-0.5 text-[11px] font-semibold leading-tight text-marine">
                                {j.livraisons} {j.livraisons > 1 ? t('commun.livraisons', 'livraisons') : t('commun.livraison', 'livraison')}
                            </span>
                        )}

                        {/* La couverture plutot que le manque : afficher le
                            nombre non affecte repetait le nombre d'enlevements
                            tant que rien n'est attribue. */}
                        {j.enlevements > 0 && (
                            <span className={`truncate text-[10px] font-semibold ${
                                j.a_affecter > 0 ? 'text-status-incident' : 'text-status-delivered'
                            }`}>
                                {j.enlevements - j.a_affecter}/{j.enlevements} {j.enlevements > 1 ? t('tdb.affectes', 'affectés') : t('tdb.affecte', 'affecté')}
                            </span>
                        )}

                        {j.ferie && (
                            <span className="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                {j.ferie}
                            </span>
                        )}

                        {j.enlevements === 0 && j.livraisons === 0 && ! j.ferie && (
                            <span className="text-[11px] text-slate-400">{t('tdb.rien_prevu', 'Rien de prévu')}</span>
                        )}
                    </Link>
                ))}
            </div>

            <div className="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('tdb.capacite_jour', 'Capacité du jour')}</p>
                    <p className="font-bold text-marine">
                        {capacite} {t('tdb.attelages', 'attelages')}
                        <span className="ml-2 text-xs font-normal text-slate-600">
                            {camions} {t('commun.camions', 'camions')} · {conducteurs} {t('tdb.chauffeurs_aptes', 'chauffeurs aptes')}
                        </span>
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('tdb.restent', 'Restent à affecter')}</p>
                    <p className={`font-bold ${aAffecter > 0 ? 'text-status-incident' : 'text-status-delivered'}`}>
                        {aAffecter > 0 ? `${aAffecter} ${t('commun.expeditions', 'expéditions')}` : t('tdb.tout_affecte', 'Tout est affecté')}
                    </p>
                </div>
            </div>
        </section>
    );
}

function FicheEntreprise({ entreprise, onFermer }) {
    const t = useTraduction();
    const locale = useLocale();
    const { post, processing } = useForm({});

    const euros = (montant) => montant === null || montant === undefined
        ? '—'
        : Number(montant).toLocaleString(locale, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

    const ligne = (intitule, valeur, mono = false) => (
        <div>
            <dt className="text-xs uppercase tracking-wide text-slate-600">{intitule}</dt>
            <dd className={`font-semibold text-marine ${mono ? 'font-mono text-sm' : ''}`}>{valeur || '—'}</dd>
        </div>
    );

    return (
        <div className="p-6">
            <p className="text-xl font-bold text-marine">{entreprise.entreprise}</p>
            <p className="text-sm text-slate-600">
                {[entreprise.secteur, entreprise.pays].filter(Boolean).join(' · ')}
            </p>

            <dl className="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                {ligne(t('compte.numero_tva', 'Numéro de TVA'), entreprise.tva, true)}
                {ligne(t('ent.numero', 'Numéro d\'entreprise'), entreprise.entreprise_numero, true)}
                {ligne(t('ent.peppol', 'Identifiant Peppol'), entreprise.peppol, true)}
                {ligne(t('ent.secteur', 'Secteur'), entreprise.secteur)}
                {ligne(t('ent.adresse_fact', 'Adresse de facturation'), entreprise.adresse)}
                {ligne(t('ent.localite', 'Localité'), [entreprise.localite, entreprise.pays].filter(Boolean).join(', '))}
                {ligne(t('ent.delai', 'Délai de paiement'), entreprise.delai)}
                {ligne(t('ent.plafond', 'Plafond de crédit'), euros(entreprise.plafond))}
            </dl>

            <h3 className="mb-3 mt-5 text-xs font-semibold uppercase tracking-wider text-slate-600">
                {t('auth.personne_contact', 'Personne de contact')}
            </h3>
            <dl className="grid gap-4 sm:grid-cols-3">
                {ligne(t('auth.nom', 'Nom'), entreprise.contact)}
                {ligne(t('auth.adresse_email', 'Adresse électronique'), entreprise.courriel)}
                {ligne(t('auth.telephone', 'Téléphone'), entreprise.telephone)}
            </dl>

            <p className="mt-5 rounded-lg bg-surface px-3 py-2 text-xs text-slate-600">
                {t('ent.note_vies', 'Le numéro de TVA a été vérifié auprès du registre européen VIES au moment de l\'inscription. Valider donne à cette entreprise l\'accès à son espace client.')}
            </p>

            <div className="mt-6 flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    onClick={onFermer}
                    className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:text-marine"
                >
                    {t('action.fermer', 'Fermer')}
                </button>
                <Link
                    href={route('clients.index')}
                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-marine transition hover:bg-surface"
                >
                    {t('ent.ouvrir_dossier', 'Ouvrir le dossier')}
                </Link>
                <button
                    type="button"
                    disabled={processing}
                    onClick={() => post(route('clients.approve', entreprise.id), {
                        preserveScroll: true,
                        onSuccess: onFermer,
                    })}
                    className="rounded-lg bg-action px-5 py-2 text-sm font-bold text-marine-deep transition hover:bg-action-dark disabled:opacity-50"
                >
                    {processing ? t('ent.validation', 'Validation…') : t('ent.valider', 'Valider l\'entreprise')}
                </button>
            </div>
        </div>
    );
}

function ValidationsEnAttente({ validations }) {
    const t = useTraduction();
    const [ouverte, setOuverte] = useState(null);

    return (
        <section className="flex h-full flex-col rounded-2xl bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-2">
                <h2 className="font-semibold text-marine">{t('tdb.validations', 'Validations en attente')}</h2>
                <Link href={route('clients.index')} className="text-sm font-medium text-action hover:underline">
                    {t('action.voir_tout', 'Voir tout')}
                </Link>
            </div>

            {validations.length === 0 ? (
                <p className="text-sm text-slate-600">{t('tdb.aucune_validation', 'Aucune entreprise n\'attend de décision.')}</p>
            ) : (
                <ul className="space-y-2">
                    {validations.map((entreprise) => (
                        <li key={entreprise.id} className="rounded-xl border border-slate-200 p-3">
                            <p className="truncate font-semibold text-marine">{entreprise.entreprise}</p>
                            <p className="truncate text-xs text-slate-600">
                                {[entreprise.secteur, entreprise.pays].filter(Boolean).join(' · ')}
                            </p>
                            <div className="mt-2 flex items-center gap-2">
                                <span className="min-w-0 flex-1 truncate font-mono text-xs text-slate-600">
                                    {entreprise.tva}
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setOuverte(entreprise)}
                                    className="shrink-0 rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-marine transition hover:bg-surface"
                                >
                                    {t('action.voir', 'Voir')}
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <Modal show={ouverte !== null} onClose={() => setOuverte(null)} maxWidth="2xl">
                {ouverte && <FicheEntreprise entreprise={ouverte} onFermer={() => setOuverte(null)} />}
            </Modal>
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
    const t = useTraduction();

    return (
        // Dernier bloc de sa colonne : il s'etire jusqu'au bas de la rangee,
        // sinon la liste des ordres, plus haute, laisse un vide a droite.
        <section className="flex flex-1 flex-col rounded-2xl bg-white p-6 shadow-sm">
            <h2 className="mb-4 font-semibold text-marine">{t('tdb.alertes', 'Alertes')}</h2>

            {alertes.length === 0 ? (
                <p className="rounded-xl border border-status-delivered/30 bg-status-delivered/5 px-3 py-4 text-sm text-status-delivered">
                    {t('tdb.rien_signaler', 'Rien à signaler.')}
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
    const t = useTraduction();
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
            {/* Meme cloisonnement que sur le suivi : l'etiquette monte a 1100
                pour couvrir les calques de Leaflet, elle ne doit pas pour
                autant couvrir l'en-tete. */}
            <div className="relative isolate h-56">
                <CarteTrajets trajets={trajets} onSelection={ouvrir} className="h-full w-full" />
                <span className="pointer-events-none absolute left-3 top-3 z-[1100] rounded-lg bg-marine px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white shadow">
                    {total > carte.length ? `${carte.length} ${t('tdb.des', 'des')} ${total}` : total} {t('tdb.en_circulation', 'en circulation')}
                </span>
            </div>
            <Link
                href={route('tracking.show')}
                className="block border-t border-slate-100 px-4 py-2.5 text-center text-sm font-semibold text-action transition hover:bg-surface"
            >
                {t('tdb.ouvrir_suivi', 'Ouvrir le suivi')}
            </Link>
        </section>
    );
}

function Facturation({ facturation }) {
    const t = useTraduction();
    const locale = useLocale();
    const euros = (montant) => Number(montant).toLocaleString(locale, {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    });

    return (
        <section className="flex h-full flex-col rounded-2xl bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-2">
                <h2 className="font-semibold text-marine">{t('nav.facturation', 'Facturation')}</h2>
                <Link href={route('invoices.index')} className="text-sm font-medium text-action hover:underline">
                    {t('action.voir_tout', 'Voir tout')}
                </Link>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div className="rounded-xl bg-status-delivered/5 px-3 py-2.5">
                    <p className="text-[11px] uppercase tracking-wide text-slate-600">{t('tdb.regle', 'Réglé')}</p>
                    <p className="text-lg font-bold text-status-delivered">{euros(facturation.paye)}</p>
                </div>
                <div className={`rounded-xl px-3 py-2.5 ${facturation.en_retard > 0 ? 'bg-status-incident/5' : 'bg-surface'}`}>
                    <p className="text-[11px] uppercase tracking-wide text-slate-600">{t('tdb.reste_du', 'Reste dû')}</p>
                    <p className={`text-lg font-bold ${facturation.en_retard > 0 ? 'text-status-incident' : 'text-marine'}`}>
                        {euros(facturation.du)}
                    </p>
                </div>
            </div>

            {facturation.en_retard > 0 && (
                <p className="mt-2 text-xs font-semibold text-status-incident">
                    {t('tdb.dont', 'dont')} {facturation.en_retard} {facturation.en_retard > 1
                        ? t('tdb.factures_echues', 'factures échues')
                        : t('tdb.facture_echue', 'facture échue')}
                </p>
            )}

            {facturation.dernieres.length > 0 && (
                <>
                    <h3 className="mb-2 mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                        {t('tdb.dernieres_factures', 'Dernières factures')}
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
                                {/* L'etat arrive du serveur en francais : il sert
                                    de valeur de repli, la cle porte la traduction. */}
                                <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                    facture.etat === 'Payée' ? 'bg-status-delivered/10 text-status-delivered'
                                        : facture.etat === 'En retard' ? 'bg-status-incident/10 text-status-incident'
                                            : 'bg-slate-100 text-slate-700'
                                }`}>
                                    {facture.etat === 'Payée' ? t('statut.payee', 'Payée')
                                        : facture.etat === 'En retard' ? t('statut.en_retard', 'En retard')
                                            : t('statut.envoyee', 'Envoyée')}
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

/**
 * Ce qui n'est plus en regle chez les chauffeurs et les vehicules, nomme
 * plutot que compte : une
 * alerte qui annonce cinquante-trois visites a renouveler ne dit pas
 * lesquelles.
 */
function Conformite({ conformite }) {
    const t = useTraduction();

    const colonne = (titre, lignes, total, adresse, rendu) => (
        <div>
            <div className="mb-3 flex items-center justify-between gap-2">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-600">{titre}</h3>
                {total > 0 && (
                    <Link href={adresse} className="text-sm font-medium text-action hover:underline">
                        {t('tdb.voir_les', 'Voir les')} {total}
                    </Link>
                )}
            </div>

            {lignes.length === 0 ? (
                <p className="rounded-xl border border-status-delivered/30 bg-status-delivered/5 px-3 py-3 text-sm text-status-delivered">
                    {t('tdb.tout_en_regle', 'Tout est en règle.')}
                </p>
            ) : (
                <ul className="space-y-2">{lignes.map(rendu)}</ul>
            )}
        </div>
    );

    // Tout ce qui figure ici roule encore : la pastille dit l'urgence, pas
    // l'etat de service, qui serait le meme pour toute la liste.
    const pastille = () => (
        <span className="shrink-0 rounded-full bg-status-incident/10 px-2 py-0.5 text-[11px] font-semibold text-status-incident">
            {t('tdb.a_traiter', 'À traiter')}
        </span>
    );

    return (
        <section className="flex h-full flex-col rounded-2xl bg-white p-6 shadow-sm">
            <h2 className="font-semibold text-marine">{t('tdb.conformite_titre', 'Chauffeurs et véhicules à mettre en règle')}</h2>
            <p className="mb-5 text-sm text-slate-600">
                {t('tdb.conformite_sous_titre', 'Uniquement ce qui roule encore alors qu\'une échéance est passée')}
            </p>

            <div className="grid gap-6 lg:grid-cols-2">
                {colonne(
                    t('nav.chauffeurs', 'Chauffeurs'),
                    conformite.chauffeurs,
                    conformite.total_chauffeurs,
                    route('drivers.index', { etat: 'visite' }),
                    (c) => (
                        <li key={c.id} className="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-semibold text-marine">{c.nom}</span>
                                <span className="block truncate text-xs text-slate-600">{c.motif}</span>
                            </span>
                            {pastille()}
                        </li>
                    ),
                )}

                {colonne(
                    t('nav.vehicules', 'Véhicules'),
                    conformite.vehicules,
                    conformite.total_vehicules,
                    route('vehicles.index', { etat: 'controle' }),
                    (v) => (
                        <li key={v.immatriculation} className="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-semibold text-marine">
                                    <span className="font-mono">{v.immatriculation}</span> — {v.modele}
                                </span>
                                <span className="block truncate text-xs text-slate-600">{v.motif}</span>
                            </span>
                            {pastille()}
                        </li>
                    ),
                )}
            </div>
        </section>
    );
}

function DernieresTraces({ journal }) {
    const t = useTraduction();

    return (
        <section className="flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-sm">
            <div className="flex items-center justify-between gap-2 border-b border-slate-100 px-6 py-4">
                <h2 className="font-semibold text-marine">{t('tdb.traces', 'Dernières traces')}</h2>
                <Link href={route('activity-logs.index')} className="text-sm font-medium text-action hover:underline">
                    {t('action.voir_tout', 'Voir tout')}
                </Link>
            </div>

            {journal.length === 0 ? (
                <p className="px-6 py-8 text-sm text-slate-600">{t('tdb.journal_vide', 'Le journal est vide.')}</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('tdb.horodatage', 'Horodatage')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('tdb.auteur', 'Auteur')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('tdb.action', 'Action')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {journal.map((ligne, i) => (
                                <tr key={i} className="border-b border-slate-50 last:border-0">
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{ligne.horodatage}</td>
                                    <td className="px-4 py-3 text-slate-700">
                                        <span className="block max-w-[10rem] truncate" title={ligne.auteur}>
                                            {ligne.auteur}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-marine">
                                        <span className="block max-w-[26rem] truncate" title={ligne.description}>
                                            {ligne.description}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
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
    calendrier = null,
    validations = null,
    conformite = null,
    journal = null,
}) {
    const { auth } = usePage().props;
    const t = useTraduction();
    const locale = useLocale();
    const personnel = Boolean(exploitation);

    const nombre = (valeur) => valeur === null || valeur === undefined
        ? '—'
        : Number(valeur).toLocaleString(locale);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">
                        {personnel ? t('tdb.vue_ensemble', 'Vue d\'ensemble') : t('nav.tableau_de_bord', 'Tableau de bord')}
                    </h1>
                    <p className="text-sm text-slate-600">
                        {personnel
                            ? t('tdb.sous_titre_personnel', 'Activité de l\'exploitation et dossiers en attente.')
                            : t('tdb.sous_titre_client', 'Aperçu de vos ordres de transport.')}
                    </p>
                </div>
            }
        >
            <Head title={t('nav.tableau_de_bord', 'Tableau de bord')} />

            {personnel ? (
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard
                        label={t('tdb.entreprises_valider', 'Entreprises à valider')}
                        value={nombre(exploitation.entreprises_a_valider)}
                        icone="valide"
                        accent="bg-action/15 text-action-dark"
                        lien={auth.canValidateClients ? route('clients.index') : null}
                    />
                    <StatCard
                        label={t('tdb.expeditions_cours', 'Expéditions en cours')}
                        value={nombre(stats.pending + stats.in_progress)}
                        detail={`${nombre(stats.in_progress)} ${t('tdb.en_circulation', 'en circulation')}`}
                        icone="camion"
                        accent="bg-marine/10 text-marine"
                        lien={route('transport-orders.index')}
                    />
                    <StatCard
                        label={t('tdb.chauffeurs_dispo', 'Chauffeurs disponibles')}
                        value={nombre(exploitation.chauffeurs_disponibles)}
                        detail={`${t('commun.sur', 'sur')} ${nombre(exploitation.chauffeurs_total)}`}
                        icone="profil"
                        accent="bg-status-progress/10 text-status-progress"
                    />
                    <StatCard
                        label={t('tdb.vehicules_dispo', 'Véhicules disponibles')}
                        value={nombre(exploitation.vehicules_disponibles)}
                        detail={`${t('commun.sur', 'sur')} ${nombre(exploitation.vehicules_total)}`}
                        icone="camion"
                        accent="bg-status-delivered/10 text-status-delivered"
                    />
                </div>
            ) : (
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard
                        label={t('tdb.expeditions_actives', 'Expéditions actives')}
                        value={nombre(performance.actives)}
                        detail={`${nombre(stats.in_progress)} ${t('tdb.en_circulation', 'en circulation')}`}
                        icone="camion"
                        accent="bg-marine/10 text-marine"
                    />
                    <StatCard
                        label={t('tdb.delai_moyen', 'Délai moyen')}
                        value={performance.delai_moyen === null ? '—' : nombre(performance.delai_moyen)}
                        unite={performance.delai_moyen === null ? null : t('tdb.jrs', 'jrs')}
                        detail={t('tdb.commande_livraison', 'de la commande à la livraison')}
                        icone="horloge"
                        accent="bg-status-pending/10 text-status-pending"
                    />
                    <StatCard
                        label={t('tdb.taux_livraison', 'Taux de livraison')}
                        value={performance.taux_livraison === null ? '—' : nombre(performance.taux_livraison)}
                        unite={performance.taux_livraison === null ? null : '%'}
                        detail={t('tdb.abouties', 'des expéditions abouties')}
                        icone="coche"
                        accent="bg-status-delivered/10 text-status-delivered"
                    />
                    <StatCard
                        label={t('tdb.annulations', 'Annulations')}
                        value={nombre(performance.annulees)}
                        detail={`${t('commun.sur', 'sur')} ${nombre(stats.total)} ${t('commun.expeditions', 'expéditions')}`}
                        icone="aide"
                        accent="bg-status-incident/10 text-status-incident"
                    />
                </div>
            )}

            {/* Le planificateur ouvre sur sa quinzaine. C'est ce qu'il
                regarde en premier, avant meme les derniers ordres, donc le
                plan de charge occupe toute la largeur en tete de page. */}
            {calendrier && (
                <div className="mt-6">
                    <PlanDeCharge calendrier={calendrier} />
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
                            <h2 className="font-semibold text-marine">{t('tdb.derniers_ordres', 'Derniers ordres')}</h2>
                            <Link href={route('transport-orders.index')} className="text-sm font-medium text-action hover:underline">
                                {t('action.voir_tout', 'Voir tout')}
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('commun.reference', 'Référence')}</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('commun.donneur_ordre', 'Donneur d\'ordre')}</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('suivi.destination', 'Destination')}</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('commun.statut', 'Statut')}</th>
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
                                                    {STATUS[order.status]
                                                        ? t(STATUS[order.status].cle, STATUS[order.status].label)
                                                        : order.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                    {recent.length === 0 && (
                                        <tr><td className="px-6 py-8 text-center text-slate-600" colSpan="4">{t('tdb.aucun_ordre', 'Aucun ordre pour l\'instant.')}</td></tr>
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

                {journal && (
                    <div className="flex flex-col lg:col-span-2 lg:col-start-1 lg:row-start-3">
                        <DernieresTraces journal={journal} />
                    </div>
                )}

                {validations && (
                    <div className="flex flex-col lg:col-start-3 lg:row-start-3">
                        <ValidationsEnAttente validations={validations} />
                    </div>
                )}

                {/* Le journal n'est visible que de l'administrateur. Sans lui
                    la rangee trois n'aurait que les validations a droite : la
                    mise en conformite vient combler la gauche. */}
                {conformite && (
                    <div className={journal
                        ? 'lg:col-span-3 lg:col-start-1 lg:row-start-4'
                        : 'flex flex-col lg:col-span-2 lg:col-start-1 lg:row-start-3'}>
                        <Conformite conformite={conformite} />
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
