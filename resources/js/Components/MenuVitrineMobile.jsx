import Icone from '@/Components/Icone';
import { useTraduction } from '@/traduire';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Le menu du site public sur petit ecran. Sous 768 px, les liens de
 * l'en-tete et « Se connecter » etaient caches sans rien pour les
 * remplacer : un client qui revenait ne trouvait plus la connexion.
 */
export default function MenuVitrineMobile({ ancres = '/#' }) {
    const t = useTraduction();
    const { auth } = usePage().props;
    const [ouvert, setOuvert] = useState(false);

    const lien = 'block rounded-lg px-3 py-2.5 text-[15px] font-semibold text-marine transition hover:bg-surface';

    return (
        <div className="md:hidden">
            <button
                type="button"
                onClick={() => setOuvert(! ouvert)}
                aria-expanded={ouvert}
                aria-label={ouvert ? t('nav.fermer_menu', 'Fermer le menu') : t('nav.ouvrir_menu', 'Ouvrir le menu')}
                className="rounded-lg p-2 text-marine transition hover:bg-surface"
            >
                <Icone nom="menu" className="h-6 w-6" />
            </button>

            {ouvert && (
                <nav className="absolute inset-x-0 top-full z-40 border-b border-slate-200 bg-white px-4 py-3 shadow-lg">
                    <a href={`${ancres}services`} onClick={() => setOuvert(false)} className={lien}>{t('nav.services', 'Services')}</a>
                    <Link href={route('tarifs.index')} className={lien}>{t('nav.tarifs', 'Tarifs')}</Link>
                    <a href={`${ancres}apropos`} onClick={() => setOuvert(false)} className={lien}>{t('nav.a_propos', 'À propos')}</a>
                    <Link href={route('tracking.show')} className={lien}>{t('nav.suivi', 'Suivre un envoi')}</Link>
                    {auth?.user ? (
                        <Link href={route('dashboard')} className={lien}>{t('accueil.mon_espace', 'Mon espace')}</Link>
                    ) : (
                        <Link href={route('login')} className={lien}>{t('nav.connexion', 'Se connecter')}</Link>
                    )}
                </nav>
            )}
        </div>
    );
}
