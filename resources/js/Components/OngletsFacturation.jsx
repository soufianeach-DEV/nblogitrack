import { useTraduction } from '@/traduire';
import { Link } from '@inertiajs/react';

const ONGLETS = [
    { cle: 'ventes', etiquette: ['facturation.ventes', 'Ventes'], adresse: 'invoices.index' },
    { cle: 'achats', etiquette: ['facturation.achats', 'Achats'], adresse: 'purchases.index' },
    { cle: 'tva', etiquette: ['facturation.synthese_tva', 'Synthèse TVA'], adresse: 'purchases.tva' },
];

export default function OngletsFacturation({ actif }) {
    const t = useTraduction();

    return (
        <div className="mb-4 flex gap-8 border-b border-slate-200 text-sm font-semibold">
            {ONGLETS.map((onglet) => (
                onglet.cle === actif ? (
                    <span key={onglet.cle} className="border-b-2 border-action pb-2 text-marine">
                        {t(...onglet.etiquette)}
                    </span>
                ) : (
                    <Link
                        key={onglet.cle}
                        href={route(onglet.adresse)}
                        className="pb-2 text-slate-600 transition hover:text-marine"
                    >
                        {t(...onglet.etiquette)}
                    </Link>
                )
            ))}
        </div>
    );
}
