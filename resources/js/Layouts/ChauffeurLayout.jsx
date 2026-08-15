import ChoixLangue from '@/Components/ChoixLangue';
import Icone from '@/Components/Icone';
import { useTraduction } from '@/traduire';
import { Link, usePage } from '@inertiajs/react';

function Onglet({ href, actif, icone, children }) {
    return (
        <Link
            href={href}
            aria-current={actif ? 'page' : undefined}
            className={`flex flex-1 flex-col items-center gap-1 py-2.5 text-[11px] font-medium transition lg:flex-none lg:flex-row lg:gap-2 lg:rounded-lg lg:px-3 lg:py-2 lg:text-sm ${
                actif
                    ? 'text-action lg:bg-white/10 lg:text-white'
                    : 'text-slate-400 hover:text-white'
            }`}
        >
            <Icone nom={icone} className="h-6 w-6 lg:h-5 lg:w-5" />
            {children}
        </Link>
    );
}

/**
 * L'ecran du chauffeur.
 *
 * Il travaille au telephone, la mise en page part donc du mobile : barre
 * d'onglets sous le pouce, cibles larges. Sur un ecran large, la meme barre
 * remonte dans l'en-tete plutot que de laisser une bande vide en bas.
 */
export default function ChauffeurLayout({ children }) {
    const { auth } = usePage().props;
    const t = useTraduction();
    const route_actuelle = typeof route !== 'undefined' ? route().current() : null;

    const initiales = [auth.user.first_name, auth.user.last_name]
        .filter(Boolean)
        .map((mot) => mot[0])
        .join('');

    return (
        <div className="flex min-h-screen flex-col bg-surface">
            <header className="sticky top-0 z-20 bg-marine">
                <div className="mx-auto flex h-14 w-full max-w-5xl items-center gap-3 px-4">
                    <img src="/images/logo-blanc.png" alt="NBLogiTrack" className="h-7 w-auto" />

                    <nav className="ml-6 hidden items-center gap-1 lg:flex">
                        <Onglet href={route('missions.index')} actif={route_actuelle === 'missions.index'} icone="camion">
                            {t('mission.onglet', 'Missions')}
                        </Onglet>
                        <Onglet href={route('profile.edit')} actif={route_actuelle === 'profile.edit'} icone="profil">
                            {t('nav.profil', 'Mon profil')}
                        </Onglet>
                    </nav>

                    <div className="ml-auto flex items-center gap-3">
                        <ChoixLangue sombre />
                        <span className="hidden text-sm text-slate-300 sm:block">
                            {auth.user.first_name} {auth.user.last_name}
                        </span>
                        <span className="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-white">
                            {initiales}
                        </span>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            aria-label={t('nav.deconnexion', 'Déconnexion')}
                            className="rounded-lg p-2 text-slate-300 transition hover:bg-white/10 hover:text-white"
                        >
                            <Icone nom="sortie" className="h-5 w-5" />
                        </Link>
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-5xl flex-1 px-4 pb-24 pt-4 lg:pb-8">
                {children}
            </main>

            <nav className="fixed inset-x-0 bottom-0 z-20 flex border-t border-white/10 bg-marine lg:hidden">
                <Onglet href={route('missions.index')} actif={route_actuelle === 'missions.index'} icone="camion">
                    {t('mission.onglet', 'Missions')}
                </Onglet>
                <Onglet href={route('profile.edit')} actif={route_actuelle === 'profile.edit'} icone="profil">
                    {t('nav.profil', 'Mon profil')}
                </Onglet>
            </nav>
        </div>
    );
}
