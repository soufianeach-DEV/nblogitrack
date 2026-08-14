import BoutonRetour from '@/Components/BoutonRetour';
import Icone from '@/Components/Icone';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const euros = (montant) => Number(montant).toLocaleString('fr-FR', {
    style: 'currency',
    currency: 'EUR',
});

export default function Paiement({ reference, facture_id, montant, regle, enregistre }) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <BoutonRetour href={route('invoices.index')}>Facturation</BoutonRetour>
                    <h1 className="mt-2 text-2xl font-bold text-marine">Paiement</h1>
                </div>
            }
        >
            <Head title={'Paiement ' + reference} />

            <div className="mx-auto max-w-xl rounded-2xl bg-white p-8 text-center shadow-sm">
                {regle ? (
                    <>
                        <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-status-delivered/10 text-status-delivered">
                            <Icone nom="facture" className="h-7 w-7" />
                        </span>
                        <h2 className="mt-4 text-xl font-bold text-marine">Paiement accepté</h2>
                        <p className="mt-2 text-slate-600">
                            {euros(montant)} pour la facture <span className="font-mono font-semibold">{reference}</span>.
                        </p>
                        {! enregistre && (
                            <p className="mt-4 rounded-lg bg-surface px-4 py-3 text-sm text-slate-600">
                                Votre banque a accepté le paiement. Son enregistrement définitif nous
                                parvient par une notification signée de l'opérateur, ce qui prend
                                quelques secondes : la facture peut rester affichée comme envoyée un
                                court instant.
                            </p>
                        )}
                    </>
                ) : (
                    <>
                        <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                            <Icone nom="facture" className="h-7 w-7" />
                        </span>
                        <h2 className="mt-4 text-xl font-bold text-marine">Paiement non confirmé</h2>
                        <p className="mt-2 text-slate-600">
                            Nous n'avons pas reçu de confirmation pour la facture{' '}
                            <span className="font-mono font-semibold">{reference}</span>. Si vous
                            venez de régler, patientez un instant puis rechargez cette page.
                        </p>
                    </>
                )}

                <div className="mt-6 flex justify-center gap-3">
                    <Link
                        href={route('invoices.show', facture_id)}
                        className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-marine transition hover:bg-surface"
                    >
                        Voir la facture
                    </Link>
                    <Link
                        href={route('invoices.index')}
                        className="rounded-lg bg-marine px-5 py-2 text-sm font-bold text-white transition hover:bg-marine-deep"
                    >
                        Mes factures
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
