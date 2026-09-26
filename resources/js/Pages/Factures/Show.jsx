import BoutonRetour from '@/Components/BoutonRetour';
import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const ETATS = {
    DRAFT: { cle: 'facture.brouillon', libelle: 'Brouillon', classe: 'bg-slate-100 text-slate-700' },
    SENT: { cle: 'statut.envoyee', libelle: 'Envoyée', classe: 'bg-brand-blue/10 text-brand-blue' },
    PAID: { cle: 'statut.payee', libelle: 'Payée', classe: 'bg-status-delivered/10 text-status-delivered' },
    OVERDUE: { cle: 'statut.en_retard', libelle: 'En retard', classe: 'bg-status-incident/10 text-status-incident' },
    CREDITED: { cle: 'facture.annulee_avoir', libelle: 'Annulée par avoir', classe: 'bg-slate-100 text-slate-500 line-through' },
};

const AVOIR = { cle: 'facture.avoir', libelle: 'Avoir', classe: 'bg-action/20 text-marine' };

const METHODES = {
    TRANSFER: ['facture.methode_virement', 'Virement'],
    CASH: ['facture.methode_especes', 'Espèces'],
    OTHER: ['facture.methode_autre', 'Autre'],
    STRIPE: ['facture.methode_en_ligne', 'Paiement en ligne'],
};

function FormulairePaiement({ facture, onFermer }) {
    const t = useTraduction();
    const aujourdhui = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const { data, setData, patch, processing, errors } = useForm({
        montant: String(facture.solde),
        date: `${aujourdhui.getFullYear()}-${pad(aujourdhui.getMonth() + 1)}-${pad(aujourdhui.getDate())}`,
        methode: 'TRANSFER',
    });
    const champ = 'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine';

    const envoyer = (e) => {
        e.preventDefault();
        patch(route('invoices.paid', facture.id), { preserveScroll: true, onSuccess: onFermer });
    };

    return (
        <form onSubmit={envoyer} className="mt-4 grid gap-3 rounded-2xl bg-white p-5 shadow-sm sm:grid-cols-4 sm:items-end">
            <label className="text-sm font-medium text-marine">
                {t('facture.montant_recu', 'Montant reçu')}
                <input type="number" step="0.01" min="0.01" max={facture.solde} value={data.montant} onChange={(e) => setData('montant', e.target.value)} className={champ} required />
                {errors.montant && <span className="mt-1 block text-xs text-status-incident">{errors.montant}</span>}
            </label>
            <label className="text-sm font-medium text-marine">
                {t('facture.date_paiement', 'Date du paiement')}
                <input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} className={champ} required />
                {errors.date && <span className="mt-1 block text-xs text-status-incident">{errors.date}</span>}
            </label>
            <label className="text-sm font-medium text-marine">
                {t('facture.methode', 'Moyen de paiement')}
                <select value={data.methode} onChange={(e) => setData('methode', e.target.value)} className={champ}>
                    {['TRANSFER', 'CASH', 'OTHER'].map((m) => <option key={m} value={m}>{t(...METHODES[m])}</option>)}
                </select>
            </label>
            <div className="flex gap-2">
                <button type="submit" disabled={processing} className="rounded-lg bg-status-delivered px-4 py-2 text-sm font-bold text-white transition hover:bg-green-800 disabled:opacity-60">
                    {processing ? t('action.enregistrement', 'Enregistrement…') : t('facture.enregistrer_paiement', 'Enregistrer')}
                </button>
                <button type="button" onClick={onFermer} className="px-2 text-sm font-semibold text-slate-600 hover:text-marine">
                    {t('action.annuler', 'Annuler')}
                </button>
            </div>
        </form>
    );
}

