import MessagesFlash from '@/Components/MessagesFlash';
import BandeauTemoins, { ouvrirTemoins } from '@/Components/BandeauTemoins';
import ChoixLangue from '@/Components/ChoixLangue';
import { useTraduction } from '@/traduire';
import { Link, usePage } from '@inertiajs/react';

export default function VitrineLayout({ children }) {
    const { auth, pages_pied: pagesPied = [] } = usePage().props;
    const utilisateur = auth?.user;
    const t = useTraduction();
    const lienNav = 'text-[15px] font-bold text-marine transition hover:text-brand-blue';

    return (
        <div className="flex min-h-screen flex-col bg-surface">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl items-center gap-8 px-4 py-3 sm:px-6">
                    <Link href={route('accueil')} className="shrink-0">
                        <img src="/images/logo-marine.png" alt="NBLogiTrack" className="h-12 w-auto sm:h-14" />
                    </Link>

                    <nav className="hidden items-center gap-6 md:flex">
                        <a href="/#services" className={lienNav}>{t('nav.services', 'Services')}</a>
                        <Link href={route('tarifs.index')} className={lienNav}>{t('nav.tarifs', 'Tarifs')}</Link>
                        <a href="/#apropos" className={lienNav}>{t('nav.a_propos', 'À propos')}</a>
                    </nav>

                    <div className="ml-auto flex items-center gap-3">
                        <ChoixLangue />

                        {utilisateur ? (
                            <Link
                                href={route('dashboard')}
                                className="rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                            >
                                {t('accueil.mon_espace', 'Mon espace')}
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')} className="hidden text-[15px] font-bold text-marine transition hover:text-brand-blue sm:block">
                                    {t('nav.connexion', 'Se connecter')}
                                </Link>
                                <Link
                                    href={route('devis.create')}
                                    className="rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                                >
                                    {t('nav.devis', 'Demander un devis')}
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main className="flex-1">
                <MessagesFlash className="mx-auto max-w-6xl px-4 pt-4" />
                {children}
            </main>

            <footer className="bg-marine-deep">
                <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p>© {new Date().getFullYear()} NBLogiTrack SRL · BE 0123.456.749 · {t('accueil.expertise', 'Expertise logistique belge')}.</p>
                    <nav className="flex flex-wrap gap-4">
                        {pagesPied.map((p) => (
                            <Link key={p.href} href={p.href} className="transition-colors hover:text-action">
                                {p.libelle}
                            </Link>
                        ))}
                        <a href="mailto:info@nblogitrack.be" className="transition-colors hover:text-action">
                            {t('nav.contact', 'Contact')}
                        </a>
                        <button type="button" onClick={ouvrirTemoins} className="transition-colors hover:text-action">
                            {t('temoins.gerer', 'Gérer les cookies')}
                        </button>
                    </nav>
                </div>
            </footer>

            <BandeauTemoins />
        </div>
    );
}
