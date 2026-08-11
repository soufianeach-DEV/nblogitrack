import { Link, usePage } from '@inertiajs/react';

export default function VitrineLayout({ children }) {
    const utilisateur = usePage().props.auth?.user;
    const lienNav = 'text-[15px] font-bold text-marine transition hover:text-brand-blue';

    return (
        <div className="flex min-h-screen flex-col bg-surface">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl items-center gap-8 px-4 py-3 sm:px-6">
                    <Link href={route('accueil')} className="shrink-0">
                        <img src="/images/logo-marine.png" alt="NBLogiTrack" className="h-12 w-auto sm:h-14" />
                    </Link>

                    <nav className="hidden items-center gap-6 md:flex">
                        <a href="/#services" className={lienNav}>Services</a>
                        <a href="/#tarifs" className={lienNav}>Tarifs</a>
                        <a href="/#apropos" className={lienNav}>À propos</a>
                    </nav>

                    <div className="ml-auto flex items-center gap-3">
                        {utilisateur ? (
                            <Link
                                href={route('dashboard')}
                                className="rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                            >
                                Mon espace
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')} className="hidden text-[15px] font-bold text-marine transition hover:text-brand-blue sm:block">
                                    Se connecter
                                </Link>
                                <Link
                                    href={route('devis.create')}
                                    className="rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                                >
                                    Demander un devis
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="bg-marine-deep">
                <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p>© {new Date().getFullYear()} NBLogiTrack. Expertise logistique belge.</p>
                    <p className="flex gap-6">
                        <span>Mentions légales</span>
                        <span>Confidentialité (RGPD)</span>
                        <span>Contact</span>
                    </p>
                </div>
            </footer>
        </div>
    );
}
