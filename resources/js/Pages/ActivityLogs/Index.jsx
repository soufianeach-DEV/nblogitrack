import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';

const COULEUR_ACTION = {
    'order.created': 'bg-brand-blue/10 text-brand-blue',
    'order.assigned': 'bg-action/10 text-action-dark',
    'order.status_changed': 'bg-status-progress/10 text-status-progress',
    'client.registered': 'bg-slate-100 text-slate-600',
    'client.validated': 'bg-status-delivered/10 text-status-delivered',
    'client.rejected': 'bg-status-incident/10 text-status-incident',
    'quote.handled': 'bg-action/10 text-action-dark',
    'auth.login': 'bg-status-delivered/10 text-status-delivered',
    'auth.logout': 'bg-slate-100 text-slate-600',
    'auth.failed': 'bg-status-incident/10 text-status-incident',
    'auth.lockout': 'bg-status-incident/20 text-status-incident',
};

export default function Index({ logs, actions, filtres, stats }) {
    const t = useTraduction();
    const locale = useLocale();
    const [champs, setChamps] = useState({
        utilisateur: filtres.utilisateur ?? '',
        action: filtres.action ?? '',
        ip: filtres.ip ?? '',
        du: filtres.du ?? '',
        au: filtres.au ?? '',
    });
    const minuteur = useRef(null);

    const filtrer = (cle, valeur) => {
        const suivant = { ...champs, [cle]: valeur };
        setChamps(suivant);
        clearTimeout(minuteur.current);
        minuteur.current = setTimeout(() => {
            router.get(route('activity-logs.index'), suivant, {
                only: ['logs', 'filtres'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    };

    const reinitialiser = () => {
        setChamps({ utilisateur: '', action: '', ip: '', du: '', au: '' });
        router.get(route('activity-logs.index'), {}, { preserveScroll: true, replace: true });
    };

    const champCls = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine';

    const horodatage = (valeur) => new Date(valeur).toLocaleString(locale, {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit',
    });

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">{t('journal.titre', 'Journal d\'activité')}</h1>}>
            <Head title={t('journal.titre', 'Journal d\'activité')} />

            <div className="mb-5 grid gap-4 sm:grid-cols-3">
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('journal.entrees', 'Entrées enregistrées')}</p>
                    <p className="mt-1 text-2xl font-bold text-marine">{stats.total.toLocaleString(locale)}</p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('journal.aujourdhui', 'Aujourd\'hui')}</p>
                    <p className="mt-1 text-2xl font-bold text-marine">{stats.aujourdhui.toLocaleString(locale)}</p>
                </div>
                <div className="rounded-2xl bg-white p-5 shadow-sm">
                    <p className="text-xs uppercase tracking-wide text-slate-600">{t('journal.echecs', 'Échecs de connexion')}</p>
                    <p className="mt-1 text-2xl font-bold text-status-incident">{stats.echecs.toLocaleString(locale)}</p>
                </div>
            </div>

            <div className="mb-4 rounded-2xl bg-white p-5 shadow-sm">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <input
                        value={champs.utilisateur}
                        onChange={(e) => filtrer('utilisateur', e.target.value)}
                        placeholder={t('journal.filtre_utilisateur', 'Utilisateur ou e-mail')}
                        className={champCls}
                    />
                    <select value={champs.action} onChange={(e) => filtrer('action', e.target.value)} className={champCls}>
                        <option value="">{t('journal.toutes_actions', 'Toutes les actions')}</option>
                        {Object.entries(actions).map(([cle, libelle]) => (
                            <option key={cle} value={cle}>{libelle}</option>
                        ))}
                    </select>
                    <input
                        value={champs.ip}
                        onChange={(e) => filtrer('ip', e.target.value)}
                        placeholder={t('journal.ip', 'Adresse IP')}
                        className={champCls}
                    />
                    <input type="date" value={champs.du} onChange={(e) => filtrer('du', e.target.value)} className={champCls} />
                    <input type="date" value={champs.au} onChange={(e) => filtrer('au', e.target.value)} className={champCls} />
                </div>
                <button type="button" onClick={reinitialiser} className="mt-3 text-xs text-brand-blue hover:underline">
                    {t('journal.reinitialiser', 'Réinitialiser les filtres')}
                </button>
            </div>

            <div className="overflow-x-auto rounded-2xl bg-white shadow-sm">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-surface">
                        <tr className="text-left text-xs uppercase tracking-wide text-slate-600">
                            <th className="px-5 py-3">{t('journal.date', 'Date')}</th>
                            <th className="px-5 py-3">{t('journal.utilisateur', 'Utilisateur')}</th>
                            <th className="px-5 py-3">{t('tdb.action', 'Action')}</th>
                            <th className="px-5 py-3">{t('journal.description', 'Description')}</th>
                            <th className="px-5 py-3">{t('journal.ip', 'Adresse IP')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {logs.data.length === 0 && (
                            <tr>
                                <td colSpan="5" className="px-5 py-10 text-center text-slate-600">
                                    {t('journal.aucune', 'Aucune entrée ne correspond aux filtres.')}
                                </td>
                            </tr>
                        )}
                        {logs.data.map((log) => (
                            <tr key={log.id} className="align-top hover:bg-surface/60">
                                <td className="whitespace-nowrap px-5 py-3 text-xs text-slate-600">{horodatage(log.created_at)}</td>
                                <td className="px-5 py-3">
                                    {log.user ? (
                                        <>
                                            <span className="font-medium text-marine">{log.user.first_name} {log.user.last_name}</span>
                                            <span className="block text-xs text-slate-600">
                                                {t('roles.' + String(log.user.role).toLowerCase(), log.user.role)}
                                            </span>
                                        </>
                                    ) : (
                                        <span className="text-xs text-slate-600">—</span>
                                    )}
                                </td>
                                <td className="whitespace-nowrap px-5 py-3">
                                    <span className={'rounded-full px-2.5 py-0.5 text-xs font-medium ' + (COULEUR_ACTION[log.action] ?? 'bg-slate-100 text-slate-600')}>
                                        {actions[log.action] ?? log.action}
                                    </span>
                                </td>
                                <td className="px-5 py-3 text-slate-600">
                                    {log.description}
                                    {log.properties && (
                                        <span className="mt-1 block text-xs text-slate-600">
                                            {Object.entries(log.properties).map(([cle, valeur]) => `${cle} : ${valeur}`).join(' · ')}
                                        </span>
                                    )}
                                </td>
                                <td className="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600">{log.ip_address ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {logs.last_page > 1 && (
                <div className="mt-6 flex flex-wrap gap-1">
                    {logs.links.map((lien, i) => (
                        <Link
                            key={i}
                            href={lien.url ?? '#'}
                            preserveScroll
                            className={
                                'rounded-lg px-3 py-2 text-sm ' +
                                (lien.active ? 'bg-marine text-white' : lien.url ? 'bg-white text-marine hover:bg-slate-50' : 'bg-white text-slate-300')
                            }
                            dangerouslySetInnerHTML={{ __html: lien.label }}
                        />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
