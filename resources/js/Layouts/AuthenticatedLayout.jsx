import Dropdown from '@/Components/Dropdown';
import { Link, usePage } from '@inertiajs/react';

function SidebarLink({ href, active, icon, children }) {
    return (
        <Link
            href={href}
            className={
                'flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium transition ' +
                (active
                    ? 'bg-white/10 text-white'
                    : 'text-slate-300 hover:bg-white/5 hover:text-white')
            }
        >
            <span className="material-symbols-outlined text-[22px]">{icon}</span>
            {children}
        </Link>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const { user, canPlan, canViewLogs } = usePage().props.auth;

    return (
        <div className="min-h-screen bg-surface">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-marine px-4 py-6 md:flex">
                <div className="mb-8 px-2">
<img src="/images/logo-blanc.png" alt="NBLogiTrack" className="w-full" />                    <p className="mt-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                        Logistique B2B
                    </p>
                </div>

                <nav className="flex-1 space-y-1">
                    <SidebarLink href={route('dashboard')} active={route().current('dashboard')} icon="dashboard">
                        Tableau de bord
                    </SidebarLink>
                    <SidebarLink href={route('transport-orders.index')} active={route().current('transport-orders.index')} icon="inventory_2">
                        Ordres de transport
                    </SidebarLink>
                    {canPlan && (
                        <SidebarLink href={route('planning.index')} active={route().current('planning.index')} icon="event_available">
                            Planification
                        </SidebarLink>
                    )}
                    <SidebarLink href={route('tracking.show')} active={route().current('tracking.show')} icon="local_shipping">
                        Suivi
                    </SidebarLink>
                    {canViewLogs && (
                        <SidebarLink href={route('activity-logs.index')} active={route().current('activity-logs.index')} icon="history">
                            Journal d'activité
                        </SidebarLink>
                    )}
                </nav>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white"
                >
                    <span className="material-symbols-outlined text-[22px]">logout</span>
                    Déconnexion
                </Link>
            </aside>

            <div className="md:pl-64">
                <header className="sticky top-0 z-20 flex h-16 items-center justify-end border-b border-slate-200 bg-white px-6">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="flex items-center gap-2 text-sm font-medium text-marine">
                                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-marine text-xs font-semibold text-white">
                                    {user.first_name?.[0]}{user.last_name?.[0]}
                                </span>
                                {user.first_name} {user.last_name}
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>Profil</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">
                                Déconnexion
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </header>

                <main className="p-6 lg:p-8">
                    {header && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}