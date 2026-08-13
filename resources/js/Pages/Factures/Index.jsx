import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

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

export default function Index({ factures = [] }) {
    const { canPlan } = usePage().props.auth;

    const du = factures.filter((f) => f.etat !== 'PAID').reduce((somme, f) => somme + f.ttc, 0);
    const paye = factures.filter((f) => f.etat === 'PAID').reduce((somme, f) => somme + f.ttc, 0);
    const echues = factures.filter((f) => f.etat === 'OVERDUE').length;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">Facturation</h1>
                    <p className="text-sm text-slate-600">
                        {canPlan ? 'Toutes les factures émises.' : 'Vos factures et leur état de paiement.'}
                    </p>
                </div>
            }
        >
            <Head title="Facturation" />

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">Réglé</p>
                    <p className="text-2xl font-bold text-status-delivered">{euros(paye)}</p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">Reste dû</p>
                    <p className="text-2xl font-bold text-marine">{euros(du)}</p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">Factures échues</p>
                    <p className={`text-2xl font-bold ${echues > 0 ? 'text-status-incident' : 'text-marine'}`}>
                        {echues}
                    </p>
                </div>
            </div>

            <div className="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Référence</th>
                                {canPlan && <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Client</th>}
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Période</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Émise le</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Échéance</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">Montant TTC</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">État</th>
                            </tr>
                        </thead>
                        <tbody>
                            {factures.map((facture) => {
                                const etat = ETATS[facture.etat] ?? ETATS.SENT;

                                return (
                                    <tr key={facture.id} className="border-b border-slate-50 last:border-0">
                                                                                <td className="whitespace-nowrap px-4 py-3 font-mono font-semibold">
                                            <Link
                                                href={route('invoices.show', facture.id)}
                                                className="text-brand-blue transition hover:text-marine"
                                            >
                                                {facture.reference}
                                            </Link>
                                        </td>
                                        {canPlan && (
                                            <td className="px-4 py-3 text-slate-700">
                                                <span className="block max-w-[12rem] truncate" title={facture.client ?? ''}>
                                                    {facture.client ?? '—'}
                                                </span>
                                            </td>
                                        )}
                                        <td className="whitespace-nowrap px-4 py-3 capitalize text-slate-600">{facture.periode}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-600">{facture.emise_le}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-600">{facture.echeance}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right font-bold text-marine">
                                            {facture.autoliquidation && (
                                                <span
                                                    className="mr-2 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700"
                                                    title="Le client est établi dans un autre pays de l'UE : la TVA n'est pas facturée ici, il la déclare et la paie dans son pays."
                                                >
                                                    TVA due par le client
                                                </span>
                                            )}
                                            {euros(facture.ttc)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase ${etat.classe}`}>
                                                {etat.libelle}
                                            </span>
                                        </td>
                                    </tr>
                                );
                            })}
                            {factures.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan={canPlan ? 7 : 6}>
                                        Aucune facture pour l'instant.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
