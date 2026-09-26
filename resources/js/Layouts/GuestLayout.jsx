import MessagesFlash from '@/Components/MessagesFlash';
import BandeauTemoins from '@/Components/BandeauTemoins';
import ChoixLangue from '@/Components/ChoixLangue';
import { useTraduction } from '@/traduire';
import { usePage } from '@inertiajs/react';

export default function GuestLayout({ children, large = false }) {
    const t = useTraduction();
    const { paysDesservis = 0 } = usePage().props;

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden p-3">
            {}
            <div
                className="absolute inset-0 scale-105 bg-cover bg-center blur-sm"
                style={{ backgroundImage: "url('/images/login-bg.jpg')" }}
            />
            {}
            <div className="absolute inset-0 bg-marine-deep/70" />

            {}
            {/* La carte grandit avec l'ecran (voir .carte-connexion) : sur un
                grand moniteur, elle restait une vignette au milieu de la photo. */}
            <div className={'carte-connexion relative z-10 flex w-full overflow-hidden rounded-2xl bg-white shadow-2xl ' + (large ? 'max-w-6xl' : 'max-w-4xl')}>
                <div className={'hidden flex-col justify-between bg-gradient-to-br from-marine to-marine-deep p-10 text-white md:flex ' + (large ? 'w-2/5' : 'w-1/2')}>
                    <img src="/images/logo-blanc.png" alt="NBLogiTrack" className="w-full" />
                    <div>
                        <h2 className="text-3xl font-bold leading-tight">
                            {t('vitrine.baseline', 'Optimisez votre logistique B2B en toute confiance.')}
                        </h2>
                        <p className="mt-4 text-slate-300">
                            {t('vitrine.sous_titre', 'Commande, suivi, planification et facturation de vos transports routiers, en Belgique et en Europe.')}
                        </p>
                    </div>
                    {/* Des chiffres verifiables : les pays ou l'on peut commander
                        et les langues de l'application. Les volumes et taux de
                        fiabilite affiches auparavant etaient inventes. */}
                    <div className="flex gap-10">
                        {paysDesservis > 0 && (
                            <div>
                                <div className="text-2xl font-bold text-action">{paysDesservis}</div>
                                <div className="text-sm text-slate-300">{t('vitrine.pays_desservis', 'pays européens desservis')}</div>
                            </div>
                        )}
                        <div>
                            <div className="text-2xl font-bold text-action">FR · NL · EN</div>
                            <div className="text-sm text-slate-300">{t('vitrine.trois_langues', 'trois langues, jusqu\'aux factures')}</div>
                        </div>
                    </div>
                </div>

                <div className={large ? 'w-full p-5 md:w-3/5 md:px-9 md:py-5' : 'w-full p-8 md:w-1/2 md:p-10'}>
                    {}
                    <div className="flex justify-end">
                        <ChoixLangue />
                    </div>
                    <MessagesFlash className="mt-4" />
                    {children}
                </div>
            </div>

            <BandeauTemoins />
        </div>
    );
}
