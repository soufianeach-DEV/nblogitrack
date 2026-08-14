import { Link } from '@inertiajs/react';

const ONGLETS = [
    { cle: 'ventes', libelle: 'Ventes', adresse: 'invoices.index' },
    { cle: 'achats', libelle: 'Achats', adresse: 'purchases.index' },
    { cle: 'tva', libelle: 'Synthèse TVA', adresse: 'purchases.tva' },
];

export default function OngletsFacturation({ actif }) {
    return (
        <div className="mb-4 flex gap-8 border-b border-slate-200 text-sm font-semibold">
            {ONGLETS.map((onglet) => (
                onglet.cle === actif ? (
                    <span key={onglet.cle} className="border-b-2 border-action pb-2 text-marine">
                        {onglet.libelle}
                    </span>
                ) : (
                    <Link
                        key={onglet.cle}
                        href={route(onglet.adresse)}
                        className="pb-2 text-slate-600 transition hover:text-marine"
                    >
                        {onglet.libelle}
                    </Link>
                )
            ))}
        </div>
    );
}