function FormulaireAvoir({ facture, onFermer }) {
    const t = useTraduction();
    const { data, setData, post, processing, errors } = useForm({ motif: '', refacturer: true });

    const envoyer = (e) => {
        e.preventDefault();
        post(route('invoices.credit', facture.id), { onSuccess: onFermer });
    };

    return (
        <form onSubmit={envoyer} className="mt-4 space-y-3 rounded-2xl border border-status-incident/30 bg-white p-5 shadow-sm">
            <p className="text-sm text-slate-600">
                {t('facture.avoir_aide', 'Une facture émise ne se modifie pas : l\'avoir l\'annule, avec un numéro propre. Rien n\'est supprimé.')}
            </p>
            <label className="block text-sm font-medium text-marine">
                {t('facture.motif_avoir', 'Motif de l\'avoir')}
                <input
                    type="text"
                    value={data.motif}
                    onChange={(e) => setData('motif', e.target.value)}
                    minLength={5}
                    maxLength={500}
                    required
                    placeholder={t('facture.motif_avoir_aide', 'Adresse de facturation erronée, supplément oublié…')}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                />
                {errors.motif && <span className="mt-1 block text-xs text-status-incident">{errors.motif}</span>}
            </label>
            <label className="flex items-start gap-2 text-sm text-marine">
                <input type="checkbox" checked={data.refacturer} onChange={(e) => setData('refacturer', e.target.checked)} className="mt-0.5 rounded border-gray-300 text-marine focus:ring-marine" />
                <span>
                    {t('facture.refacturer', 'Refacturer aussitôt les prestations')}
                    <span className="block text-xs text-slate-600">{t('facture.refacturer_aide', 'Une nouvelle facture est émise avec les données actuelles du client et des expéditions. Décochez pour un geste commercial.')}</span>
                </span>
            </label>
            <div className="flex gap-2">
                <button type="submit" disabled={processing} className="rounded-lg bg-status-incident px-4 py-2 text-sm font-bold text-white transition hover:opacity-90 disabled:opacity-60">
                    {processing ? t('action.enregistrement', 'Enregistrement…') : t('facture.emettre_avoir', 'Émettre l\'avoir')}
                </button>
                <button type="button" onClick={onFermer} className="px-2 text-sm font-semibold text-slate-600 hover:text-marine">
                    {t('action.annuler', 'Annuler')}
                </button>
            </div>
        </form>
    );
}

function BoutonEnvoi({ facture }) {
    const t = useTraduction();
    const { post, processing } = useForm({});

    return (
        <button
            type="button"
            onClick={() => post(route('invoices.send', facture.id), { preserveScroll: true })}
            disabled={processing}
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-marine shadow-sm transition hover:bg-surface disabled:opacity-60"
        >
            {processing
                ? t('facture.envoi_en_cours', 'Envoi…')
                : facture.envoyee_le
                    ? t('facture.renvoyer', 'Renvoyer par courriel')
                    : t('facture.envoyer', 'Envoyer par courriel')}
        </button>
    );
}

function BoutonPayerEnLigne({ facture, pleineLargeur = false }) {
    const t = useTraduction();
    const { post, processing } = useForm({});

    return (
        <button
            type="button"
            onClick={() => post(route('payments.payer', facture.id))}
            disabled={processing}
            className={
                'inline-flex items-center justify-center gap-1.5 rounded-lg bg-action font-bold text-marine-deep shadow-sm transition hover:bg-action-dark disabled:opacity-60 '
                + (pleineLargeur ? 'w-full px-4 py-2.5 text-sm' : 'px-4 py-2 text-sm')
            }
        >
            <Icone nom="facture" className="h-4 w-4" />
            {processing
                ? t('facture.redirection', 'Redirection…')
                : t('facture.payer_en_ligne', 'Payer en ligne')}
        </button>
    );
}

