import Icone from '@/Components/Icone';
import VitrineLayout from '@/Layouts/VitrineLayout';
import { useTraduction } from '@/traduire';
import { Head, Link } from '@inertiajs/react';

export default function Confirmation({ devis }) {
    const t = useTraduction();
    const ligne = (libelle, valeur) => (
        <div className="flex justify-between gap-4 border-b border-slate-100 px-5 py-3 last:border-0">
            <dt className="text-sm text-slate-600">{libelle}</dt>
            <dd className="text-right text-sm font-semibold text-marine">{valeur || '—'}</dd>
        </div>
    );

    return (
        <VitrineLayout>
            <Head title={t('devis.envoye_titre', 'Demande de devis envoyée')} />

            <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6">
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm sm:p-12">
                    <span className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-status-delivered/10 text-status-delivered">
                        <Icone nom="coche" className="h-10 w-10" />
                    </span>

                    <h1 className="mt-6 text-3xl font-extrabold text-marine">{t('devis.envoye_titre', 'Demande de devis envoyée')} !</h1>

                    <p className="mx-auto mt-4 max-w-lg leading-relaxed text-slate-600">
                        {t('devis.envoye_texte', 'Merci. Votre demande a bien été transmise à notre équipe commerciale. Un conseiller vous recontacte sous 24 h ouvrées avec une estimation adaptée à votre trajet et à votre marchandise.')}
                    </p>

                    <p className="mt-6 inline-block rounded-lg bg-brand-blue/10 px-5 py-2.5 text-sm font-bold text-brand-blue">
                        {t('devis.reference', 'Référence de votre demande :')} {devis.reference}
                    </p>

                    <dl className="mt-8 rounded-xl border border-slate-200 text-left">
                        {ligne(t('devis.trajet', 'Trajet'), devis.trajet)}
                        {ligne(t('devis.enlevement_souhaite', 'Enlèvement souhaité'), devis.enlevement)}
                        {ligne(t('devis.marchandise', 'Marchandise'), devis.marchandise)}
                        {ligne(t('devis.options', 'Options'), devis.options.length ? devis.options.join(', ') : t('devis.aucune', 'Aucune'))}
                    </dl>

                    <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <Link
                            href={route('accueil')}
                            className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-6 py-3 text-sm font-bold text-marine transition hover:bg-surface"
                        >
                            <Icone nom="retour" className="h-4 w-4" />
                            {t('devis.retour_accueil', 'Retour à l\'accueil')}
                        </Link>
                        <Link
                            href={route('register')}
                            className="rounded-lg bg-action px-6 py-3 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                        >
                            {t('devis.creer_espace', 'Créer mon espace client')}
                        </Link>
                    </div>
                </div>
            </div>
        </VitrineLayout>
    );
}
