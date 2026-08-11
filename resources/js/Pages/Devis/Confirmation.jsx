import Icone from '@/Components/Icone';
import VitrineLayout from '@/Layouts/VitrineLayout';
import { Head, Link } from '@inertiajs/react';

export default function Confirmation({ devis }) {
    const ligne = (libelle, valeur) => (
        <div className="flex justify-between gap-4 border-b border-slate-100 px-5 py-3 last:border-0">
            <dt className="text-sm text-slate-600">{libelle}</dt>
            <dd className="text-right text-sm font-semibold text-marine">{valeur || '—'}</dd>
        </div>
    );

    return (
        <VitrineLayout>
            <Head title="Demande de devis envoyée" />

            <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6">
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm sm:p-12">
                    <span className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-status-delivered/10 text-status-delivered">
                        <Icone nom="coche" className="h-10 w-10" />
                    </span>

                    <h1 className="mt-6 text-3xl font-extrabold text-marine">Demande de devis envoyée !</h1>

                    <p className="mx-auto mt-4 max-w-lg leading-relaxed text-slate-600">
                        Merci. Votre demande a bien été transmise à notre équipe commerciale. Un conseiller
                        vous recontacte sous 24 h ouvrées avec une estimation adaptée à votre trajet et à
                        votre marchandise.
                    </p>

                    <p className="mt-6 inline-block rounded-lg bg-brand-blue/10 px-5 py-2.5 text-sm font-bold text-brand-blue">
                        Référence de votre demande : {devis.reference}
                    </p>

                    <dl className="mt-8 rounded-xl border border-slate-200 text-left">
                        {ligne('Trajet', devis.trajet)}
                        {ligne('Enlèvement souhaité', devis.enlevement)}
                        {ligne('Marchandise', devis.marchandise)}
                        {ligne('Options', devis.options.length ? devis.options.join(', ') : 'Aucune')}
                    </dl>

                    <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <Link
                            href={route('accueil')}
                            className="rounded-lg border border-slate-300 px-6 py-3 text-sm font-bold text-marine transition hover:bg-surface"
                        >
                            ← Retour à l'accueil
                        </Link>
                        <Link
                            href={route('register')}
                            className="rounded-lg bg-action px-6 py-3 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                        >
                            Créer mon espace client
                        </Link>
                    </div>
                </div>
            </div>
        </VitrineLayout>
    );
}