export default function Show({ facture, peutMarquerPayee = false, peutEmettreAvoir = false, peutPayerEnLigne = false, peutEnvoyer = false, paiementEnCours = null, aRembourser = [] }) {
    const t = useTraduction();
    const locale = useLocale();
    const euros = (montant) => Number(montant).toLocaleString(locale, { style: 'currency', currency: 'EUR' });
    const etat = facture.avoir ? AVOIR : (ETATS[facture.etat] ?? ETATS.SENT);
    const [formulaire, setFormulaire] = useState(null);
    const bouton = 'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm transition';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <BoutonRetour href={route('invoices.index')}>{t('nav.facturation', 'Facturation')}</BoutonRetour>
                        <h1 className="mt-2 font-mono text-2xl font-bold text-marine">{facture.reference}</h1>
                        <p className="text-sm text-slate-600">
                            {t('facture.prestations', 'Prestations du :debut au :fin', {
                                debut: facture.periode_debut,
                                fin: facture.periode_fin,
                            })}
                        </p>
                        {facture.facture_annulee && (
                            <p className="text-sm text-slate-600">
                                {t('facture.annule_la_facture', 'Annule la facture')}{' '}
                                <Link href={route('invoices.show', facture.facture_annulee.id)} className="font-mono font-semibold text-brand-blue hover:text-marine">{facture.facture_annulee.reference}</Link>
                                {facture.motif_avoir ? ` — ${facture.motif_avoir}` : ''}
                            </p>
                        )}
                        {facture.annulee_par && (
                            <p className="text-sm text-status-incident">
                                {t('facture.annulee_par', 'Annulée par l\'avoir')}{' '}
                                <Link href={route('invoices.show', facture.annulee_par.id)} className="font-mono font-semibold underline">{facture.annulee_par.reference}</Link>
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center gap-2 sm:gap-3">
                        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase ${etat.classe}`}>
                            {t(etat.cle, etat.libelle)}
                        </span>
                        <a
                            href={route('invoices.pdf', facture.id)}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-marine shadow-sm transition hover:bg-surface"
                        >
                            <Icone nom="facture" className="h-4 w-4" />
                            PDF
                        </a>
                        <a
                            href={route('invoices.ubl', facture.id)}
                            title={t('facture.peppol_aide', 'Facture électronique structurée, format Peppol BIS Billing 3.0')}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-marine shadow-sm transition hover:bg-surface"
                        >
                            <Icone nom="facture" className="h-4 w-4" />
                            XML Peppol
                        </a>
                        {peutEnvoyer && <BoutonEnvoi facture={facture} />}
                        {peutMarquerPayee && (
                            <button type="button" onClick={() => setFormulaire('paiement')} className={bouton + ' bg-action text-marine-deep hover:bg-action-dark'}>
                                {t('facture.enregistrer_un_paiement', 'Enregistrer un paiement')}
                            </button>
                        )}
                        {peutEmettreAvoir && (
                            <button type="button" onClick={() => setFormulaire('avoir')} className={bouton + ' border border-status-incident/40 bg-white text-status-incident hover:bg-status-incident/5'}>
                                {t('facture.annuler_par_avoir', 'Annuler par un avoir')}
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={facture.reference} />

            {formulaire === 'paiement' && <FormulairePaiement facture={facture} onFermer={() => setFormulaire(null)} />}
            {formulaire === 'avoir' && <FormulaireAvoir facture={facture} onFermer={() => setFormulaire(null)} />}
            {formulaire && <div className="h-4" />}


            <div className="grid gap-4 lg:grid-cols-3">
                <section className="rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        {t('facture.facture_a', 'Facturé à')}
                    </h2>
                    <p className="font-bold text-marine">{facture.client.nom}</p>
                    <p className="text-sm text-slate-600">{facture.client.adresse}</p>
                    <p className="text-sm text-slate-600">{facture.client.localite}</p>
                    <p className="text-sm text-slate-600">{facture.client.pays}</p>
                    <p className="mt-2 font-mono text-sm text-marine">{facture.client.tva}</p>
                </section>

                <section className="rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        {t('facture.dates', 'Dates')}
                    </h2>
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between gap-3">
                            <dt className="text-slate-600">{t('facture.emise_le', 'Émise le')}</dt>
                            <dd className="font-semibold text-marine">{facture.emise_le}</dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-slate-600">{t('facture.echeance', 'Échéance')}</dt>
                            <dd className="font-semibold text-marine">{facture.echeance}</dd>
                        </div>
                        {facture.envoyee_le && (
                            <div className="flex justify-between gap-3">
                                <dt className="text-slate-600">{t('facture.envoyee_le', 'Envoyée le')}</dt>
                                <dd className="font-semibold text-marine">{facture.envoyee_le}</dd>
                            </div>
                        )}
                        {facture.payee_le && (
                            <div className="flex justify-between gap-3">
                                <dt className="text-slate-600">{t('ordres.payee_le', 'Payée le')}</dt>
                                <dd className="font-semibold text-status-delivered">{facture.payee_le}</dd>
                            </div>
                        )}
                    </dl>
                </section>

                {facture.avoir ? (
                <section className="rounded-2xl bg-marine p-5 text-white shadow-sm">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-300">{t('facture.montant_avoir', 'Montant de l\'avoir')}</h2>
                    <p className="text-3xl font-bold">{euros(facture.ttc)}</p>
                    <p className="mt-3 text-sm text-slate-300">{t('facture.avoir_rien_a_payer', 'La facture annulée n\'est plus due.')}</p>
                </section>
                ) : facture.etat === 'CREDITED' ? (
                <section className="rounded-2xl bg-slate-100 p-5 text-slate-700 shadow-sm">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('facture.annulee_avoir', 'Annulée par avoir')}</h2>
                    <p className="text-3xl font-bold line-through decoration-2">{euros(facture.ttc)}</p>
                    <p className="mt-3 text-sm">{t('facture.annulee_rien_a_payer', 'Cette facture est annulée : il n\'y a rien à payer.')}</p>
                </section>
                ) : (
                <section className="rounded-2xl bg-marine p-5 text-white shadow-sm">
                    <div className="flex flex-col items-start justify-between gap-4 sm:flex-row">
                        <div className="min-w-0 break-words">
                            <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-300">
                                {facture.etat === 'PAID'
                                    ? t('facture.paiement_recu', 'Paiement reçu')
                                    : t('facture.a_payer', 'À payer')}
                            </h2>
                            <p className="text-3xl font-bold">{euros(facture.etat === 'PAID' ? facture.ttc : facture.solde)}</p>
                            {facture.paye > 0 && facture.etat !== 'PAID' && (
                                <p className="mt-1 text-xs text-slate-300">
                                    {t('facture.deja_paye', ':paye déjà reçus sur :total', { paye: euros(facture.paye), total: euros(facture.ttc) })}
                                </p>
                            )}
                            <p className="mt-3 text-xs uppercase tracking-wide text-slate-300">{t('facture.compte', 'Compte')}</p>
                            <p className="font-mono text-sm">{facture.iban}</p>
                            <p className="mt-2 text-xs uppercase tracking-wide text-slate-300">{t('facture.communication', 'Communication structurée')}</p>
                            <p className="font-mono text-sm">{facture.communication}</p>
                        </div>
                        {facture.qr && (
                            <div className="shrink-0 text-center">
                                <div
                                    className="rounded-lg bg-white p-1.5"
                                    title={t('facture.qr_aide', 'Virement SEPA au format EPC : votre application bancaire préremplit le compte, le montant et la communication.')}
                                >
                                    {}
                                    <img
                                        src={facture.qr}
                                        alt={t('facture.qr_alt', 'QR de virement SEPA au format EPC, contenant le compte, le montant et la communication')}
                                        className="h-44 w-44"
                                    />
                                </div>
                                <p className="mt-1.5 text-[11px] leading-tight text-slate-300">
                                    {t('facture.virement_sepa', 'Virement SEPA')}
                                    <span className="block">{t('facture.norme_epc', 'norme EPC')}</span>
                                </p>
                            </div>
                        )}
                    </div>

                    {paiementEnCours && (
                        <p className="mt-4 rounded-lg bg-white/10 px-3 py-2 text-xs leading-snug text-slate-200">
                            {t('facture.paiement_en_cours_depuis', 'Paiement en ligne en cours de traitement par la banque depuis le :date : la facture sera marquée payée dès sa réception.', { date: paiementEnCours })}
                        </p>
                    )}
                    {peutPayerEnLigne && (
                        <div className="mt-4 border-t border-white/15 pt-4">
                            <BoutonPayerEnLigne facture={facture} pleineLargeur />
                            <p className="mt-2 text-center text-[11px] leading-tight text-slate-300">
                                {t('facture.banque_carte', 'Votre banque ne lit pas le code ? Réglez par carte en quelques secondes.')}
                            </p>
                        </div>
                    )}
                </section>
                )}
            </div>

            {aRembourser.length > 0 && (
                <section className="mt-4 rounded-2xl border border-status-incident/30 bg-white p-5 shadow-sm">
                    <h2 className="mb-2 text-xs font-semibold uppercase tracking-wider text-status-incident">{t('facture.a_rembourser', 'Paiements en ligne reçus en trop, à rembourser')}</h2>
                    <p className="mb-2 text-xs text-slate-600">{t('facture.a_rembourser_aide', 'Remboursez-les depuis le tableau de bord Stripe (Paiements), en cherchant la session indiquée.')}</p>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {aRembourser.map((r) => (
                            <li key={r.session} className="flex items-center justify-between gap-3 py-2">
                                <span className="text-slate-600">{r.date} · <span className="font-mono text-xs">{r.session}</span></span>
                                <span className="font-semibold text-status-incident">{euros(r.montant)}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {facture.paiements.length > 0 && (
                <section className="mt-4 rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">{t('facture.paiements', 'Paiements reçus')}</h2>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {facture.paiements.map((p) => (
                            <li key={p.id} className="flex items-center justify-between gap-3 py-2">
                                <span className="text-slate-600">{p.date} · {t(...(METHODES[p.methode] ?? METHODES.OTHER))}</span>
                                <span className="font-semibold text-status-delivered">{euros(p.montant)}</span>
                            </li>
                        ))}
                    </ul>
                    {facture.solde > 0 && (
                        <p className="mt-2 flex justify-between border-t border-slate-100 pt-2 text-sm font-bold text-marine">
                            <span>{t('facture.reste_du', 'Reste dû')}</span>
                            <span>{euros(facture.solde)}</span>
                        </p>
                    )}
                </section>
            )}

            <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('ordres.expedition', 'Expédition')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('facture.prestation', 'Prestation')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('facture.montant_ht', 'Montant HT')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {facture.lignes.map((ligne) => (
                                <tr key={ligne.id} className="border-b border-slate-50 last:border-0">
                                    <td className="whitespace-nowrap px-4 py-3 font-mono font-semibold">
                                        {ligne.ordre_id ? (
                                            <Link
                                                href={route('transport-orders.show', ligne.ordre_id)}
                                                className="text-brand-blue transition hover:text-marine"
                                            >
                                                {ligne.numero}
                                            </Link>
                                        ) : (
                                            <span className="text-marine">{ligne.numero ?? '—'}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {ligne.description.startsWith('Transport ') ? (
                                            <>
                                                <strong className="font-semibold text-marine">{t('facture.transport', 'Transport')}</strong>
                                                {ligne.description.slice(9)}
                                            </>
                                        ) : ligne.description}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right font-semibold text-marine">
                                        {euros(ligne.ht)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="text-sm">
                            <tr className="border-t border-slate-100">
                                <td colSpan="2" className="px-4 py-2 text-right text-slate-600">{t('facture.total_ht', 'Total HT')}</td>
                                <td className="whitespace-nowrap px-4 py-2 text-right font-semibold text-marine">{euros(facture.ht)}</td>
                            </tr>
                            <tr>
                                <td colSpan="2" className="px-4 py-2 text-right text-slate-600">
                                    {t('facture.tva', 'TVA')} {facture.taux.toLocaleString(locale)} %
                                </td>
                                <td className="whitespace-nowrap px-4 py-2 text-right font-semibold text-marine">{euros(facture.tva)}</td>
                            </tr>
                            <tr className="border-t border-slate-100">
                                <td colSpan="2" className="px-4 py-3 text-right font-bold text-marine">{t('facture.total_ttc', 'Total TTC')}</td>
                                <td className="whitespace-nowrap px-4 py-3 text-right text-lg font-bold text-marine">{euros(facture.ttc)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                {facture.categorie_tva === 'AE' && (
                    <p className="border-t border-slate-100 px-4 py-3 text-xs text-slate-600">
                        {t('facture.autoliq_mention', 'Autoliquidation — TVA due par le preneur (art. 21, §2 du Code de la TVA ; art. 44 de la directive 2006/112/CE).')}
                    </p>
                )}
                {facture.categorie_tva === 'O' && (
                    <p className="border-t border-slate-100 px-4 py-3 text-xs text-slate-600">
                        {t('facture.hors_champ_mention', 'Prestation hors du champ de la TVA belge — preneur établi hors de l\'Union européenne (art. 21, §2 du Code de la TVA).')}
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
