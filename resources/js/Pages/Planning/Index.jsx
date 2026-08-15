import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const LIBELLE_STATUT = {
    PENDING: 'En attente',
    IN_PROGRESS: 'En cours',
    DELIVERED: 'Livré',
    CANCELLED: 'Annulé',
};

const COULEUR_STATUT = {
    PENDING: 'bg-status-pending/10 text-status-pending',
    IN_PROGRESS: 'bg-status-progress/10 text-status-progress',
    DELIVERED: 'bg-status-delivered/10 text-status-delivered',
    CANCELLED: 'bg-status-incident/10 text-status-incident',
};

const LIBELLE_PRIORITE = { LOW: 'Basse', NORMAL: 'Normale', HIGH: 'Haute', URGENT: 'Urgente' };

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

function LigneAffectation({ ordre, vehicles, drivers }) {
    const { data, setData, post, processing, errors } = useForm({
        vehicle_registration: '',
        driver_id: '',
    });

    const affecter = (e) => {
        e.preventDefault();
        post(route('planning.assign', ordre.id), { preserveScroll: true });
    };

    const capaciteSuffisante = (v) => Number(v.capacity_tonnes) * 1000 >= Number(ordre.weight);
    const hayonSuffisant = (v) => ! ordre.needs_tail_lift || v.has_tail_lift;
    const vehiculeCompatible = (v) => capaciteSuffisante(v) && hayonSuffisant(v);
    const motifRefus = (v) => {
        if (! capaciteSuffisante(v)) return ' (capacité insuffisante)';
        if (! hayonSuffisant(v)) return ' (sans hayon)';

        return '';
    };
    const chauffeurApte = (d) => (d.empechements ?? []).length === 0;
    const chauffeurCompatible = (d) => chauffeurApte(d) && (! ordre.is_hazardous || d.adr_certified);
    const motifChauffeur = (d) => {
        if (! chauffeurApte(d)) return ' (' + d.empechements[0] + ')';
        if (ordre.is_hazardous && ! d.adr_certified) return ' (ADR requis)';

        return '';
    };
    const selectCls = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine';

    // Les exigences etaient affichees en tete de carte, les listes tout en
    // bas : au moment de choisir le planificateur ne les avait plus sous les
    // yeux. Elles sont rappelees ici, avec le nombre de candidats retenus.
    const camionsOk = vehicles.filter(vehiculeCompatible).length;
    const chauffeursOk = drivers.filter(chauffeurCompatible).length;
    const pastille = 'rounded-full px-2.5 py-0.5 font-medium';

    return (
        <>
        <div className="mb-3 flex flex-wrap items-center gap-2 text-xs">
            <span className="font-semibold uppercase tracking-wide text-slate-600">Besoins</span>

            <span className={pastille + ' bg-surface text-marine'}>
                Charge utile ≥ {(Number(ordre.weight) / 1000).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} t
            </span>

            {ordre.conduite && (
                <span className={pastille + ' bg-surface text-marine'}>
                    {ordre.conduite}
                </span>
            )}

            {ordre.is_hazardous && (
                <span className={pastille + ' bg-status-incident/10 text-status-incident'}>
                    Chauffeur certifié ADR
                </span>
            )}

            {ordre.needs_tail_lift && (
                <span className={pastille + ' bg-brand-blue/10 text-brand-blue'}>
                    Hayon élévateur
                </span>
            )}

            <span className={camionsOk === 0 || chauffeursOk === 0 ? 'font-semibold text-status-incident' : 'text-slate-600'}>
                {camionsOk} camion{camionsOk > 1 ? 's' : ''} · {chauffeursOk} chauffeur{chauffeursOk > 1 ? 's' : ''} compatibles
            </span>
        </div>

        <form onSubmit={affecter} className="flex flex-col gap-2 sm:flex-row sm:items-start">
            <div className="flex-1">
                <select
                    value={data.vehicle_registration}
                    onChange={(e) => setData('vehicle_registration', e.target.value)}
                    className={selectCls}
                    required
                >
                    <option value="">— Véhicule —</option>
                    {vehicles.map((v) => (
                        <option key={v.registration} value={v.registration} disabled={! vehiculeCompatible(v)}>
                            {v.registration} · {v.brand} {v.model} · {Number(v.capacity_tonnes).toLocaleString('fr-FR')} t
                            {v.has_tail_lift ? ' · hayon' : ''}
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
                    <option value="">— Chauffeur —</option>
                    {drivers.map((d) => (
                        <option key={d.id} value={d.id} disabled={! chauffeurCompatible(d)}>
                            {d.nom} · permis {d.license_type}{d.adr_certified ? ' · ADR' : ''}
                            {d.conduite_semaine > 0 ? ` · ${enHeures(d.conduite_semaine)} cette semaine` : ''}
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
                Affecter
            </button>
        </form>
        </>
    );
}

function BoutonsStatut({ ordre }) {
    const changer = (statut, confirmation) => {
        if (confirmation && ! window.confirm(confirmation)) return;
        router.patch(route('planning.status', ordre.id), { status: statut }, { preserveScroll: true });
    };

    return (
        <div className="flex flex-wrap gap-2">
            {ordre.status === 'IN_PROGRESS' && (
                <button
                    type="button"
                    onClick={() => changer('DELIVERED')}
                    className="rounded-lg bg-status-delivered px-3 py-1.5 text-xs font-semibold text-white transition hover:opacity-90"
                >
                    Marquer livré
                </button>
            )}
            {['PENDING', 'IN_PROGRESS'].includes(ordre.status) && (
                <button
                    type="button"
                    onClick={() => changer('CANCELLED', 'Annuler définitivement cet ordre ?')}
                    className="rounded-lg border border-status-incident px-3 py-1.5 text-xs font-semibold text-status-incident transition hover:bg-status-incident/5"
                >
                    Annuler
                </button>
            )}
        </div>
    );
}

const LIBELLE_CONTRAINTE = { adr: 'Matières dangereuses', hayon: 'Hayon requis' };

export default function Index({
    orders, vehicles, drivers, statut, compteurs,
    priorite = null, priorites = [],
    contrainte = null, contraintes = [],
}) {
    const flash = usePage().props.flash ?? {};

    const dateCourte = (valeur) => valeur
        ? new Date(valeur).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
        : '—';

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">Planification</h1>}>
            <Head title="Planification" />

            {flash.success && (
                <div className="mb-4 rounded-lg bg-status-delivered/10 px-4 py-3 text-sm font-medium text-status-delivered">
                    {flash.success}
                </div>
            )}

            <div className="mb-5 flex flex-wrap gap-2">
                {Object.keys(LIBELLE_STATUT).map((cle) => (
                    <Link
                        key={cle}
                        href={route('planning.index', { status: cle })}
                        preserveScroll
                        className={
                            'rounded-lg px-4 py-2 text-sm font-medium transition ' +
                            (statut === cle ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50')
                        }
                    >
                        {LIBELLE_STATUT[cle]}
                        <span className="ml-2 text-xs opacity-70">{compteurs[cle] ?? 0}</span>
                    </Link>
                ))}
            </div>

            <div className="mb-3 flex flex-wrap items-center gap-2">
                <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Priorité</span>
                <Link
                    href={route('planning.index', { status: statut, contrainte })}
                    preserveScroll
                    className={
                        'rounded-full px-3 py-1 text-sm font-medium transition ' +
                        (priorite === null ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50')
                    }
                >
                    Toutes
                </Link>
                {priorites.map((p) => (
                    <Link
                        key={p.valeur}
                        href={route('planning.index', { status: statut, priorite: p.valeur, contrainte })}
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
                        {LIBELLE_PRIORITE[p.valeur]}
                        <span className="ml-1.5 text-xs opacity-70">{p.nombre}</span>
                    </Link>
                ))}
            </div>

            <div className="mb-5 flex flex-wrap items-center gap-2">
                <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Contrainte</span>
                <Link
                    href={route('planning.index', { status: statut, priorite })}
                    preserveScroll
                    className={
                        'rounded-full px-3 py-1 text-sm font-medium transition ' +
                        (contrainte === null ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50')
                    }
                >
                    Toutes
                </Link>
                {contraintes.map((c) => (
                    <Link
                        key={c.valeur}
                        href={route('planning.index', { status: statut, priorite, contrainte: c.valeur })}
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
                        {LIBELLE_CONTRAINTE[c.valeur]}
                        <span className="ml-1.5 text-xs opacity-70">{c.nombre}</span>
                    </Link>
                ))}
            </div>

            <div className="space-y-3">
                {orders.data.length === 0 && (
                    <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-600">
                        Aucun ordre {LIBELLE_STATUT[statut].toLowerCase()}.
                    </p>
                )}

                {orders.data.map((ordre) => (
                    <div key={ordre.id} className="rounded-2xl bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-semibold text-marine">{ordre.tracking_number}</span>
                                    <span className={'rounded-full px-2.5 py-0.5 text-xs font-medium ' + COULEUR_STATUT[ordre.status]}>
                                        {LIBELLE_STATUT[ordre.status]}
                                    </span>
                                    <span className={'text-xs ' + COULEUR_PRIORITE[ordre.priority]}>
                                        {LIBELLE_PRIORITE[ordre.priority]}
                                    </span>
                                    {ordre.is_hazardous && (
                                        <span className="rounded-full bg-status-incident/10 px-2.5 py-0.5 text-xs font-medium text-status-incident">
                                            ADR
                                        </span>
                                    )}
                                    {ordre.needs_tail_lift && (
                                        <span className="rounded-full bg-brand-blue/10 px-2.5 py-0.5 text-xs font-medium text-brand-blue">
                                            Hayon requis
                                        </span>
                                    )}
                                </div>
                                <p className="mt-1 text-sm text-slate-600">{ordre.client?.company_name}</p>
                                <p className="mt-1 text-xs text-slate-600">
                                    {ordre.pickup_address} → {ordre.delivery_address}
                                </p>
                                <p className="mt-1 text-xs text-slate-600">
                                    {Number(ordre.weight).toLocaleString('fr-FR')} kg
                                    {ordre.distance_km ? ` · ${Number(ordre.distance_km).toLocaleString('fr-FR')} km` : ''}
                                    {' · chargement '}{dateCourte(ordre.pickup_date)}
                                    {ordre.goods_type ? ` · ${ordre.goods_type}` : ''}
                                </p>
                            </div>
                            <BoutonsStatut ordre={ordre} />
                        </div>

                        <div className="mt-4 border-t border-slate-100 pt-4">
                            {ordre.status === 'PENDING' ? (
                                <LigneAffectation ordre={ordre} vehicles={vehicles} drivers={drivers} />
                            ) : (
                                <div className="flex flex-wrap gap-6 text-xs text-slate-600">
                                    <span>
                                        <span className="text-slate-600">Véhicule : </span>
                                        {ordre.vehicle
                                            ? `${ordre.vehicle.registration} · ${ordre.vehicle.brand} ${ordre.vehicle.model}`
                                            : 'non affecté'}
                                    </span>
                                    <span>
                                        <span className="text-slate-600">Chauffeur : </span>
                                        {ordre.driver?.user
                                            ? `${ordre.driver.user.first_name} ${ordre.driver.user.last_name}`
                                            : 'non affecté'}
                                    </span>
                                    {ordre.actual_delivery_date && (
                                        <span>
                                            <span className="text-slate-600">Livré le : </span>
                                            {dateCourte(ordre.actual_delivery_date)}
                                        </span>
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
