import ChoixLangue from '@/Components/ChoixLangue';
import { useTraduction } from '@/traduire';
import { Head, Link, usePage } from '@inertiajs/react';

function blocs(corps) {
    const sortie = [];
    let liste = null;

    const fermerListe = () => {
        if (liste) {
            sortie.push({ type: 'liste', elements: liste });
            liste = null;
        }
    };

    for (const ligne of (corps ?? '').split('\n')) {
        const texte = ligne.trim();

        if (texte === '') {
            fermerListe();
        } else if (texte.startsWith('## ')) {
            fermerListe();
            sortie.push({ type: 'titre', texte: texte.slice(3) });
        } else if (texte.startsWith('- ')) {
            liste = [...(liste ?? []), texte.slice(2)];
        } else {
            fermerListe();
            sortie.push({ type: 'paragraphe', texte });
        }
    }

    fermerListe();

    return sortie;
}

export default function Show({ page }) {
    const t = useTraduction();
    const { auth, pages_pied: pagesPied = [] } = usePage().props;

    return (
        <div className="flex min-h-screen flex-col bg-surface">
            <Head title={page.titre} />

            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl items-center gap-8 px-4 py-3 sm:px-6">
                    <Link href={route('accueil')} className="shrink-0 transition-transform duration-300 hover:scale-105">
                        <img src="/images/logo-marine.png" alt="NBLogiTrack" className="h-14 w-auto sm:h-16" />
                    </Link>
                    <div className="ml-auto flex items-center gap-3">
                        <ChoixLangue />
                        <Link
                            href={auth?.user ? route('dashboard') : route('login')}
                            className="rounded-lg bg-action px-4 py-2 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                        >
                            {auth?.user
                                ? t('accueil.mon_espace', 'Mon espace')
                                : t('nav.connexion', 'Se connecter')}
                        </Link>
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-12 sm:px-6 sm:py-16">
                <article className="rounded-2xl bg-white p-6 shadow-sm sm:p-12">
                    <h1 className="text-3xl font-bold text-marine sm:text-4xl">{page.titre}</h1>

                    {page.repli && (
                        <p className="mt-4 rounded-lg bg-surface px-4 py-3 text-sm text-slate-600">
                            {t('page.repli', 'Cette page n\'est pas encore disponible dans votre langue : elle est affichée en français.')}
                        </p>
                    )}

                    <div className="mt-8 text-[15px] leading-relaxed text-slate-700">
                        {blocs(page.corps).map((bloc, i) => {
                            if (bloc.type === 'titre') {
                                return (
                                    <h2
                                        key={i}
                                        className="mb-3 mt-10 border-b border-slate-100 pb-2 text-lg font-bold text-marine first:mt-0"
                                    >
                                        {bloc.texte}
                                    </h2>
                                );
                            }

                            if (bloc.type === 'liste') {
                                return (
                                    <ul key={i} className="mb-4 ml-5 list-disc space-y-1.5 marker:text-slate-400">
                                        {bloc.elements.map((e, j) => <li key={j}>{e}</li>)}
                                    </ul>
                                );
                            }

                            return <p key={i} className="mb-4">{bloc.texte}</p>;
                        })}
                    </div>

                    {page.mise_a_jour && (
                        <p className="mt-10 border-t border-slate-100 pt-4 text-xs text-slate-600">
                            {t('page.mise_a_jour', 'Dernière mise à jour le :date', { date: page.mise_a_jour })}
                        </p>
                    )}
                </article>
            </main>

            <footer className="bg-marine-deep">
                <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-8 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p>© {new Date().getFullYear()} NBLogiTrack. {t('accueil.droits', 'Tous droits réservés.')}</p>
                    <nav className="flex flex-wrap gap-4">
                        {pagesPied.map((p) => (
                            <Link key={p.href} href={p.href} className="transition-colors hover:text-action">
                                {p.libelle}
                            </Link>
                        ))}
                    </nav>
                </div>
            </footer>
        </div>
    );
}
