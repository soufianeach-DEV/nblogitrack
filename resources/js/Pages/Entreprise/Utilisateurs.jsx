import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTraduction } from '@/traduire';
import { Head, router, useForm } from '@inertiajs/react';

const ROLES = {
    ADMIN: ['entreprise.role_admin', 'Administrateur', 'entreprise.role_admin_aide', 'Commandes, factures et gestion des comptes'],
    ORDERS: ['entreprise.role_commandes', 'Commandes', 'entreprise.role_commandes_aide', 'Passe, suit et annule les expéditions'],
    BILLING: ['entreprise.role_comptabilite', 'Comptabilité', 'entreprise.role_comptabilite_aide', 'Consulte et règle les factures'],
};

export default function Utilisateurs({ entreprise, utilisateurs = [] }) {
    const t = useTraduction();
    const { data, setData, post, processing, errors, reset } = useForm({ first_name: '', last_name: '', email: '', role: 'ORDERS' });
    const champ = 'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine';

    const inviter = (e) => {
        e.preventDefault();
        post(route('company.users.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    const modifier = (u, changement) => router.patch(route('company.users.update', u.id), changement, { preserveScroll: true });

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">{t('entreprise.titre', 'Utilisateurs de l\'entreprise')}</h1>
                    <p className="text-sm text-slate-600">{entreprise}</p>
                </div>
            }
        >
            <Head title={t('entreprise.titre', 'Utilisateurs de l\'entreprise')} />

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="overflow-hidden rounded-2xl bg-white shadow-sm lg:col-span-2">
                    <ul className="divide-y divide-slate-100">
                        {utilisateurs.map((u) => (
                            <li key={u.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0">
                                    <p className={`font-semibold ${u.actif ? 'text-marine' : 'text-slate-400 line-through'}`}>
                                        {u.nom}{u.moi && <span className="ml-2 text-xs font-normal text-slate-500">({t('entreprise.vous', 'vous')})</span>}
                                    </p>
                                    <p className="truncate text-sm text-slate-600">{u.email}</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <select
                                        value={u.role ?? 'ORDERS'}
                                        onChange={(e) => modifier(u, { role: e.target.value })}
                                        aria-label={t('entreprise.role', 'Rôle')}
                                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                                    >
                                        {Object.entries(ROLES).map(([cle, r]) => <option key={cle} value={cle}>{t(r[0], r[1])}</option>)}
                                    </select>
                                    {! u.moi && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (u.actif && ! window.confirm(t('entreprise.confirmer_fermeture', 'Fermer l\'accès de ce compte ?'))) return;
                                                modifier(u, { actif: ! u.actif });
                                            }}
                                            className={`rounded-lg border px-3 py-1.5 text-xs font-semibold transition ${u.actif ? 'border-status-incident/40 text-status-incident hover:bg-status-incident/5' : 'border-marine text-marine hover:bg-marine/5'}`}
                                        >
                                            {u.actif ? t('entreprise.fermer_acces', 'Fermer l\'accès') : t('entreprise.rouvrir_acces', 'Rouvrir l\'accès')}
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="text-sm font-bold text-marine">{t('entreprise.inviter', 'Inviter un collègue')}</h2>
                    <p className="mt-1 text-xs text-slate-600">{t('entreprise.inviter_aide', 'Il reçoit un lien pour choisir son mot de passe.')}</p>
                    <form onSubmit={inviter} className="mt-3 space-y-3">
                        <label className="block text-sm font-medium text-marine">
                            {t('profil.prenom', 'Prénom')}
                            <input value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} className={champ} required maxLength={100} />
                            {errors.first_name && <span className="mt-1 block text-xs text-status-incident">{errors.first_name}</span>}
                        </label>
                        <label className="block text-sm font-medium text-marine">
                            {t('profil.nom', 'Nom')}
                            <input value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} className={champ} required maxLength={100} />
                            {errors.last_name && <span className="mt-1 block text-xs text-status-incident">{errors.last_name}</span>}
                        </label>
                        <label className="block text-sm font-medium text-marine">
                            {t('profil.email', 'Adresse e-mail')}
                            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={champ} required maxLength={255} />
                            {errors.email && <span className="mt-1 block text-xs text-status-incident">{errors.email}</span>}
                        </label>
                        <fieldset>
                            <legend className="text-sm font-medium text-marine">{t('entreprise.role', 'Rôle')}</legend>
                            {Object.entries(ROLES).map(([cle, r]) => (
                                <label key={cle} className="mt-2 flex items-start gap-2 text-sm">
                                    <input type="radio" name="role" value={cle} checked={data.role === cle} onChange={() => setData('role', cle)} className="mt-0.5 text-marine focus:ring-marine" />
                                    <span>
                                        <span className="font-semibold text-marine">{t(r[0], r[1])}</span>
                                        <span className="block text-xs text-slate-600">{t(r[2], r[3])}</span>
                                    </span>
                                </label>
                            ))}
                        </fieldset>
                        <button type="submit" disabled={processing} className="w-full rounded-lg bg-action px-4 py-2 text-sm font-bold text-marine-deep hover:bg-action-dark disabled:opacity-60">
                            {processing ? t('action.enregistrement', 'Enregistrement…') : t('entreprise.envoyer_invitation', 'Envoyer l\'invitation')}
                        </button>
                    </form>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
