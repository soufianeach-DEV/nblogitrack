import BoutonRetour from '@/Components/BoutonRetour';
import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const ETATS = {
    DRAFT: { cle: 'facture.brouillon', libelle: 'Brouillon', classe: 'bg-slate-100 text-slate-700' },
    SENT: { cle: 'statut.envoyee', libelle: 'Envoyée', classe: 'bg-brand-blue/10 text-brand-blue' },
    PAID: { cle: 'statut.payee', libelle: 'Payée', classe: 'bg-status-delivered/10 text-status-delivered' },
    OVERDUE: { cle: 'statut.en_retard', libelle: 'En retard', classe: 'bg-status-incident/10 text-status-incident' },
};

function BoutonPaiement({ facture }) {
    const t = useTraduction();
    const [arme, setArme] = useState(false);
    const { patch, processing } = useForm({});

    const envoyer = () => {
        if (! arme) {
            setArme(true);

            return;
        }

        patch(route('invoices.paid', facture.id), {
            preserveScroll: true,
            onFinish: () => setArme(false),
        });
    };

    return (
        <div className="flex items-center gap-2">
            <button
                type="button"
                onClick={envoyer}
                disabled={processing}
                className={`rounded-lg px-4 py-2 text-sm font-bold transition disabled:opacity-60 ${
                    arme
                        ? 'bg-status-delivered text-white hover:bg-green-800'
                        : 'bg-action text-marine-deep hover:bg-action-dark'
                }`}
            >
                {processing
                    ? t('action.enregistrement', 'Enregistrement…')
                    : arme
                        ? t('facture.confirmer_paiement', 'Confirmer le paiement')
                        : t('facture.marquer_payee', 'Marquer payée')}
            </button>
            {arme && ! processing && (
                <button
                    type="button"
                    onClick={() => setArme(false)}
                    className="text-sm font-semibold text-slate-600 transition hover:text-marine"
                >
                    {t('action.annuler', 'Annuler')}
                </button>
            )}
        </div>
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

export default function Show({ facture, peutMarquerPayee = false, peutPayerEnLigne = false }) {
    const t = useTraduction();
    const locale = useLocale();
    const euros = (montant) => Number(montant).toLocaleString(locale, { style: 'currency', currency: 'EUR' });
    const etat = ETATS[facture.etat] ?? ETATS.SENT;

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
                    </div>
                    <div className="flex items-center gap-3">
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
                        {peutMarquerPayee && <BoutonPaiement facture={facture} />}
                    </div>
                </div>
            }
        >
            <Head title={facture.reference} />

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
                        {facture.payee_le && (
                            <div className="flex justify-between gap-3">
                                <dt className="text-slate-600">{t('ordres.payee_le', 'Payée le')}</dt>
                                <dd className="font-semibold text-status-delivered">{facture.payee_le}</dd>
                            </div>
                        )}
                    </dl>
                </section>

                <section className="rounded-2xl bg-marine p-5 text-white shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-300">
                                {facture.etat === 'PAID'
                                    ? t('facture.paiement_recu', 'Paiement reçu')
                                    : t('facture.a_payer', 'À payer')}
                            </h2>
                            <p className="text-3xl font-bold">{euros(facture.ttc)}</p>
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

                    {}
                    {peutPayerEnLigne && (
                        <div className="mt-4 border-t border-white/15 pt-4">
                            <BoutonPayerEnLigne facture={facture} pleineLargeur />
                            <p className="mt-2 text-center text-[11px] leading-tight text-slate-300">
                                Votre banque ne lit pas le code ? Réglez par carte en quelques secondes.
                            </p>
                        </div>
                    )}
                </section>
            </div>

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
                {facture.autoliquidation && (
                    <p className="border-t border-slate-100 px-4 py-3 text-xs text-slate-600">
                        {t('facture.autoliq_mention', 'Autoliquidation — TVA due par le preneur (art. 21, §2 du Code de la TVA ; art. 44 de la directive 2006/112/CE).')}
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
