import OngletsFacturation from '@/Components/OngletsFacturation';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head, Link, router, usePage } from '@inertiajs/react';

const ETATS = {
    DRAFT: { cle: 'facture.brouillon', libelle: 'Brouillon', classe: 'bg-slate-100 text-slate-700' },
    SENT: { cle: 'statut.envoyee', libelle: 'Envoyée', classe: 'bg-brand-blue/10 text-brand-blue' },
    PAID: { cle: 'statut.payee', libelle: 'Payée', classe: 'bg-status-delivered/10 text-status-delivered' },
    OVERDUE: { cle: 'statut.en_retard', libelle: 'En retard', classe: 'bg-status-incident/10 text-status-incident' },
};

export default function Index({ factures = [], peutGererAchats = false }) {
    const { canPlan } = usePage().props.auth;
    const t = useTraduction();
    const locale = useLocale();
    const euros = (montant) => Number(montant).toLocaleString(locale, { style: 'currency', currency: 'EUR' });

    // Pas de colonne vide chez le personnel, qui ne paie jamais.
    const colonnePaiement = factures.some((f) => f.peut_payer);

    /**
     * Toute la ligne ouvre la facture : viser un lien de huit caracteres
     * dans une ligne de cinquante pixels de haut est inutilement penible.
     *
     * La reference reste un vrai lien : c'est lui qui porte l'acces
     * clavier et ce qu'annonce un lecteur d'ecran. La ligne n'est qu'un
     * raccourci a la souris, elle n'entre donc pas dans l'ordre de
     * tabulation — sinon chaque facture y compterait deux fois.
     */
    const ouvrir = (facture) => (e) => {
        // Un clic qui vient de terminer une selection de texte ne navigue
        // pas : on voulait copier un montant, pas changer de page.
        if (window.getSelection()?.toString()) return;

        router.get(route('invoices.show', facture.id));
    };

    const du = factures.filter((f) => f.etat !== 'PAID').reduce((somme, f) => somme + f.ttc, 0);
    const paye = factures.filter((f) => f.etat === 'PAID').reduce((somme, f) => somme + f.ttc, 0);
    const enRetard = factures.filter((f) => f.etat === 'OVERDUE').length;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">{t('nav.facturation', 'Facturation')}</h1>
                    <p className="text-sm text-slate-600">
                        {canPlan
                            ? t('facture.toutes', 'Toutes les factures émises.')
                            : t('facture.les_votres', 'Vos factures et leur état de paiement.')}
                    </p>
                </div>
            }
        >
            <Head title={t('nav.facturation', 'Facturation')} />

            {peutGererAchats && <OngletsFacturation actif="ventes" />}

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('tdb.regle', 'Réglé')}</p>
                    <p className="text-2xl font-bold text-status-delivered">{euros(paye)}</p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('tdb.reste_du', 'Reste dû')}</p>
                    <p className="text-2xl font-bold text-marine">{euros(du)}</p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    {/* Le tableau dit deja « en retard » pour cet etat : deux
                        mots pour une meme notion feraient deux traductions. */}
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('facture.en_retard', 'Factures en retard')}</p>
                    <p className={`text-2xl font-bold ${enRetard > 0 ? 'text-status-incident' : 'text-marine'}`}>
                        {enRetard}
                    </p>
                </div>
            </div>

            <div className="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('commun.reference', 'Référence')}</th>
                                {canPlan && <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('ordres.client', 'Client')}</th>}
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('facture.periode', 'Période')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('facture.emise_le', 'Émise le')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('facture.echeance', 'Échéance')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('ordres.montant_ttc', 'Montant TTC')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('ordres.etat', 'État')}</th>
                                {colonnePaiement && <th scope="col" className="px-4 py-3"><span className="sr-only">{t('facture.paiement', 'Paiement')}</span></th>}
                            </tr>
                        </thead>
                        <tbody>
                            {factures.map((facture) => {
                                const etat = ETATS[facture.etat] ?? ETATS.SENT;

                                return (
                                    <tr
                                        key={facture.id}
                                        onClick={ouvrir(facture)}
                                        className="cursor-pointer border-b border-slate-50 transition-colors last:border-0 hover:bg-surface"
                                    >
                                        <td className="whitespace-nowrap px-4 py-3 font-mono font-semibold">
                                            <Link
                                                href={route('invoices.show', facture.id)}
                                                // Le lien mene deja la ou mene la ligne : sans
                                                // cette coupure, un clic dessus declencherait
                                                // deux visites vers la meme page.
                                                onClick={(e) => e.stopPropagation()}
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
                                                    title={t('facture.autoliq_aide', 'Le client est établi dans un autre pays de l\'UE : la TVA n\'est pas facturée ici, il la déclare et la paie dans son pays.')}
                                                >
                                                    {t('facture.tva_due_client', 'TVA due par le client')}
                                                </span>
                                            )}
                                            {euros(facture.ttc)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase ${etat.classe}`}>
                                                {t(etat.cle, etat.libelle)}
                                            </span>
                                        </td>
                                        {/* Colonne a part : les etats n'ont pas la meme largeur,
                                            les boutons ne s'aligneraient pas derriere eux. */}
                                        {colonnePaiement && (
                                            <td className="whitespace-nowrap px-4 py-3">
                                                {facture.peut_payer && (
                                                    <button
                                                        type="button"
                                                        onClick={(e) => {
                                                            // Payer n'est pas consulter : le clic
                                                            // s'arrete ici et n'ouvre pas la fiche.
                                                            e.stopPropagation();
                                                            router.post(route('payments.payer', facture.id));
                                                        }}
                                                        className="rounded-lg bg-action px-3 py-1 text-xs font-bold text-marine-deep transition hover:bg-action-dark"
                                                    >
                                                        {t('facture.payer', 'Payer')}
                                                    </button>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                            {factures.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan={(canPlan ? 7 : 6) + (colonnePaiement ? 1 : 0)}>
                                        {t('facture.aucune', 'Aucune facture pour l\'instant.')}
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
