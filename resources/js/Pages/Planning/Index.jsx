import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction, useVocabulaire } from '@/traduire';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

const LIBELLE_STATUT = {
    PENDING: ['statut.en_attente', 'En attente'],
    ASSIGNED: ['statut.affecte', 'Affecté'],
    IN_PROGRESS: ['statut.en_cours', 'En cours'],
    DELIVERED: ['statut.livre', 'Livré'],
    CANCELLED: ['statut.annule', 'Annulé'],
};

const COULEUR_STATUT = {
    PENDING: 'bg-status-pending/10 text-status-pending',
    ASSIGNED: 'bg-status-assigned/10 text-status-assigned',
    IN_PROGRESS: 'bg-status-progress/10 text-status-progress',
    DELIVERED: 'bg-status-delivered/10 text-status-delivered',
    CANCELLED: 'bg-status-incident/10 text-status-incident',
};

const LIBELLE_PRIORITE = {
    LOW: ['priorite.basse', 'Basse'],
    NORMAL: ['priorite.normale', 'Normale'],
    HIGH: ['priorite.haute', 'Haute'],
    URGENT: ['priorite.urgente', 'Urgente'],
};

const COULEUR_PRIORITE = {
    LOW: 'text-slate-600',
    NORMAL: 'text-slate-600',
    HIGH: 'text-action-dark font-semibold',
    URGENT: 'text-status-incident font-semibold',
};

const enHeures = (heures) => {
    const h = Math.floor(heures);
    const min = Math.round((heures - h) * 60);

    if (min === 60) return `${h + 1} h`;

    return min === 0 ? `${h} h` : `${h} h ${String(min).padStart(2, '0')}`;
};

/**
 * Fret retour : les binomes qui livrent pres du lieu de chargement a la
 * bonne date, affectables d'un clic (le serveur recontrole tout), ou le
 * depart du depot a prevoir.
 */
