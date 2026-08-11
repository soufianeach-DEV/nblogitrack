import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

const COULEUR = {
    PENDING: 'bg-status-pending/10 text-status-pending',
    PROCESSING: 'bg-status-progress/10 text-status-progress',
    QUOTED: 'bg-status-delivered/10 text-status-delivered',
    CLOSED: 'bg-slate-100 text-slate-600',
};

export default function Index({ demandes, statut, recherche, statuts, compteurs }) {
    const flash = usePage().props.flash ?? {};
    const [champ, setChamp] = useState(recherche ?? '');
    const [traitement, setTraitement] = useState(null);
    const minuteur = useRef(null);

    const { data, setData, patch, processing, errors, reset } = useForm({
        status: 'PROCESSING',
        internal_note: '',
    });

    const chercher = (valeur) => {
        setChamp(valeur);
        clearTimeout(minuteur.current);
        minuteur.current = setTimeout(() => {
            router.get(route('quotes.index'), { statut, q: valeur }, {
                only: ['demandes', 'recherche'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 250);
    };

    const ouvrir = (demande, statutVise) => {
        reset();
        setData({ status: statutVise, internal_note: demande.internal_note ?? '' });
        setTraitement(demande);
    };

    const enregistrer = (e) => {
        e.preventDefault();
        patch(route('quotes.status', traitement.id), {
            preserveScroll: true,
            onSuccess: () => setTraitement(null),
        });
    };

    const date = (valeur) => valeur
        ? new Date(valeur).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
        : '—';

    const ligne = (libelle, valeur) => (
        <div>
            <dt className="text-xs uppercase tracking-wide text-slate-600">{libelle}</dt>
            <dd className="text-sm text-marine">{valeur || '—'}</dd>
        </div>
    );

    const onglet = (cle, libelle) => (
        <Link
            key={cle}
            href={route('quotes.index', { statut: cle, q: champ })}
            preserveScroll
            className={
                'rounded-lg px-4 py-2 text-sm font-medium transition ' +
                (statut === cle ? 'bg-marine text-white' : 'bg-white text-marine hover:bg-slate-50')
            }
        >
            {libelle}
            <span className="ml-2 text-xs opacity-70">
                {cle === 'tout'
                    ? Object.values(compteurs).reduce((a, b) => a + b, 0)
                    : compteurs[cle] ?? 0}
            </span>
        </Link>
    );

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">Demandes de devis</h1>}>
            <Head title="Demandes de devis" />

            {flash.success && (
                <div className="mb-4 rounded-lg bg-status-delivered/10 px-4 py-3 text-sm font-medium text-status-delivered">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap gap-2">
                {Object.entries(statuts).map(([cle, libelle]) => onglet(cle, libelle))}
                {onglet('tout', 'Toutes')}
            </div>

            <div className="mb-4 rounded-2xl bg-white p-5 shadow-sm">
                <input
                    value={champ}
                    onChange={(e) => chercher(e.target.value)}
                    placeholder="Référence, entreprise, contact, e-mail ou numéro de TVA"
                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine sm:max-w-lg"
                />
            </div>

            <div className="space-y-3">
                {demandes.data.length === 0 && (
                    <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-600">
                        Aucune demande dans cette catégorie.
                    </p>
                )}

                {demandes.data.map((d) => (
                    <article key={d.id} className="rounded-2xl bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-mono text-sm text-brand-blue">{d.reference}</span>
                                    <span className={'rounded-full px-3 py-0.5 text-xs font-medium ' + (COULEUR[d.status] ?? '')}>
                                        {statuts[d.status]}
                                    </span>
                                </div>
                                <h2 className="mt-1 text-lg font-bold text-marine">{d.company_name}</h2>
                                <p className="text-xs text-slate-600">
                                    Reçue le {date(d.created_at)} · {d.customer_type}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {d.status === 'PENDING' && (
                                    <button
                                        type="button"
                                        onClick={() => ouvrir(d, 'PROCESSING')}
                                        className="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                    >
                                        Prendre en charge
                                    </button>
                                )}
                                {(d.status === 'PENDING' || d.status === 'PROCESSING') && (
                                    <>
                                        <button
                                            type="button"
                                            onClick={() => ouvrir(d, 'QUOTED')}
                                            className="rounded-lg bg-status-delivered px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                        >
                                            Devis transmis
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => ouvrir(d, 'CLOSED')}
                                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-surface"
                                        >
                                            Sans suite
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>

                        <dl className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-4">
                            {ligne('Contact', d.contact_name)}
                            {ligne('E-mail', d.email)}
                            {ligne('Téléphone', d.phone)}
                            {ligne('Numéro de TVA', d.vat_number)}
                            {ligne('Enlèvement', d.pickup_address)}
                            {ligne('Livraison', d.delivery_address)}
                            {ligne('Date souhaitée', date(d.pickup_date) + ' · ' + d.date_flexibility)}
                            {ligne('Trajet', d.trip_type + ' · ' + d.frequency)}
                            {ligne('Marchandise', d.goods_type + (d.weight ? ' · ' + Number(d.weight).toLocaleString('fr-FR') + ' kg' : ''))}
                            {ligne('Volume', d.volume)}
                            {ligne('Véhicule souhaité', d.vehicle_type)}
                            {ligne('Assurance', d.insurance_value)}
                        </dl>

                        {(d.needs_tail_lift || d.is_hazardous || d.needs_express || d.needs_ecmr) && (
                            <p className="mt-3 flex flex-wrap gap-2">
                                {d.needs_tail_lift && <span className="rounded-full bg-action/10 px-3 py-1 text-xs font-medium text-action-dark">Hayon élévateur</span>}
                                {d.is_hazardous && <span className="rounded-full bg-status-incident/10 px-3 py-1 text-xs font-medium text-status-incident">ADR</span>}
                                {d.needs_express && <span className="rounded-full bg-status-progress/10 px-3 py-1 text-xs font-medium text-status-progress">Express</span>}
                                {d.needs_ecmr && <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">e-CMR</span>}
                            </p>
                        )}

                        {d.special_instructions && (
                            <p className="mt-3 rounded-lg bg-surface px-3 py-2 text-xs text-slate-600">
                                Consignes : {d.special_instructions}
                            </p>
                        )}

                        {d.internal_note && (
                            <p className="mt-3 rounded-lg bg-brand-blue/5 px-3 py-2 text-xs text-brand-blue">
                                Note interne : {d.internal_note}
                                {d.handler && ` — ${d.handler.first_name} ${d.handler.last_name}, le ${date(d.handled_at)}`}
                            </p>
                        )}
                    </article>
                ))}
            </div>

            {demandes.last_page > 1 && (
                <div className="mt-6 flex flex-wrap gap-1">
                    {demandes.links.map((lien, i) => (
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

            <Modal show={traitement !== null} onClose={() => setTraitement(null)} maxWidth="lg">
                <form onSubmit={enregistrer} className="p-6">
                    <h2 className="text-lg font-bold text-marine">
                        {statuts[data.status]} — {traitement?.reference}
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {traitement?.company_name}. La note reste interne, elle n'est jamais envoyée au demandeur.
                    </p>

                    <textarea
                        value={data.internal_note}
                        onChange={(e) => setData('internal_note', e.target.value)}
                        rows="3"
                        placeholder="Ex : client rappelé, chiffrage en cours sur base d'un semi-remorque."
                        className="mt-4 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                    />
                    {errors.internal_note && <p className="mt-1 text-sm text-status-incident">{errors.internal_note}</p>}

                    <div className="mt-4 flex justify-end gap-2">
                        <button type="button" onClick={() => setTraitement(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            Annuler
                        </button>
                        <button disabled={processing} className="rounded-lg bg-marine px-4 py-2 text-sm font-semibold text-white transition hover:bg-marine-deep disabled:opacity-50">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
