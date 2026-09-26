import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction, useVocabulaire } from '@/traduire';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

const COULEUR = {
    PENDING: 'bg-status-pending/10 text-status-pending',
    PROCESSING: 'bg-status-progress/10 text-status-progress',
    QUOTED: 'bg-status-delivered/10 text-status-delivered',
    CLOSED: 'bg-slate-100 text-slate-600',
    ORDERED: 'bg-action/10 text-action-dark',
};

const LIBELLES_COLIS = {
    palette_europe: ['devis.colis_palette_europe', 'Palette Europe (120 × 80)'],
    palette_industrielle: ['devis.colis_palette_industrielle', 'Palette industrielle (120 × 100)'],
    demi_palette: ['devis.colis_demi_palette', 'Demi-palette (80 × 60)'],
    colis: ['devis.colis_colis', 'Colis'],
    caisse: ['devis.colis_caisse', 'Caisse'],
    rouleau: ['devis.colis_rouleau', 'Rouleau'],
    vrac: ['devis.colis_vrac', 'Vrac'],
    autre: ['devis.colis_autre', 'Autre'],
};

const LIBELLES_ACCES = {
    centre_ville: ['devis.acces_centre_ville', 'Centre-ville'],
    zone_basses_emissions: ['devis.acces_zbe', 'Zone de basses émissions'],
    limite_tonnage: ['devis.acces_tonnage', 'Limite de tonnage'],
    rue_etroite: ['devis.acces_rue_etroite', 'Rue étroite'],
    sans_stationnement: ['devis.acces_stationnement', 'Pas de stationnement pour un camion'],
};

const LIBELLES_CRENEAU = {
    matin: ['devis.creneau_matin', 'Le matin'],
    apres_midi: ['devis.creneau_apres_midi', 'L\'après-midi'],
    journee: ['devis.creneau_journee', 'Toute la journée'],
    indifferent: ['devis.indifferent', 'Indifférent'],
};