function FretRetour({ ordre }) {
    const t = useTraduction();
    const candidats = ordre.fret_retour ?? [];
    const affecter = (c) => router.post(route('planning.assign', ordre.id), {
        vehicle_registration: c.vehicle_registration, driver_id: c.driver_id, reaffectation: false,
    }, { preserveScroll: true });

    return (
        <div className="mb-3 space-y-2 text-xs">
            {ordre.porteuse_info && (
                <p className={'rounded-lg px-3 py-2 ' + (ordre.porteuse_info.valide ? 'bg-status-delivered/10 text-status-delivered' : 'bg-status-incident/10 text-status-incident')} role={ordre.porteuse_info.valide ? 'status' : 'alert'}>
                    {ordre.porteuse_info.valide
                        ? t('planif.retour_vendu', 'Vendu au tarif fret retour sur :numero', { numero: ordre.porteuse_info.numero })
                        : t('planif.retour_orphelin', 'Vendu au tarif fret retour, mais la mission :numero n\'est plus disponible : trouvez un autre camion, le prix reste acquis au client.', { numero: ordre.porteuse_info.numero ?? '—' })}
                </p>
            )}
            {candidats.length > 0 && (
                <div className="rounded-lg bg-status-delivered/10 px-3 py-2 text-status-delivered">
                    <p className="font-semibold">{t('planif.fret_retour', 'Fret retour possible')}</p>
                    <ul className="mt-1 space-y-1">
                        {candidats.map((c) => (
                            <li key={c.numero} className="flex flex-wrap items-center justify-between gap-2">
                                <span>{t('planif.fret_retour_ligne', ':camion · :chauffeur — livre :numero à :ville le :date · :km km d\'approche · :reste t libres', {
                                    camion: c.vehicle_registration, chauffeur: c.chauffeur, numero: c.numero, ville: c.ville, date: c.arrivee, km: c.approche_km, reste: c.reste_t,
                                })}</span>
                                <button type="button" onClick={() => affecter(c)} className="rounded-md bg-status-delivered px-2 py-1 font-semibold text-white hover:opacity-90">
                                    {t('planif.affecter_binome', 'Affecter à ce binôme')}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {ordre.positionnement && (
                <p className="rounded-lg bg-surface px-3 py-2 text-slate-600">
                    {t('planif.positionnement', 'Aucun camion ne livre à moins de :rayon km : départ de Bruxelles au plus tard le :date (≈ :km km à vide).', { rayon: 150, date: ordre.positionnement.depart_au_plus_tard, km: ordre.positionnement.km })}
                </p>
            )}
        </div>
    );
}

function LigneAffectation({ ordre, vehicles, drivers, couverture = {}, reaffectation = false, onFermer }) {
    const t = useTraduction();
    const voc = useVocabulaire();
    const { data, setData, post, processing, errors } = useForm({
        vehicle_registration: reaffectation ? (ordre.vehicle?.registration ?? '') : '',
        driver_id: reaffectation ? String(ordre.driver_id ?? '') : '',
        motif: '',
        reaffectation,
    });

    const affecter = (e) => {
        e.preventDefault();
        post(route('planning.assign', ordre.id), {
            preserveScroll: true,
            onSuccess: () => onFermer?.(),
        });
    };

    // Le serveur dit, a la date de la mission, pourquoi un camion ou un
    // chauffeur ne convient pas (capacite, hayon, ADR, controle technique,
    // documents) : l'ecran et l'affectation ne se contredisent plus.
    const vehiculeChoisi = vehicles.find((v) => v.registration === data.vehicle_registration);
    const chauffeurChoisi = drivers.find((d) => String(d.id) === String(data.driver_id));
    // Code 95 et carte tachygraphe ne sont exiges qu'au-dela de 3,5 t :
    // ils ne grisent pas un chauffeur si le camion choisi est une
    // camionnette de permis B.
    const lourd = (v) => ! v || v.permis_requis !== 'B';
    const refusPro = (d, v) => (lourd(v) ? ordre.refus_chauffeurs_pro?.[d.id] ?? null : null);
    const refusVehicule = (v) => ordre.refus_vehicules?.[v.registration]
        ?? (chauffeurChoisi && v.permis_requis !== 'B' ? ordre.refus_chauffeurs_pro?.[chauffeurChoisi.id] ?? null : null);
    const refusChauffeur = (d) => ordre.refus_chauffeurs?.[d.id] ?? refusPro(d, vehiculeChoisi);
    // Le permis depend du couple : celui du chauffeur doit couvrir celui
    // qu'exige le camion (un tracteur de 44 t exige le CE).
    const permisManquant = (permis, vehicule) => (
        vehicule && ! (couverture[permis] ?? []).includes(vehicule.permis_requis) ? vehicule.permis_requis : null
    );
    const motifPermis = (permis) => ' (' + t('planif.permis_requis', 'permis :permis requis', { permis }) + ')';

    const vehiculeCompatible = (v) => ! refusVehicule(v) && ! (chauffeurChoisi && permisManquant(chauffeurChoisi.license_type, v));
    const motifRefus = (v) => {
        const refus = refusVehicule(v);
        if (refus) return ' (' + refus + ')';
        const permis = chauffeurChoisi ? permisManquant(chauffeurChoisi.license_type, v) : null;

        return permis ? motifPermis(permis) : '';
    };
    const chauffeurCompatible = (d) => ! refusChauffeur(d) && ! permisManquant(d.license_type, vehiculeChoisi);
    const motifChauffeur = (d) => {
        const refus = refusChauffeur(d);
        if (refus) return ' (' + refus + ')';
        const permis = permisManquant(d.license_type, vehiculeChoisi);

        return permis ? motifPermis(permis) : '';
    };
    const selectCls = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine';

    const camionsOk = vehicles.filter(vehiculeCompatible).length;
    const chauffeursOk = drivers.filter(chauffeurCompatible).length;
    const pastille = 'rounded-full px-2.5 py-0.5 font-medium';
    const locale = useLocale();

    return (
        <>
        <div className="mb-3 flex flex-wrap items-center gap-2 text-xs">
            <span className="font-semibold uppercase tracking-wide text-slate-600">{t('planif.besoins', 'Besoins')}</span>

            {/* Tronque au centieme : arrondi au dixieme, 850 kg s'affichaient
                0,9 t et semblaient ecarter un camion de 0,85 t qui convient. */}
            <span className={pastille + ' bg-surface text-marine'}>
                {t('planif.charge_utile', 'Charge utile ≥')} {(Math.floor(Number(ordre.weight) / 10) / 100).toLocaleString(locale, { maximumFractionDigits: 2 })} t
            </span>

            {ordre.volume && (
                <span className={pastille + ' bg-surface text-marine'}>
                    {t('planif.volume_min', 'Volume ≥')} {Number(ordre.volume).toLocaleString(locale)} m³
                </span>
            )}

            {ordre.trajet && ordre.trajet !== 'BE → BE' && (
                <span className={pastille + ' bg-brand-blue/10 text-brand-blue'}>
                    {ordre.trajet}
                </span>
            )}

            {ordre.conduite && (
                <span className={pastille + ' bg-surface text-marine'}>
                    {ordre.conduite}
                </span>
            )}

            {ordre.is_hazardous && (
                <>
                    <span className={pastille + ' bg-status-incident/10 text-status-incident'}>
                        {t('planif.chauffeur_adr', 'Chauffeur certifié ADR')}
                    </span>
                    <span className={pastille + ' bg-status-incident/10 text-status-incident'}>
                        {t('planif.vehicule_adr', 'Véhicule équipé ADR')}
                    </span>
                </>
            )}

            {ordre.needs_tail_lift && (
                <span className={pastille + ' bg-brand-blue/10 text-brand-blue'}>
                    {t('devis.hayon', 'Hayon élévateur')}
                </span>
            )}

            <span className={camionsOk === 0 || chauffeursOk === 0 ? 'font-semibold text-status-incident' : 'text-slate-600'}>
                {camionsOk} {camionsOk > 1 ? t('commun.camions', 'camions') : t('planif.camion', 'camion')} · {chauffeursOk} {chauffeursOk > 1 ? t('planif.chauffeurs_compat', 'chauffeurs compatibles') : t('planif.chauffeur_compat', 'chauffeur compatible')}
            </span>
        </div>

        {reaffectation && (
            <div className="mb-2">
                <p className="mb-1 text-xs text-slate-600">
                    {ordre.status === 'IN_PROGRESS'
                        ? t('planif.transbordement_aide', 'La marchandise est chargée : choisissez le camion ou le chauffeur qui prend le relais. La mission reste en cours.')
                        : t('planif.reaffectation_aide', 'Changez de camion ou de chauffeur sans remettre la mission en attente.')}
                </p>
                <input
                    type="text"
                    value={data.motif}
                    onChange={(e) => setData('motif', e.target.value)}
                    placeholder={t('planif.motif_reaffectation', 'Motif (panne, accident, relais de chauffeur…)')}
                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                    required
                    minLength={5}
                    maxLength={200}
                />
                {errors.motif && <p className="mt-1 text-xs text-status-incident">{errors.motif}</p>}
            </div>
        )}
        <form onSubmit={affecter} className="flex flex-col gap-2 sm:flex-row sm:items-start">
            <div className="flex-1">
                <select
                    value={data.vehicle_registration}
                    onChange={(e) => setData('vehicle_registration', e.target.value)}
                    className={selectCls}
                    required
                >
                    <option value="">— {t('ordres.vehicule', 'Véhicule')} —</option>
                    {vehicles.map((v) => (
                        <option key={v.registration} value={v.registration} disabled={! vehiculeCompatible(v)}>
                            {v.registration} · {voc('vehicule', v.vehicle_type)} · {v.brand} {v.model} · {Number(v.capacity_tonnes).toLocaleString(locale)} t
                            {v.capacity_volume ? ` · ${Number(v.capacity_volume).toLocaleString(locale)} m³` : ''}
                            {' · ' + t('suivi.permis', 'Permis').toLowerCase() + ' ' + v.permis_requis}
                            {v.has_tail_lift ? ' · ' + t('planif.hayon_court', 'hayon') : ''}
                            {v.adr_equipe ? ' · ADR' : ''}
                            {motifRefus(v)}
                        </option>
                    ))}
                </select>
                {errors.vehicle_registration && <p className="mt-1 text-xs text-status-incident">{errors.vehicle_registration}</p>}
            </div>
            <div className="flex-1">
                <select
                    value={data.driver_id}
                    onChange={(e) => setData('driver_id', e.target.value)}
                    className={selectCls}
                    required
                >
                    <option value="">— {t('suivi.chauffeur', 'Chauffeur')} —</option>
                    {drivers.map((d) => (
                        <option key={d.id} value={d.id} disabled={! chauffeurCompatible(d)}>
                            {d.nom} · {t('suivi.permis', 'Permis').toLowerCase()} {d.license_type}{d.adr_certified ? ' · ADR' : ''}
                            {d.conduite_semaine > 0 ? ` · ${enHeures(d.conduite_semaine)} ${t('planif.cette_semaine', 'cette semaine')}` : ''}
                            {d.retraite_passee ? ' · ' + t('planif.retraite_passee', 'retraite prévue le :date, fiche à revoir', { date: d.retraite_passee }) : ''}
                            {motifChauffeur(d)}
                        </option>
                    ))}
                </select>
                {errors.driver_id && <p className="mt-1 text-xs text-status-incident">{errors.driver_id}</p>}
            </div>
            <button
                type="submit"
                disabled={processing}
                className="rounded-lg bg-action px-4 py-2 text-sm font-semibold text-marine-deep transition hover:bg-action-dark disabled:opacity-50"
            >
                {reaffectation ? t('planif.reaffecter', 'Réaffecter') : t('planif.affecter', 'Affecter')}
            </button>
            {reaffectation && (
                <button
                    type="button"
                    onClick={onFermer}
                    className="px-2 py-2 text-sm font-semibold text-slate-600 hover:text-marine"
                >
                    {t('action.annuler', 'Annuler')}
                </button>
            )}
        </form>
        </>
    );
}

function BoutonsStatut({ ordre, onReaffecter }) {
    const t = useTraduction();

    const changer = (statut, confirmation) => {
        if (confirmation && ! window.confirm(confirmation)) return;
        router.patch(route('planning.status', ordre.id), { status: statut }, { preserveScroll: true });
    };

    const desaffecter = () => {
        const motif = window.prompt(t('planif.motif_desaffectation', 'Motif de la désaffectation (accident, panne, immobilisation…) :'));
        if (motif === null) return;
        if (motif.trim().length < 5) {
            window.alert(t('planif.motif_requis', 'Le motif est obligatoire (5 caractères minimum).'));
            return;
        }
        router.post(route('planning.desaffecter', ordre.id), { motif: motif.trim() }, { preserveScroll: true });
    };

    return (
        <div className="flex flex-wrap gap-2">
            {ordre.status === 'IN_PROGRESS' && (
                <button
                    type="button"
                    onClick={() => changer('DELIVERED', t('planif.confirmer_livre', 'Marquer l\'ordre :numero comme livré aujourd\'hui ? Ce changement est définitif.', { numero: ordre.tracking_number }))}
                    className="rounded-lg bg-status-delivered px-3 py-1.5 text-xs font-semibold text-white transition hover:opacity-90"
                >
                    {t('planif.marquer_livre', 'Marquer livré')}
                </button>
            )}
            {['ASSIGNED', 'IN_PROGRESS'].includes(ordre.status) && (
                <button
                    type="button"
                    onClick={onReaffecter}
                    className="rounded-lg border border-marine px-3 py-1.5 text-xs font-semibold text-marine transition hover:bg-marine/5"
                >
                    {t('planif.reaffecter', 'Réaffecter')}
                </button>
            )}
            {ordre.status === 'ASSIGNED' && (
                <button
                    type="button"
                    onClick={desaffecter}
                    className="rounded-lg border border-marine px-3 py-1.5 text-xs font-semibold text-marine transition hover:bg-marine/5"
                >
                    {t('planif.desaffecter', 'Désaffecter')}
                </button>
            )}
            {['PENDING', 'ASSIGNED', 'IN_PROGRESS'].includes(ordre.status) && (
                <button
                    type="button"
                    onClick={() => changer('CANCELLED', t('planif.confirmer_annulation', 'Annuler définitivement cet ordre ?'))}
                    className="rounded-lg border border-status-incident px-3 py-1.5 text-xs font-semibold text-status-incident transition hover:bg-status-incident/5"
                >
                    {t('action.annuler', 'Annuler')}
                </button>
            )}
        </div>
    );
}

const LIBELLE_CONTRAINTE = {
    adr: ['planif.contrainte_adr', 'Matières dangereuses'],
    hayon: ['planif.contrainte_hayon', 'Hayon requis'],
    etranger: ['planif.contrainte_etranger', 'Enlèvement à l\'étranger'],
};

export default function Index({
    orders, vehicles, drivers, couverture = {}, statut, compteurs,
    priorite = null, priorites = [],
    contrainte = null, contraintes = [],
    jour = null,
    q = '', suggestions = [],
}) {
    const t = useTraduction();
    const [enReaffectation, setEnReaffectation] = useState(null);
    const v = useVocabulaire();
    const locale = useLocale();

    const [champs, setChamps] = useState(q);
    const minuteur = useRef(null);

    const chercher = (valeur) => {
        setChamps(valeur);
        clearTimeout(minuteur.current);
        minuteur.current = setTimeout(() => {
            router.get(route('planning.index'), {
                status: statut,
                priorite,
                contrainte,
                jour: jour || undefined,
                q: valeur || undefined,
            }, {
                only: ['orders', 'priorites', 'contraintes', 'compteurs', 'suggestions', 'q'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 250);
    };

    const dateCourte = (valeur) => valeur
        ? new Date(valeur).toLocaleDateString(locale, { day: '2-digit', month: '2-digit', year: 'numeric' })
        : '—';

    // Le jour arrive en AAAA-MM-JJ : new Date() le lirait en UTC et
    // afficherait la veille sous un fuseau a l'ouest de Greenwich.
    const jourCourt = (valeur) => valeur.split('-').reverse().join('/');

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">{t('nav.planification', 'Planification')}</h1>}>
            <Head title={t('nav.planification', 'Planification')} />


            {}
            <div className="mb-4">
                <input
                    list="suggestions-planification"
                    value={champs}
                    onChange={(e) => chercher(e.target.value)}
                    placeholder={t('planif.chercher', 'Numéro, entreprise, ville de départ ou d\'arrivée…')}
                    aria-label={t('planif.chercher', 'Numéro, entreprise, ville de départ ou d\'arrivée…')}
                    className="w-full rounded-lg border-slate-200 bg-white py-2.5 px-4 text-sm shadow-sm focus:border-marine focus:ring-marine sm:max-w-xl"
                />
                <datalist id="suggestions-planification">
                    {suggestions.map((s) => <option key={s} value={s} />)}
                </datalist>

                {champs !== '' && (
                    <button
                        type="button"
                        onClick={() => chercher('')}
                        className="ml-3 text-xs text-brand-blue hover:underline"
                    >
                        {t('journal.reinitialiser', 'Réinitialiser les filtres')}
                    </button>
                )}
            </div>

            {jour && (
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-marine py-1 pl-3 pr-1.5 text-sm font-medium text-white">
                        {t('planif.enlevement_le', 'Enlèvement le :date', { date: jourCourt(jour) })}
                        <Link
                            href={route('planning.index', { status: statut, priorite, contrainte, q: champs || undefined })}
                            preserveScroll
                            aria-label={t('planif.retirer_filtre', 'Retirer ce filtre')}
                            title={t('planif.retirer_filtre', 'Retirer ce filtre')}
                            className="flex h-5 w-5 items-center justify-center rounded-full leading-none text-white/80 transition hover:bg-white/20 hover:text-white"
                        >
                            ×
                        </Link>
                    </span>
                </div>
            )}

            <div className="mb-5 flex flex-wrap gap-2">
                {Object.keys(LIBELLE_STATUT).map((cle) => (
                    <Link
                        key={cle}
                        href={route('planning.index', { status: cle, jour: jour || undefined, q: champs || undefined })}
                        preserveScroll
                        className={
                            'rounded-lg px-4 py-2 text-sm font-medium transition ' +
                            (statut === cle ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50')
                        }
                    >
                        {t(...LIBELLE_STATUT[cle])}
                        <span className="ml-2 text-xs opacity-70">{compteurs[cle] ?? 0}</span>
                    </Link>
                ))}
            </div>

            <div className="mb-3 flex flex-wrap items-center gap-2">
                <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">{t('commande.priorite', 'Priorité')}</span>
                <Link
                    href={route('planning.index', { status: statut, contrainte, jour: jour || undefined, q: champs || undefined })}
                    preserveScroll
                    className={
                        'rounded-full px-3 py-1 text-sm font-medium transition ' +
                        (priorite === null ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50')
                    }
                >
                    {t('planif.toutes', 'Toutes')}
                </Link>
                {priorites.map((p) => (
                    <Link
                        key={p.valeur}
                        href={route('planning.index', { status: statut, priorite: p.valeur, contrainte, jour: jour || undefined, q: champs || undefined })}
                        preserveScroll
                        className={
                            'rounded-full px-3 py-1 text-sm font-medium transition ' +
                            (priorite === p.valeur
                                ? 'bg-marine text-white'
                                : p.valeur === 'URGENT' && p.nombre > 0
                                    ? 'bg-status-incident/10 text-status-incident hover:bg-status-incident/20'
                                    : 'bg-white text-marine hover:bg-slate-50')
                        }
                    >
                        {t(...LIBELLE_PRIORITE[p.valeur])}
                        <span className="ml-1.5 text-xs opacity-70">{p.nombre}</span>
                    </Link>
                ))}
            </div>

            <div className="mb-5 flex flex-wrap items-center gap-2">
                <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">{t('planif.contrainte', 'Contrainte')}</span>
                <Link
                    href={route('planning.index', { status: statut, priorite, jour: jour || undefined, q: champs || undefined })}
                    preserveScroll
                    className={
                        'rounded-full px-3 py-1 text-sm font-medium transition ' +
                        (contrainte === null ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50')
                    }
                >
                    {t('planif.toutes', 'Toutes')}
                </Link>
                {contraintes.map((c) => (
                    <Link
                        key={c.valeur}
                        href={route('planning.index', { status: statut, priorite, contrainte: c.valeur, jour: jour || undefined, q: champs || undefined })}
                        preserveScroll
                        className={
                            'rounded-full px-3 py-1 text-sm font-medium transition ' +
                            (contrainte === c.valeur
                                ? 'bg-marine text-white'
                                : c.valeur === 'adr' && c.nombre > 0
                                    ? 'bg-status-incident/10 text-status-incident hover:bg-status-incident/20'
                                    : 'bg-white text-marine hover:bg-slate-50')
                        }
                    >
                        {t(...LIBELLE_CONTRAINTE[c.valeur])}
                        <span className="ml-1.5 text-xs opacity-70">{c.nombre}</span>
                    </Link>
                ))}
            </div>

            <div className="space-y-3">
                {orders.data.length === 0 && (
                    <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-600">
                        {t('planif.aucun_ordre', 'Aucun ordre dans cet état.')}
                    </p>
                )}

                {orders.data.map((ordre) => (
                    <div key={ordre.id} className="rounded-2xl bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link
                                        href={route('transport-orders.show', ordre.id)}
                                        className="font-semibold text-marine hover:underline"
                                    >
                                        {ordre.tracking_number}
                                    </Link>
                                    <span className={'rounded-full px-2.5 py-0.5 text-xs font-medium ' + COULEUR_STATUT[ordre.status]}>
                                        {t(...LIBELLE_STATUT[ordre.status])}
                                    </span>
                                    <span className={'text-xs ' + COULEUR_PRIORITE[ordre.priority]}>
                                        {t(...LIBELLE_PRIORITE[ordre.priority])}
                                    </span>
                                    {ordre.is_hazardous && (
                                        <span className="rounded-full bg-status-incident/10 px-2.5 py-0.5 text-xs font-medium text-status-incident">
                                            ADR
                                        </span>
                                    )}
                                    {ordre.needs_tail_lift && (
                                        <span className="rounded-full bg-brand-blue/10 px-2.5 py-0.5 text-xs font-medium text-brand-blue">
                                            {t('planif.contrainte_hayon', 'Hayon requis')}
                                        </span>
                                    )}
                                </div>
                                <p className="mt-1 text-sm text-slate-600">{ordre.client?.company_name}</p>
                                <p className="mt-1 text-xs text-slate-600">
                                    {ordre.pickup_address} → {ordre.delivery_address}
                                </p>
                                <p className="mt-1 text-xs text-slate-600">
                                    {Number(ordre.weight).toLocaleString(locale)} kg
                                    {ordre.volume ? ` · ${Number(ordre.volume).toLocaleString(locale)} m³` : ''}
                                    {ordre.distance_km ? ` · ${Number(ordre.distance_km).toLocaleString(locale)} km` : ''}
                                    {' · ' + t('planif.chargement', 'chargement') + ' '}{dateCourte(ordre.pickup_date)}
                                    {ordre.goods_type ? ` · ${v('marchandise', ordre.goods_type)}` : ''}
                                </p>
                            </div>
                            <BoutonsStatut ordre={ordre} onReaffecter={() => setEnReaffectation(ordre.id)} />
                        </div>

                        <div className="mt-4 border-t border-slate-100 pt-4">
                            {ordre.status === 'PENDING' ? (
                                <>
                                    <FretRetour ordre={ordre} />
                                    <LigneAffectation ordre={ordre} vehicles={vehicles} drivers={drivers} couverture={couverture} />
                                </>
                            ) : enReaffectation === ordre.id ? (
                                <LigneAffectation
                                    ordre={ordre}
                                    vehicles={vehicles}
                                    drivers={drivers}
                                    couverture={couverture}
                                    reaffectation
                                    onFermer={() => setEnReaffectation(null)}
                                />
                            ) : (
                                <div className="flex flex-wrap gap-6 text-xs text-slate-600">
                                    <span>
                                        <span className="text-slate-600">{t('ordres.vehicule', 'Véhicule')} : </span>
                                        {ordre.vehicle
                                            ? `${ordre.vehicle.registration} · ${ordre.vehicle.brand} ${ordre.vehicle.model}`
                                            : t('planif.non_affecte', 'non affecté')}
                                    </span>
                                    <span>
                                        <span className="text-slate-600">{t('suivi.chauffeur', 'Chauffeur')} : </span>
                                        {ordre.driver?.user
                                            ? `${ordre.driver.user.first_name} ${ordre.driver.user.last_name}`
                                            : t('planif.non_affecte', 'non affecté')}
                                    </span>
                                    {ordre.porteuse_info && <div className="basis-full"><FretRetour ordre={ordre} /></div>}
                                    {(ordre.retours_possibles ?? []).length > 0 && (
                                        <div className="basis-full rounded-lg bg-status-delivered/10 px-3 py-2 text-status-delivered">
                                            <p className="font-semibold">{t('planif.retours_possibles', ':n fret(s) retour possible(s) près de la livraison', { n: ordre.retours_possibles.length })}</p>
                                            <ul className="mt-1 list-disc pl-4">
                                                {ordre.retours_possibles.map((r) => (
                                                    <li key={r.numero}>{r.numero} · {r.ville} · {r.date} · {Number(r.poids).toLocaleString(locale)} kg · {r.approche_km} km</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                    {ordre.en_route_depuis && (
                                        <p className="basis-full rounded-lg bg-status-assigned/10 px-3 py-2 text-status-assigned" role="status">
                                            {t('planif.en_route_depuis', 'En route depuis le :date : la livraison n\'a pas été enregistrée. Tant qu\'elle ne l\'est pas, ce camion et ce chauffeur restent occupés.', { date: ordre.en_route_depuis })}
                                        </p>
                                    )}
                                    {(ordre.alertes ?? []).length > 0 && (
                                        <div className="basis-full rounded-lg bg-status-incident/10 px-3 py-2 text-status-incident" role="alert">
                                            <p className="font-semibold">{t('planif.non_conforme', 'Affectation non conforme : réaffectez cette mission.')}</p>
                                            <ul className="mt-1 list-disc pl-4">
                                                {ordre.alertes.map((alerte) => <li key={alerte}>{alerte}</li>)}
                                            </ul>
                                        </div>
                                    )}
                                    {ordre.actual_delivery_date && (
                                        <span>
                                            <span className="text-slate-600">{t('planif.livre_le', 'Livré le')} : </span>
                                            {dateCourte(ordre.actual_delivery_date)}
                                        </span>
                                    )}

                                    {}
                                    {['ASSIGNED', 'IN_PROGRESS'].includes(ordre.status) && (
                                        <button
                                            type="button"
                                            onClick={() => router.patch(route('planning.tracking', ordre.id), {}, { preserveScroll: true })}
                                            className={
                                                'ml-auto rounded-lg px-3 py-1 text-xs font-semibold transition ' +
                                                (ordre.suivi_direct
                                                    ? 'bg-brand-blue/10 text-brand-blue hover:bg-brand-blue/20'
                                                    : 'border border-slate-200 text-slate-600 hover:bg-surface')
                                            }
                                            title={t('planif.suivi_aide', 'Le chauffeur est averti sur son écran. Les positions s\'effacent après la livraison.')}
                                        >
                                            {ordre.suivi_direct
                                                ? t('planif.suivi_actif', 'Suivi de position activé')
                                                : t('planif.suivi_inactif', 'Activer le suivi de position')}
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            {orders.last_page > 1 && (
                <div className="mt-6 flex flex-wrap gap-1">
                    {orders.links.map((lien, i) => (
                        <Link
                            key={i}
                            href={lien.url ?? '#'}
                            preserveScroll
                            className={
                                'rounded-lg px-3 py-2 text-sm ' +
                                (lien.active ? 'bg-marine text-white' : lien.url ? 'bg-white text-marine hover:bg-slate-50' : 'bg-white text-slate-300')
                            }
                            dangerouslySetInnerHTML={{ __html: lien.label }}
                        />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
