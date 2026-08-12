import Dropdown from '@/Components/Dropdown';
import Icone from '@/Components/Icone';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const ROLES = {
    ADMIN: 'Superviseur',
    PLANNER: 'Planificateur',
    CLIENT: 'Client',
    DRIVER: 'Chauffeur',
};

function LienMenu({ href, active, icone, onClick, children }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={
                'flex items-center gap-3 border-l-[3px] px-4 py-2 text-sm transition ' +
                (active
                    ? 'border-action bg-white/5 font-semibold text-action'
                    : 'border-transparent font-medium text-slate-300 hover:bg-white/5 hover:text-white')
            }
        >
            <Icone nom={icone} className="h-5 w-5 shrink-0" />
            {children}
        </Link>
    );
}

function Groupe({ titre, children }) {
    return (
        <div className="mt-4">
            <p className="px-4 pb-1.5 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">
                {titre}
            </p>
            <div>{children}</div>
        </div>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const { user, canPlan, canViewLogs, canValidateClients, canHandleQuotes, canViewFleet } = usePage().props.auth;
    const [menuOuvert, setMenuOuvert] = useState(false);
    const [recherche, setRecherche] = useState('');

    const estAdmin = canValidateClients;

    useEffect(() => {
        const echap = (e) => e.key === 'Escape' && setMenuOuvert(false);
        window.addEventListener('keydown', echap);

        return () => window.removeEventListener('keydown', echap);
    }, []);

    const fermer = () => setMenuOuvert(false);

    // L'administrateur cherche une entreprise, les autres une expedition.
    const rechercher = (e) => {
        e.preventDefault();

        estAdmin
            ? router.get(route('clients.index'), { etat: 'tout', q: recherche })
            : router.get(route('transport-orders.index'), { tracking: recherche });
    };

    const marque = (
        <div className="px-4">
            <img src="/images/logo-blanc.png" alt="NBLogiTrack" className="mx-auto w-full max-w-[132px]" />
            <p className="mt-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                {estAdmin ? 'Administration centrale' : 'Logistique B2B'}
            </p>
        </div>
    );

    const nouvelleExpedition = (
        <div className="mt-4 px-4">
            <Link
                href={route('transport-orders.create')}
                onClick={fermer}
                className="flex items-center justify-center gap-2 rounded-lg bg-action px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-marine-deep transition hover:bg-action-dark"
            >
                <Icone nom="plus" className="h-4 w-4" />
                Nouvelle expédition
            </Link>
        </div>
    );

    const navigation = (
        <>
            <Groupe titre="Opérations">
                <LienMenu href={route('dashboard')} active={route().current('dashboard')} icone="dashboard" onClick={fermer}>
                    Tableau de bord
                </LienMenu>
                <LienMenu href={route('transport-orders.index')} active={route().current('transport-orders.*')} icone="colis" onClick={fermer}>
                    Ordres de transport
                </LienMenu>
                {canPlan && (
                    <LienMenu href={route('planning.index')} active={route().current('planning.index')} icone="planning" onClick={fermer}>
                        Planification
                    </LienMenu>
                )}
                {! canPlan && (
                    <LienMenu href={route('tracking.show')} active={route().current('tracking.show')} icone="camion" onClick={fermer}>
                        Suivi
                    </LienMenu>
                )}
                {canHandleQuotes && (
                    <LienMenu href={route('quotes.index')} active={route().current('quotes.index')} icone="journal" onClick={fermer}>
                        Demandes de devis
                    </LienMenu>
                )}
                {canValidateClients && (
                    <LienMenu href={route('clients.index')} active={route().current('clients.index')} icone="valide" onClick={fermer}>
                        Entreprises
                    </LienMenu>
                )}
                {canViewFleet && (
                    <>
                        <LienMenu href={route('drivers.index')} active={route().current('drivers.index')} icone="profil" onClick={fermer}>
                            Chauffeurs
                        </LienMenu>
                        <LienMenu href={route('vehicles.index')} active={route().current('vehicles.index')} icone="camion" onClick={fermer}>
                            Véhicules
                        </LienMenu>
                    </>
                )}
            </Groupe>

            <Groupe titre="Finance &amp; data">
                <LienMenu href={route('invoices.index')} active={route().current('invoices.index')} icone="facture" onClick={fermer}>
                    Facturation
                </LienMenu>
            </Groupe>

            {canViewLogs && (
                <Groupe titre="Système">
                    <LienMenu href={route('activity-logs.index')} active={route().current('activity-logs.index')} icone="journal" onClick={fermer}>
                        Journaux
                    </LienMenu>
                </Groupe>
            )}
        </>
    );

    const pied = (
        <div className="mt-4 border-t border-white/10 px-1 pt-2">
            <Link
                href={route('profile.edit')}
                onClick={fermer}
                className="flex items-center gap-3 px-4 py-1.5 text-sm font-medium text-slate-300 transition hover:text-white"
            >
                <Icone nom="aide" className="h-5 w-5 shrink-0" />
                Mon profil
            </Link>
            <Link
                href={route('logout')}
                method="post"
                as="button"
                className="flex w-full items-center gap-3 px-4 py-1.5 text-sm font-medium text-slate-300 transition hover:text-white"
            >
                <Icone nom="sortie" className="h-5 w-5 shrink-0" />
                Déconnexion
            </Link>
        </div>
    );

    const panneau = (
        <>
            {marque}
            {nouvelleExpedition}
            <nav className="mt-1 flex-1">{navigation}</nav>
            {pied}
        </>
    );

    return (
        <div className="min-h-screen bg-surface">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col overflow-y-auto bg-marine-deep py-4 md:flex">
                {panneau}
            </aside>

            {menuOuvert && (
                <div className="fixed inset-0 z-40 md:hidden">
                    <div className="absolute inset-0 bg-marine-deep/70" onClick={fermer} aria-hidden="true" />
                    <aside className="absolute inset-y-0 left-0 flex w-72 max-w-[85%] flex-col overflow-y-auto bg-marine-deep py-4 shadow-xl">
                        <button
                            type="button"
                            onClick={fermer}
                            aria-label="Fermer le menu"
                            className="absolute right-4 top-5 rounded-lg p-1 text-slate-300 transition hover:bg-white/10 hover:text-white"
                        >
                            <Icone nom="fermer" className="h-6 w-6" />
                        </button>
                        {panneau}
                    </aside>
                </div>
            )}

            <div className="md:pl-64">
                <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
                    <button
                        type="button"
                        onClick={() => setMenuOuvert(true)}
                        aria-label="Ouvrir le menu"
                        aria-expanded={menuOuvert}
                        className="rounded-lg p-2 text-marine transition hover:bg-surface md:hidden"
                    >
                        <Icone nom="menu" className="h-6 w-6" />
                    </button>

                    <form onSubmit={rechercher} className="relative hidden w-full max-w-md sm:block">
                        <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-600">
                            <Icone nom="recherche" className="h-5 w-5" />
                        </span>
                        <input
                            value={recherche}
                            onChange={(e) => setRecherche(e.target.value)}
                            placeholder={estAdmin ? 'Rechercher une entreprise…' : 'Rechercher une expédition…'}
                            aria-label={estAdmin ? 'Rechercher une entreprise' : 'Rechercher une expédition'}
                            className="w-full rounded-lg border-slate-200 bg-surface py-2 pl-10 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                    </form>

                    <div className="ml-auto">
                        <Dropdown>
                            <Dropdown.Trigger>
                                <button className="flex items-center gap-3 text-left">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-blue/10 text-xs font-bold text-brand-blue">
                                        {user.first_name?.[0]}{user.last_name?.[0]}
                                    </span>
                                    <span className="hidden leading-tight sm:block">
                                        <span className="block text-sm font-bold text-marine">
                                            {user.first_name} {user.last_name}
                                        </span>
                                        <span className="block text-[11px] uppercase tracking-wider text-slate-600">
                                            {ROLES[user.role] ?? user.role}
                                        </span>
                                    </span>
                                </button>
                            </Dropdown.Trigger>
                            <Dropdown.Content>
                                <Dropdown.Link href={route('profile.edit')}>Profil</Dropdown.Link>
                                <Dropdown.Link href={route('logout')} method="post" as="button">
                                    Déconnexion
                                </Dropdown.Link>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </header>

                <main className="p-4 sm:p-6 lg:p-8">
                    {header && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}