export default function Index({ demandes, statut, recherche, statuts, compteurs, libelles = {} }) {
    const t = useTraduction();
    const v = useVocabulaire();
    const locale = useLocale();
    const [champ, setChamp] = useState(recherche ?? '');
    const [traitement, setTraitement] = useState(null);
    const [ouverte, setOuverte] = useState(null);

    const commander = (d) => {
        if (! window.confirm(t('demandes.commander_confirmer', 'Créer la commande correspondante pour l\'entreprise cliente ? Le prix est calculé comme au formulaire de commande.'))) return;
        router.post(route('quotes.order', d.id), {}, { preserveScroll: true });
    };

    // Ce qu'on sait du lieu d'enlevement ou de livraison, en une ligne.
    const surPlace = (d, lieu) => [
        d[lieu + '_contact_name'] && (d[lieu + '_contact_name'] + (d[lieu + '_contact_phone'] ? ' · ' + d[lieu + '_contact_phone'] : '')),
        d[lieu + '_opening_hours'],
        d[lieu + '_time_slot'] && t(...LIBELLES_CRENEAU[d[lieu + '_time_slot']]),
        d[lieu + '_has_dock'] === true && t('devis.quai_oui', 'Oui, un quai'),
        d[lieu + '_has_dock'] === false && t('devis.quai_non', 'Non : hayon nécessaire'),
        d[lieu + '_appointment'] && t('devis.rendez_vous', 'Prise de rendez-vous obligatoire'),
        ...(d[lieu + '_access'] ?? []).map((a) => LIBELLES_ACCES[a] ? t(...LIBELLES_ACCES[a]) : a),
        d[lieu + '_access_notes'],
    ].filter(Boolean).join(' · ');
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

    // Les choix du formulaire sont ranges en francais : le serveur fournit
    // leur libelle dans la langue de l'ecran. Une ancienne valeur hors
    // liste s'affiche telle quelle.
    const choix = (valeur) => (valeur ? libelles[valeur] ?? valeur : valeur);

    const date = (valeur) => valeur
        ? new Date(valeur).toLocaleDateString(locale, { day: '2-digit', month: '2-digit', year: 'numeric' })
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
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">{t('nav.devis_demandes', 'Demandes de devis')}</h1>}>
            <Head title={t('nav.devis_demandes', 'Demandes de devis')} />


            <div className="mb-4 flex flex-wrap gap-2">
                {Object.entries(statuts).map(([cle, libelle]) => onglet(cle, libelle))}
                {onglet('tout', t('planif.toutes', 'Toutes'))}
            </div>

            <div className="mb-4 rounded-2xl bg-white p-5 shadow-sm">
                <input
                    value={champ}
                    onChange={(e) => chercher(e.target.value)}
                    placeholder={t('demandes.filtre', 'Référence, entreprise, contact, e-mail ou numéro de TVA')}
                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine sm:max-w-lg"
                />
            </div>

            <div className="space-y-3">
                {demandes.data.length === 0 && (
                    <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-600">
                        {t('demandes.aucune', 'Aucune demande dans cette catégorie.')}
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
                                    {t('demandes.recue_le', 'Reçue le')} {date(d.created_at)} · {choix(d.customer_type)}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {d.status === 'PENDING' && (
                                    <button
                                        type="button"
                                        onClick={() => ouvrir(d, 'PROCESSING')}
                                        className="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                    >
                                        {t('demandes.prendre_en_charge', 'Prendre en charge')}
                                    </button>
                                )}
                                {['PENDING', 'PROCESSING', 'QUOTED'].includes(d.status) && (
                                    <button
                                        type="button"
                                        onClick={() => commander(d)}
                                        className="rounded-lg bg-action px-4 py-2 text-sm font-semibold text-marine-deep transition hover:bg-action-dark"
                                    >
                                        {t('demandes.transformer', 'Transformer en commande')}
                                    </button>
                                )}
                                {d.commande && (
                                    <Link href={route('transport-orders.show', d.commande.id)} className="rounded-lg border border-action px-4 py-2 text-sm font-semibold text-action-dark">
                                        {d.commande.tracking_number}
                                    </Link>
                                )}
                                {(d.status === 'PENDING' || d.status === 'PROCESSING') && (
                                    <>
                                        <button
                                            type="button"
                                            onClick={() => ouvrir(d, 'QUOTED')}
                                            className="rounded-lg bg-status-delivered px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                        >
                                            {t('demandes.devis_transmis', 'Devis transmis')}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => ouvrir(d, 'CLOSED')}
                                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-surface"
                                        >
                                            {t('demandes.sans_suite', 'Sans suite')}
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>

                        <dl className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-4">
                            {ligne(t('nav.contact', 'Contact'), d.contact_name)}
                            {ligne(t('devis.email', 'Adresse e-mail'), d.email)}
                            {ligne(t('auth.telephone', 'Téléphone'), d.phone)}
                            {ligne(t('compte.numero_tva', 'Numéro de TVA'), d.vat_number)}
                            {ligne(t('demandes.enlevement', 'Enlèvement'), d.pickup_address)}
                            {ligne(t('demandes.livraison', 'Livraison'), d.delivery_address)}
                            {ligne(t('demandes.date_souhaitee', 'Date souhaitée'), date(d.pickup_date) + ' · ' + choix(d.date_flexibility))}
                            {ligne(t('devis.trajet', 'Trajet'), choix(d.trip_type) + ' · ' + choix(d.frequency))}
                            {ligne(t('devis.marchandise', 'Marchandise'), v('marchandise', d.goods_type) + (d.weight ? ' · ' + Number(d.weight).toLocaleString(locale) + ' kg' : ''))}
                            {ligne(t('commande.volume', 'Volume'), d.volume)}
                            {ligne(t('demandes.vehicule_souhaite', 'Véhicule souhaité'), choix(d.vehicle_type))}
                            {ligne(t('demandes.assurance', 'Assurance'), choix(d.insurance_value))}
                        </dl>

                        <button type="button" onClick={() => setOuverte(ouverte === d.id ? null : d.id)} className="mt-3 text-xs font-semibold text-brand-blue" aria-expanded={ouverte === d.id}>
                            {ouverte === d.id ? t('demandes.moins', 'Masquer le détail') : t('demandes.plus', 'Voir tout le détail')}
                        </button>

                        {ouverte === d.id && (
                            <dl className="mt-3 grid gap-3 rounded-xl bg-surface p-4 sm:grid-cols-2 lg:grid-cols-3">
                                {ligne(t('devis.forme_juridique', 'Forme juridique'), [d.legal_form, d.sector].filter(Boolean).join(' · '))}
                                {ligne(t('devis.eori', 'Numéro EORI'), d.eori_number)}
                                {ligne(t('devis.adresse_facturation', 'Adresse de facturation'), [d.billing_street, [d.billing_postal_code, d.billing_city].filter(Boolean).join(' '), d.billing_country].filter(Boolean).join(', '))}
                                {ligne(t('devis.fonction', 'Fonction'), d.contact_function)}
                                {ligne(t('devis.portable', 'Téléphone portable'), d.mobile_phone)}
                                {ligne(t('devis.email_facturation', 'E-mail de facturation'), d.billing_email)}
                                {ligne(t('devis.canal', 'Comment vous répondre ?'), (d.preferred_channel === 'phone' ? t('devis.canal_telephone', 'Par téléphone') : t('devis.canal_email', 'Par e-mail'))
                                    + (d.callback_slot && LIBELLES_CRENEAU[d.callback_slot] ? ' · ' + t(...LIBELLES_CRENEAU[d.callback_slot]) : '') + ' · ' + (d.correspondence_language ?? 'fr').toUpperCase())}
                                {ligne(t('devis.sur_place_enlevement', 'À l\'enlèvement'), surPlace(d, 'pickup'))}
                                {ligne(t('devis.sur_place_livraison', 'À la livraison'), surPlace(d, 'delivery'))}
                                {ligne(t('devis.date_livraison', 'Date de livraison souhaitée'), d.delivery_date ? date(d.delivery_date) : null)}
                                {ligne(t('devis.volume_mensuel', 'Volume prévu'), choix(d.monthly_volume))}
                                {ligne(t('devis.valeur_declaree', 'Valeur de la marchandise (€ HT)'), d.declared_value ? Number(d.declared_value).toLocaleString(locale) + ' €' : null)}
                                {ligne(t('devis.budget', 'Budget indicatif (€ HT)'), d.budget ? Number(d.budget).toLocaleString(locale) + ' €' : null)}
                                {ligne(t('devis.reponse_avant', 'Réponse souhaitée avant le'), d.response_deadline ? date(d.response_deadline) : null)}
                                {d.needs_temperature && ligne(t('devis.temperature', 'Température dirigée'), `${d.temperature_min} °C → ${d.temperature_max} °C`)}
                                {d.is_hazardous && ligne('ADR', [d.un_number && 'ONU ' + d.un_number, d.adr_class && t('devis.classe_adr', 'Classe ADR') + ' ' + d.adr_class, d.packing_group && t('devis.groupe_emballage', 'Groupe d\'emballage') + ' ' + d.packing_group].filter(Boolean).join(' · '))}
                                {(d.packages ?? []).length > 0 && (
                                    <div className="sm:col-span-2 lg:col-span-3">
                                        <dt className="text-xs uppercase tracking-wide text-slate-600">{t('devis.colis_titre', 'Colis et palettes')}</dt>
                                        <dd className="text-sm text-marine">
                                            <ul className="list-disc pl-4">
                                                {d.packages.map((c, i) => (
                                                    <li key={i}>
                                                        {c.quantite} × {LIBELLES_COLIS[c.type] ? t(...LIBELLES_COLIS[c.type]) : c.type}
                                                        {c.longueur && c.largeur ? ` · ${c.longueur} × ${c.largeur}${c.hauteur ? ' × ' + c.hauteur : ''} cm` : ''}
                                                        {c.poids_unitaire ? ` · ${c.poids_unitaire} kg` : ''}
                                                        {c.empilable === false || c.empilable === '0' ? ' · ' + t('demandes.non_empilable', 'non empilable') : ''}
                                                    </li>
                                                ))}
                                            </ul>
                                        </dd>
                                    </div>
                                )}
                                {(d.attachments ?? []).length > 0 && (
                                    <div className="sm:col-span-2 lg:col-span-3">
                                        <dt className="text-xs uppercase tracking-wide text-slate-600">{t('devis.pieces_jointes', 'Pièces jointes')}</dt>
                                        <dd className="mt-1 flex flex-wrap gap-2">
                                            {d.attachments.map((p, i) => (
                                                <a key={i} href={route('quotes.piece', { quoteRequest: d.id, rang: i })} className="rounded-lg bg-white px-3 py-1 text-xs font-semibold text-brand-blue shadow-sm hover:underline">
                                                    {p.nom} ({Math.max(1, Math.round((p.taille ?? 0) / 1024)).toLocaleString(locale)} Ko)
                                                </a>
                                            ))}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        )}

                        {(d.needs_tail_lift || d.is_hazardous || d.needs_express || d.needs_ecmr || d.needs_temperature) && (
                            <p className="mt-3 flex flex-wrap gap-2">
                                {d.needs_tail_lift && <span className="rounded-full bg-action/10 px-3 py-1 text-xs font-medium text-action-dark">{t('devis.hayon', 'Hayon élévateur')}</span>}
                                {d.is_hazardous && <span className="rounded-full bg-status-incident/10 px-3 py-1 text-xs font-medium text-status-incident">ADR</span>}
                                {d.needs_express && <span className="rounded-full bg-status-progress/10 px-3 py-1 text-xs font-medium text-status-progress">Express</span>}
                                {d.needs_ecmr && <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">e-CMR</span>}
                                {d.needs_temperature && <span className="rounded-full bg-brand-blue/10 px-3 py-1 text-xs font-medium text-brand-blue">{t('devis.temperature', 'Température dirigée')}</span>}
                            </p>
                        )}

                        {d.special_instructions && (
                            <p className="mt-3 rounded-lg bg-surface px-3 py-2 text-xs text-slate-600">
                                {t('demandes.consignes', 'Consignes :')} {d.special_instructions}
                            </p>
                        )}

                        {d.internal_note && (
                            <p className="mt-3 rounded-lg bg-brand-blue/5 px-3 py-2 text-xs text-brand-blue">
                                {t('demandes.note_interne', 'Note interne :')} {d.internal_note}
                                {d.handler && ` — ${d.handler.first_name} ${d.handler.last_name}, ${t('entreprises.le', 'le')} ${date(d.handled_at)}`}
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
                        {traitement?.company_name}. {t('demandes.note_privee', 'La note reste interne, elle n\'est jamais envoyée au demandeur.')}
                    </p>

                    <textarea
                        value={data.internal_note}
                        onChange={(e) => setData('internal_note', e.target.value)}
                        rows="3"
                        placeholder={t('demandes.note_ex', 'Ex : client rappelé, chiffrage en cours sur base d\'un semi-remorque.')}
                        className="mt-4 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                    />
                    {errors.internal_note && <p className="mt-1 text-sm text-status-incident">{errors.internal_note}</p>}

                    <div className="mt-4 flex justify-end gap-2">
                        <button type="button" onClick={() => setTraitement(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button disabled={processing} className="rounded-lg bg-marine px-4 py-2 text-sm font-semibold text-white transition hover:bg-marine-deep disabled:opacity-50">
                            {t('action.enregistrer', 'Enregistrer')}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
