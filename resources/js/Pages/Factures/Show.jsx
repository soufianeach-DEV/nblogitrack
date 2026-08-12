import BoutonRetour from '@/Components/BoutonRetour';
import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const ETATS = {
    DRAFT: { libelle: 'Brouillon', classe: 'bg-slate-100 text-slate-700' },
    SENT: { libelle: 'Envoyée', classe: 'bg-brand-blue/10 text-brand-blue' },
    PAID: { libelle: 'Payée', classe: 'bg-status-delivered/10 text-status-delivered' },
    OVERDUE: { libelle: 'En retard', classe: 'bg-status-incident/10 text-status-incident' },
};

const euros = (montant) => Number(montant).toLocaleString('fr-FR', {
    style: 'currency',
    currency: 'EUR',
});

function BoutonPaiement({ facture }) {
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
                {processing ? 'Enregistrement…' : arme ? 'Confirmer le paiement' : 'Marquer payée'}
            </button>
            {arme && ! processing && (
                <button
                    type="button"
                    onClick={() => setArme(false)}
                    className="text-sm font-semibold text-slate-600 transition hover:text-marine"
                >
                    Annuler
                </button>
            )}
        </div>
    );
}

export default function Show({ facture, peutMarquerPayee = false }) {
    const etat = ETATS[facture.etat] ?? ETATS.SENT;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <BoutonRetour href={route('invoices.index')}>Facturation</BoutonRetour>
                        <h1 className="mt-2 font-mono text-2xl font-bold text-marine">{facture.reference}</h1>
                        <p className="text-sm text-slate-600">
                            Prestations du {facture.periode_debut} au {facture.periode_fin}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase ${etat.classe}`}>
                            {etat.libelle}
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
                            title="Facture électronique structurée, format Peppol BIS Billing 3.0"
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
                        Facturé à
                    </h2>
                    <p className="font-bold text-marine">{facture.client.nom}</p>
                    <p className="text-sm text-slate-600">{facture.client.adresse}</p>
                    <p className="text-sm text-slate-600">{facture.client.localite}</p>
                    <p className="text-sm text-slate-600">{facture.client.pays}</p>
                    <p className="mt-2 font-mono text-sm text-marine">{facture.client.tva}</p>
                </section>

                <section className="rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        Dates
                    </h2>
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between gap-3">
                            <dt className="text-slate-600">Émise le</dt>
                            <dd className="font-semibold text-marine">{facture.emise_le}</dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-slate-600">Échéance</dt>
                            <dd className="font-semibold text-marine">{facture.echeance}</dd>
                        </div>
                        {facture.payee_le && (
                            <div className="flex justify-between gap-3">
                                <dt className="text-slate-600">Payée le</dt>
                                <dd className="font-semibold text-status-delivered">{facture.payee_le}</dd>
                            </div>
                        )}
                    </dl>
                </section>

                <section className="rounded-2xl bg-marine p-5 text-white shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-300">
                                {facture.etat === 'PAID' ? 'Paiement reçu' : 'À payer'}
                            </h2>
                            <p className="text-3xl font-bold">{euros(facture.ttc)}</p>
                            <p className="mt-3 text-xs uppercase tracking-wide text-slate-300">Compte</p>
                            <p className="font-mono text-sm">{facture.iban}</p>
                            <p className="mt-2 text-xs uppercase tracking-wide text-slate-300">Communication structurée</p>
                            <p className="font-mono text-sm">{facture.communication}</p>
                        </div>
                        {facture.qr && (
                            <div className="shrink-0 rounded-lg bg-white p-1.5" title="À scanner avec votre application bancaire">
                                <img
                                    src={facture.qr}
                                    alt="QR de paiement à scanner avec votre application bancaire"
                                    className="h-28 w-28"
                                />
                            </div>
                        )}
                    </div>
                </section>
            </div>

            <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Expédition</th>
                                <th scope="col" className="px-4 py-3 font-semibold">Prestation</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">Montant HT</th>
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
                                                <strong className="font-semibold text-marine">Transport</strong>
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
                                <td colSpan="2" className="px-4 py-2 text-right text-slate-600">Total HT</td>
                                <td className="whitespace-nowrap px-4 py-2 text-right font-semibold text-marine">{euros(facture.ht)}</td>
                            </tr>
                            <tr>
                                <td colSpan="2" className="px-4 py-2 text-right text-slate-600">
                                    TVA {facture.taux.toLocaleString('fr-FR')} %
                                </td>
                                <td className="whitespace-nowrap px-4 py-2 text-right font-semibold text-marine">{euros(facture.tva)}</td>
                            </tr>
                            <tr className="border-t border-slate-100">
                                <td colSpan="2" className="px-4 py-3 text-right font-bold text-marine">Total TTC</td>
                                <td className="whitespace-nowrap px-4 py-3 text-right text-lg font-bold text-marine">{euros(facture.ttc)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                {facture.autoliquidation && (
                    <p className="border-t border-slate-100 px-4 py-3 text-xs text-slate-600">
                        Autoliquidation — TVA due par le preneur (art. 21, §2 du Code de la TVA ;
                        art. 44 de la directive 2006/112/CE).
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
