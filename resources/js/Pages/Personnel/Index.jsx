import BarreFiltres from '@/Components/BarreFiltres';
import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTraduction } from '@/traduire';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const COULEURS = {
    DRIVER: 'bg-brand-blue/10 text-brand-blue',
    PLANNER: 'bg-action/15 text-action-dark',
    ADMIN: 'bg-marine/10 text-marine',
};

function Creation({ roles, permis, statuts, onFermer }) {
    const t = useTraduction();
    const { data, setData, post, processing, errors, reset } = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        role: 'DRIVER',
        license_number: '',
        license_type: 'CE',
        license_expiry: '',
        employment_status: 'OUVRIER',
        hired_on: new Date().toISOString().slice(0, 10),
    });

    const enregistrer = (e) => {
        e.preventDefault();
        post(route('staff.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onFermer();
            },
        });
    };

    const champ = 'mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine';
    const intitule = 'text-xs uppercase tracking-wide text-slate-600';
    const erreur = (nom) => errors[nom] && <p className="mt-1 text-xs text-status-incident">{errors[nom]}</p>;

    return (
        <form onSubmit={enregistrer} className="p-6">
            <h2 className="text-lg font-bold text-marine">{t('personnel.nouveau', 'Nouveau compte')}</h2>
            <p className="mt-1 text-sm text-slate-600">
                {t('personnel.mdp_par_lien', 'L\'intéressé choisira son mot de passe par un lien envoyé à son adresse.')}
            </p>

            <div className="mt-5 grid gap-3 sm:grid-cols-2">
                <div>
                    <label htmlFor="prenom" className={intitule}>{t('auth.prenom', 'Prénom')}</label>
                    <input id="prenom" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} className={champ} required />
                    {erreur('first_name')}
                </div>
                <div>
                    <label htmlFor="nom" className={intitule}>{t('auth.nom', 'Nom')}</label>
                    <input id="nom" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} className={champ} required />
                    {erreur('last_name')}
                </div>
                <div>
                    <label htmlFor="courriel" className={intitule}>{t('auth.adresse_email', 'Adresse électronique')}</label>
                    <input id="courriel" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={champ} required />
                    {erreur('email')}
                </div>
                <div>
                    <label htmlFor="tel" className={intitule}>{t('auth.telephone', 'Téléphone')}</label>
                    <input id="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} className={champ} />
                    {erreur('phone')}
                </div>
                <div>
                    <label htmlFor="role" className={intitule}>{t('personnel.role', 'Rôle')}</label>
                    <select id="role" value={data.role} onChange={(e) => setData('role', e.target.value)} className={champ}>
                        {Object.entries(roles).map(([valeur, libelle]) => (
                            <option key={valeur} value={valeur}>{libelle}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="embauche" className={intitule}>{t('personnel.entree_service', 'Entrée en service')}</label>
                    <input id="embauche" type="date" value={data.hired_on} onChange={(e) => setData('hired_on', e.target.value)} className={champ} />
                    {erreur('hired_on')}
                </div>
            </div>

            {data.role === 'DRIVER' && (
                <div className="mt-4 rounded-lg bg-surface p-4">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">{t('personnel.titres_conduite', 'Titres de conduite')}</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label htmlFor="permis-num" className={intitule}>{t('personnel.numero_permis', 'Numéro de permis')}</label>
                            <input id="permis-num" value={data.license_number} onChange={(e) => setData('license_number', e.target.value)} className={champ} />
                            {erreur('license_number')}
                        </div>
                        <div>
                            <label htmlFor="permis-type" className={intitule}>{t('personnel.categorie', 'Catégorie')}</label>
                            <select id="permis-type" value={data.license_type} onChange={(e) => setData('license_type', e.target.value)} className={champ}>
                                {permis.map((p) => <option key={p} value={p}>{p}</option>)}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="permis-fin" className={intitule}>{t('personnel.permis_valable', 'Permis valable jusqu\'au')}</label>
                            <input id="permis-fin" type="date" value={data.license_expiry} onChange={(e) => setData('license_expiry', e.target.value)} className={champ} />
                            {erreur('license_expiry')}
                        </div>
                        <div>
                            <label htmlFor="statut" className={intitule}>{t('commun.statut', 'Statut')}</label>
                            <select id="statut" value={data.employment_status} onChange={(e) => setData('employment_status', e.target.value)} className={champ}>
                                {Object.entries(statuts).map(([valeur, libelle]) => (
                                    <option key={valeur} value={valeur}>{libelle}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <p className="mt-3 text-xs text-slate-600">
                        {t('personnel.qualifs_ensuite', 'La visite médicale, la qualification code 95 et la carte tachygraphe se complètent ensuite depuis la fiche du chauffeur.')}
                    </p>
                </div>
            )}

            <div className="mt-5 flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" onClick={onFermer} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-surface">
                    {t('action.annuler', 'Annuler')}
                </button>
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-lg bg-marine px-5 py-2 text-sm font-bold text-white transition hover:bg-marine-deep disabled:opacity-50"
                >
                    {processing ? t('personnel.creation', 'Création…') : t('personnel.creer', 'Créer le compte')}
                </button>
            </div>
        </form>
    );
}

export default function Index({ comptes = [], roles = {}, permis = [], statuts = {}, compteurs, filtres = {} }) {
    const t = useTraduction();
    const [creer, setCreer] = useState(false);
    const flash = usePage().props.flash ?? {};
    const { errors } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-marine">{t('nav.personnel', 'Personnel')}</h1>
                        <p className="text-sm text-slate-600">
                            {t('personnel.compte', ':total comptes, :actifs actifs', {
                                total: compteurs.total,
                                actifs: compteurs.actifs,
                            })}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setCreer(true)}
                        className="rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-marine transition hover:bg-action-dark"
                    >
                        + {t('personnel.nouveau', 'Nouveau compte')}
                    </button>
                </div>
            }
        >
            <Head title={t('nav.personnel', 'Personnel')} />

            {flash.success && (
                <p className="mb-4 rounded-lg bg-status-delivered/10 px-4 py-3 text-sm font-semibold text-status-delivered">
                    {flash.success}
                </p>
            )}
            {errors.is_active && (
                <p className="mb-4 rounded-lg bg-status-incident/10 px-4 py-3 text-sm font-semibold text-status-incident">
                    {errors.is_active}
                </p>
            )}

            <BarreFiltres
                adresse={route('staff.index')}
                filtres={filtres}
                placeholder={t('personnel.filtre', 'Nom, prénom, adresse électronique…')}
                listes={[{
                    champ: 'role',
                    intitule: t('personnel.tous_roles', 'Tous les rôles'),
                    options: Object.entries(roles).map(([valeur, libelle]) => ({ valeur, libelle })),
                }]}
                compteurs={[
                    { libelle: t('parc.tous', 'Tous'), valeur: null, nombre: compteurs.total },
                    { libelle: t('personnel.actifs', 'Actifs'), valeur: 'actifs', nombre: compteurs.actifs },
                    { libelle: t('personnel.desactives', 'Désactivés'), valeur: 'inactifs', nombre: compteurs.inactifs },
                ]}
            />

            <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('personnel.personne', 'Personne')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('personnel.role', 'Rôle')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('auth.telephone', 'Téléphone')}</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">{t('ordres.etat', 'État')}</th>
                                <th scope="col" className="px-4 py-3"><span className="sr-only">{t('tdb.action', 'Action')}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            {comptes.map((c) => (
                                <tr key={c.id} className="border-b border-slate-50 last:border-0">
                                    <td className="px-4 py-3">
                                        <span className="block max-w-[16rem] truncate font-semibold text-marine">{c.nom}</span>
                                        <span className="block max-w-[16rem] truncate text-xs text-slate-600">{c.email}</span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${COULEURS[c.role_code] ?? 'bg-slate-100 text-slate-700'}`}>
                                            {c.role}
                                        </span>
                                        {c.permis && <span className="ml-2 text-xs text-slate-600">{t('suivi.permis', 'Permis').toLowerCase()} {c.permis}</span>}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{c.telephone ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                                            c.actif ? 'bg-status-delivered/10 text-status-delivered' : 'bg-slate-100 text-slate-700'
                                        }`}>
                                            {c.actif ? t('personnel.actif', 'Actif') : t('personnel.desactive', 'Désactivé')}
                                        </span>
                                        {c.sorti_le && (
                                            <span className="mt-1 block text-xs text-slate-600">{t('personnel.parti_le', 'parti le')} {c.sorti_le}</span>
                                        )}
                                        {! c.confirme && c.actif && (
                                            <span className="mt-1 block text-xs text-slate-600">{t('personnel.mdp_pas_choisi', 'mot de passe pas encore choisi')}</span>
                                        )}
                                        {c.empechements.length > 0 && (
                                            <span className="mt-1 block max-w-[18rem] truncate text-xs text-status-incident" title={c.empechements.join(' · ')}>
                                                {c.empechements[0]}
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <Link
                                                href={route('staff.reset-link', c.id)}
                                                method="post"
                                                as="button"
                                                preserveScroll
                                                className="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-marine transition hover:bg-surface"
                                            >
                                                {t('personnel.lien_mdp', 'Lien mot de passe')}
                                            </Link>
                                            {! c.soi_meme && (
                                                <Link
                                                    href={route('staff.toggle', c.id)}
                                                    method="patch"
                                                    as="button"
                                                    preserveScroll
                                                    className={`rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                                        c.actif
                                                            ? 'border border-status-incident/40 text-status-incident hover:bg-status-incident/5'
                                                            : 'border border-slate-200 text-marine hover:bg-surface'
                                                    }`}
                                                >
                                                    {c.actif ? t('personnel.desactiver', 'Désactiver') : t('personnel.reactiver', 'Réactiver')}
                                                </Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {comptes.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan="5">
                                        {t('personnel.aucun', 'Aucun compte ne correspond à cette recherche.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal show={creer} onClose={() => setCreer(false)} maxWidth="2xl">
                <Creation roles={roles} permis={permis} statuts={statuts} onFermer={() => setCreer(false)} />
            </Modal>
        </AuthenticatedLayout>
    );
}
