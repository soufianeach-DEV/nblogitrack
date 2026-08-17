import OngletsFacturation from '@/Components/OngletsFacturation';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head } from '@inertiajs/react';

export default function Tva({ lignes = [], totaux }) {
    const t = useTraduction();
    const locale = useLocale();
    const euros = (montant) => Number(montant).toLocaleString(locale, { style: 'currency', currency: 'EUR' });

    const parTrimestre = lignes.reduce((groupes, ligne) => {
        (groupes[ligne.trimestre] = groupes[ligne.trimestre] ?? []).push(ligne);
        return groupes;
    }, {});

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">{t('nav.facturation', 'Facturation')}</h1>
                    <p className="text-sm text-slate-600">
                        {t('tva.intro', 'TVA collectée sur les ventes, TVA déductible sur les achats : la mécanique de la déclaration périodique.')}
                    </p>
                </div>
            }
        >
            <Head title={t('facturation.synthese_tva', 'Synthèse TVA')} />

            <OngletsFacturation actif="tva" />

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('tva.collectee', 'TVA collectée')}</p>
                    <p className="text-2xl font-bold text-marine">{euros(totaux.collectee)}</p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('tva.deductible', 'TVA déductible')}</p>
                    <p className="text-2xl font-bold text-status-delivered">{euros(totaux.deductible)}</p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('tva.solde_verser', 'Solde à verser')}</p>
                    <p className={`text-2xl font-bold ${totaux.solde >= 0 ? 'text-marine' : 'text-status-delivered'}`}>
                        {euros(totaux.solde)}
                    </p>
                </div>
            </div>

            <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('tva.mois', 'Mois')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('tva.ventes_ht', 'Ventes HT')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('tva.collectee', 'TVA collectée')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('tva.achats_ht', 'Achats HT')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('tva.deductible', 'TVA déductible')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">{t('tva.solde', 'Solde')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(parTrimestre).map(([trimestre, mois]) => (
                                <TrimestreLignes key={trimestre} trimestre={trimestre} mois={mois} euros={euros} t={t} />
                            ))}
                            {lignes.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan="6">
                                        {t('tva.aucune', 'Aucune facture émise ni reçue pour l\'instant.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-4 rounded-2xl bg-white p-5 text-sm text-slate-600 shadow-sm">
                <p className="font-semibold text-marine">{t('tva.regles', 'Règles appliquées')}</p>
                <p className="mt-2">
                    {t('tva.regles_texte', 'La TVA sur le carburant d\'un camion est déductible en totalité — la limite de 50 % ne concerne que les voitures. La taxe kilométrique belge (Viapass) est hors du champ de la TVA : rien à déduire. Les ventes en autoliquidation intracommunautaire ne portent pas de TVA : le preneur la déclare dans son pays.')}
                </p>
            </div>
        </AuthenticatedLayout>
    );
}

function TrimestreLignes({ trimestre, mois, euros, t }) {
    const somme = (champ) => mois.reduce((total, m) => total + m[champ], 0);

    return (
        <>
            {mois.map((m) => (
                <tr key={m.mois} className="border-b border-slate-50">
                    <td className="whitespace-nowrap px-4 py-3 capitalize text-slate-700">{m.libelle}</td>
                    <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{euros(m.ventes_ht)}</td>
                    <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{euros(m.collectee)}</td>
                    <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{euros(m.achats_ht)}</td>
                    <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{euros(m.deductible)}</td>
                    <td className="whitespace-nowrap px-4 py-3 text-right font-semibold text-marine">{euros(m.solde)}</td>
                </tr>
            ))}
            <tr className="border-b border-slate-100 bg-surface">
                <td className="whitespace-nowrap px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-marine">
                    {t('tva.total', 'Total')} {trimestre}
                </td>
                <td className="whitespace-nowrap px-4 py-2.5 text-right text-xs font-semibold text-slate-700">{euros(somme('ventes_ht'))}</td>
                <td className="whitespace-nowrap px-4 py-2.5 text-right text-xs font-semibold text-slate-700">{euros(somme('collectee'))}</td>
                <td className="whitespace-nowrap px-4 py-2.5 text-right text-xs font-semibold text-slate-700">{euros(somme('achats_ht'))}</td>
                <td className="whitespace-nowrap px-4 py-2.5 text-right text-xs font-semibold text-slate-700">{euros(somme('deductible'))}</td>
                <td className="whitespace-nowrap px-4 py-2.5 text-right text-xs font-bold text-marine">{euros(somme('solde'))}</td>
            </tr>
        </>
    );
}
