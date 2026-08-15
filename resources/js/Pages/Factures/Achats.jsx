import BarreFiltres from '@/Components/BarreFiltres';
import Modal from '@/Components/Modal';
import OngletsFacturation from '@/Components/OngletsFacturation';
// useLocale et useTraduction sont importes plus bas avec le layout.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const ETATS = {
    TO_PAY: { cle: 'achats.a_payer', libelle: 'À payer', classe: 'bg-brand-blue/10 text-brand-blue' },
    PAID: { cle: 'statut.payee', libelle: 'Payée', classe: 'bg-status-delivered/10 text-status-delivered' },
    OVERDUE: { cle: 'statut.en_retard', libelle: 'En retard', classe: 'bg-status-incident/10 text-status-incident' },
};

const dernierJour = (mois) => {
    const [annee, numero] = mois.split('-').map(Number);
    return `${mois}-${String(new Date(annee, numero, 0).getDate()).padStart(2, '0')}`;
};

function Encodage({ categories, vehicules, fournisseurs, onFermer }) {
    const t = useTraduction();
    const locale = useLocale();
    const euros = (montant) => Number(montant).toLocaleString(locale, { style: 'currency', currency: 'EUR' });
    const moisPasse = new Date();
    moisPasse.setMonth(moisPasse.getMonth() - 1);
    const moisDefaut = `${moisPasse.getFullYear()}-${String(moisPasse.getMonth() + 1).padStart(2, '0')}`;

    const { data, setData, post, processing, errors, transform } = useForm({
        supplier_name: '',
        reference: '',
        category: 'CARBURANT',
        vehicle_registration: '',
        mois: moisDefaut,
        period_start: '',
        period_end: '',
        issued_on: new Date().toISOString().slice(0, 10),
        due_on: '',
        liters: '',
        taxed_km: '',
        amount_excl_tax: '',
        vat_rate: '21',
        vat_deductible: true,
    });

    const enregistrer = (e) => {
        e.preventDefault();

        // La periode et l'echeance se deduisent du mois et de l'emission :
        // trente jours, le delai legal par defaut.
        transform((donnees) => {
            const d = new Date(donnees.issued_on);
            d.setDate(d.getDate() + 30);

            return {
                ...donnees,
                period_start: `${donnees.mois}-01`,
                period_end: dernierJour(donnees.mois),
                due_on: donnees.due_on || d.toISOString().slice(0, 10),
            };
        });

        post(route('purchases.store'), {
            preserveScroll: true,
            onSuccess: onFermer,
        });
    };

    const choisirCategorie = (valeur) => {
        setData((precedent) => ({
            ...precedent,
            category: valeur,
            // La taxe kilometrique belge est hors champ TVA ; le carburant
            // d'un camion se deduit en entier.
            vat_rate: valeur === 'PEAGE' ? '0' : '21',
            vat_deductible: valeur !== 'PEAGE',
        }));
    };

    const ht = Number(data.amount_excl_tax) || 0;
    const tva = ht * Number(data.vat_rate) / 100;
    const champ = 'mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine';
    const intitule = 'text-xs uppercase tracking-wide text-slate-600';

    return (
        <form onSubmit={enregistrer} className="p-6">
            <h2 className="text-lg font-bold text-marine">{t('achats.encoder', 'Encoder une facture fournisseur')}</h2>

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label htmlFor="fournisseur" className={intitule}>{t('achats.fournisseur', 'Fournisseur')}</label>
                    <input
                        id="fournisseur"
                        list="liste-fournisseurs"
                        value={data.supplier_name}
                        onChange={(e) => setData('supplier_name', e.target.value)}
                        className={champ}
                        required
                    />
                    <datalist id="liste-fournisseurs">
                        {fournisseurs.map((f) => <option key={f} value={f} />)}
                    </datalist>
                    {errors.supplier_name && <p className="mt-1 text-xs text-status-incident">{errors.supplier_name}</p>}
                </div>
                <div>
                    <label htmlFor="reference" className={intitule}>{t('achats.reference', 'Référence de la facture')}</label>
                    <input
                        id="reference"
                        value={data.reference}
                        onChange={(e) => setData('reference', e.target.value)}
                        className={champ}
                        required
                    />
                    {errors.reference && <p className="mt-1 text-xs text-status-incident">{errors.reference}</p>}
                </div>
                <div>
                    <label htmlFor="categorie" className={intitule}>{t('personnel.categorie', 'Catégorie')}</label>
                    <select id="categorie" value={data.category} onChange={(e) => choisirCategorie(e.target.value)} className={champ}>
                        {Object.entries(categories).map(([valeur, libelle]) => (
                            <option key={valeur} value={valeur}>{libelle}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="vehicule" className={intitule}>{t('ordres.vehicule', 'Véhicule')}</label>
                    <select
                        id="vehicule"
                        value={data.vehicle_registration}
                        onChange={(e) => setData('vehicle_registration', e.target.value)}
                        className={champ}
                        required
                    >
                        <option value="">{t('commande.choisir', '— Choisir —')}</option>
                        {vehicules.map((v) => <option key={v.valeur} value={v.valeur}>{v.libelle}</option>)}
                    </select>
                    {errors.vehicle_registration && <p className="mt-1 text-xs text-status-incident">{errors.vehicle_registration}</p>}
                </div>
                <div>
                    <label htmlFor="mois" className={intitule}>{t('achats.periode', 'Période facturée')}</label>
                    <input
                        id="mois"
                        type="month"
                        value={data.mois}
                        onChange={(e) => setData('mois', e.target.value)}
                        className={champ}
                        required
                    />
                </div>
                <div>
                    <label htmlFor="emission" className={intitule}>{t('facture.emise_le', 'Émise le')}</label>
                    <input
                        id="emission"
                        type="date"
                        value={data.issued_on}
                        onChange={(e) => setData('issued_on', e.target.value)}
                        className={champ}
                        required
                    />
                </div>
                {data.category === 'CARBURANT' ? (
                    <div>
                        <label htmlFor="litres" className={intitule}>{t('achats.litres', 'Litres (vide pour GNC)')}</label>
                        <input
                            id="litres"
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.liters}
                            onChange={(e) => setData('liters', e.target.value)}
                            className={champ}
                        />
                    </div>
                ) : (
                    <div>
                        <label htmlFor="km" className={intitule}>{t('achats.km_taxes', 'Kilomètres taxés')}</label>
                        <input
                            id="km"
                            type="number"
                            step="0.1"
                            min="0"
                            value={data.taxed_km}
                            onChange={(e) => setData('taxed_km', e.target.value)}
                            className={champ}
                        />
                    </div>
                )}
                <div>
                    <label htmlFor="ht" className={intitule}>{t('achats.montant_ht', 'Montant hors TVA')}</label>
                    <input
                        id="ht"
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.amount_excl_tax}
                        onChange={(e) => setData('amount_excl_tax', e.target.value)}
                        className={champ}
                        required
                    />
                    {errors.amount_excl_tax && <p className="mt-1 text-xs text-status-incident">{errors.amount_excl_tax}</p>}
                </div>
                <div>
                    <label htmlFor="taux" className={intitule}>{t('achats.taux_tva', 'Taux de TVA')}</label>
                    <select id="taux" value={data.vat_rate} onChange={(e) => setData('vat_rate', e.target.value)} className={champ}>
                        <option value="0">{t('achats.taux_zero', '0 % — hors champ ou exonéré')}</option>
                        <option value="6">6 %</option>
                        <option value="12">12 %</option>
                        <option value="21">21 %</option>
                    </select>
                </div>
                <label className="flex items-end gap-2 pb-2">
                    <input
                        type="checkbox"
                        checked={data.vat_deductible}
                        disabled={Number(data.vat_rate) === 0}
                        onChange={(e) => setData('vat_deductible', e.target.checked)}
                        className="rounded border-slate-300 text-marine focus:ring-marine"
                    />
                    <span className="text-sm text-slate-600">{t('tva.deductible', 'TVA déductible')}</span>
                </label>
            </div>

            <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                <p className="text-sm text-slate-600">
                    {t('facture.tva', 'TVA')} {euros(tva)} — <span className="font-bold text-marine">{t('achats.ttc', 'TTC')} {euros(ht + tva)}</span>
                </p>
                <div className="flex gap-3">
                    <button type="button" onClick={onFermer} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-surface">
                        {t('action.annuler', 'Annuler')}
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-marine px-5 py-2 text-sm font-bold text-white transition hover:bg-marine-deep disabled:opacity-50"
                    >
                        {processing ? t('action.enregistrement', 'Enregistrement…') : t('action.enregistrer', 'Enregistrer')}
                    </button>
                </div>
            </div>
        </form>
    );
}

export default function Achats({ achats, compteurs, cartes, categories, vehicules = [], fournisseurs = [], filtres = {} }) {
    const t = useTraduction();
    const locale = useLocale();
    const euros = (montant) => Number(montant).toLocaleString(locale, { style: 'currency', currency: 'EUR' });
    const [encoder, setEncoder] = useState(false);
    const flash = usePage().props.flash ?? {};

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-marine">{t('nav.facturation', 'Facturation')}</h1>
                        <p className="text-sm text-slate-600">{t('achats.intro', 'Les factures des fournisseurs, rattachées au parc.')}</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setEncoder(true)}
                        className="rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-marine transition hover:bg-action-dark"
                    >
                        + {t('achats.encoder_court', 'Encoder une facture')}
                    </button>
                </div>
            }
        >
            <Head title={t('facturation.achats', 'Achats')} />

            <OngletsFacturation actif="achats" />

            {flash.success && (
                <p className="mb-4 rounded-lg bg-status-delivered/10 px-4 py-3 text-sm font-semibold text-status-delivered">
                    {flash.success}
                </p>
            )}
            {flash.error && (
                <p className="mb-4 rounded-lg bg-status-incident/10 px-4 py-3 text-sm font-semibold text-status-incident">
                    {flash.error}
                </p>
            )}

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('achats.reste_payer', 'Reste à payer')}</p>
                    <p className="text-2xl font-bold text-marine">{euros(cartes.a_payer_ttc)}</p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('achats.en_retard', 'Factures en retard')}</p>
                    <p className={`text-2xl font-bold ${compteurs.retard > 0 ? 'text-status-incident' : 'text-marine'}`}>
                        {compteurs.retard}
                    </p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('achats.deductible_cumulee', 'TVA déductible cumulée')}</p>
                    <p className="text-2xl font-bold text-status-delivered">{euros(cartes.deductible)}</p>
                </div>
            </div>

            <div className="mt-4">
                <BarreFiltres
                    adresse={route('purchases.index')}
                    filtres={filtres}
                    placeholder={t('achats.filtre', 'Fournisseur, référence, immatriculation…')}
                    listes={[{
                        champ: 'categorie',
                        intitule: t('achats.toutes_categories', 'Toutes les catégories'),
                        options: Object.entries(categories).map(([valeur, libelle]) => ({ valeur, libelle })),
                    }]}
                    compteurs={[
                        { libelle: t('planif.toutes', 'Toutes'), valeur: null, nombre: compteurs.total },
                        { libelle: t('achats.a_payer', 'À payer'), valeur: 'a_payer', nombre: compteurs.a_payer },
                        { libelle: t('statut.en_retard', 'En retard'), valeur: 'retard', nombre: compteurs.retard, alerte: true },
                        { libelle: t('achats.payees', 'Payées'), valeur: 'payees', nombre: compteurs.payees },
                    ]}
                />
            </div>

            <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('achats.fournisseur', 'Fournisseur')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('personnel.categorie', 'Catégorie')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('ordres.vehicule', 'Véhicule')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('facture.periode', 'Période')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('facture.echeance', 'Échéance')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('achats.ht', 'HT')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('facture.tva', 'TVA')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('achats.ttc', 'TTC')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('ordres.etat', 'État')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {achats.data.map((a) => {
                                const etat = ETATS[a.etat] ?? ETATS.TO_PAY;

                                return (
                                    <tr key={a.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-3">
                                            <span className="block font-semibold text-marine">{a.fournisseur}</span>
                                            <span className="block font-mono text-xs text-slate-600">{a.reference}</span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-600">
                                            {a.categorie}
                                            {a.litres !== null && (
                                                <span className="block text-xs">{a.litres.toLocaleString(locale)} l</span>
                                            )}
                                            {a.km_taxes !== null && (
                                                <span className="block text-xs">{a.km_taxes.toLocaleString(locale)} {t('achats.km_taxes_court', 'km taxés')}</span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className="font-mono text-marine">{a.vehicule}</span>
                                            <span className="block text-xs text-slate-600">{a.vehicule_detail}</span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 capitalize text-slate-600">{a.periode}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-600">{a.echeance}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{euros(a.ht)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">
                                            {euros(a.tva)}
                                            {a.deductible && (
                                                <span className="ml-1.5 rounded bg-status-delivered/10 px-1.5 py-0.5 text-[11px] font-semibold text-status-delivered">
                                                    {t('achats.deductible', 'déductible')}
                                                </span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right font-bold text-marine">{euros(a.ttc)}</td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase ${etat.classe}`}>
                                                {t(etat.cle, etat.libelle)}
                                            </span>
                                            {a.etat !== 'PAID' && (
                                                <Link
                                                    href={route('purchases.paid', a.id)}
                                                    method="patch"
                                                    as="button"
                                                    preserveScroll
                                                    className="ml-2 rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-marine transition hover:bg-surface"
                                                >
                                                    {t('facture.marquer_payee', 'Marquer payée')}
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                            {achats.data.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan="9">
                                        {t('achats.aucune', 'Aucune facture fournisseur ne correspond à cette recherche.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {achats.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm text-slate-600">
                    <span>
                        {t('achats.pagination', 'Page :page sur :total — :n factures', {
                            page: achats.current_page,
                            total: achats.last_page,
                            n: achats.total,
                        })}
                    </span>
                    <div className="flex gap-2">
                        {achats.prev_page_url && (
                            <Link href={achats.prev_page_url} preserveScroll className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-marine transition hover:bg-surface">
                                {t('achats.precedent', 'Précédent')}
                            </Link>
                        )}
                        {achats.next_page_url && (
                            <Link href={achats.next_page_url} preserveScroll className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-marine transition hover:bg-surface">
                                {t('achats.suivant', 'Suivant')}
                            </Link>
                        )}
                    </div>
                </div>
            )}

            <Modal show={encoder} onClose={() => setEncoder(false)} maxWidth="2xl">
                <Encodage
                    categories={categories}
                    vehicules={vehicules}
                    fournisseurs={fournisseurs}
                    onFermer={() => setEncoder(false)}
                />
            </Modal>
        </AuthenticatedLayout>
    );
}
