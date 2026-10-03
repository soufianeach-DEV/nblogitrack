import ListeRecherche from '@/Components/ListeRecherche';
import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

function Carte({ intitule, valeur, detail, alerte = false }) {
    return (
        <div className="rounded-2xl bg-white p-5 shadow-sm">
            <p className="text-xs uppercase tracking-wide text-slate-600">{intitule}</p>
            <p className={`text-2xl font-bold ${alerte ? 'text-status-incident' : 'text-marine'}`}>{valeur}</p>
            {detail && <p className="mt-0.5 text-xs text-slate-600">{detail}</p>}
        </div>
    );
}

export default function Index({ cles, journal, filtres, permissions, entreprises, statistiques, demandes = [] }) {
    const t = useTraduction();
    const locale = useLocale();
    const flash = usePage().props.flash ?? {};
    const [creation, setCreation] = useState(false);
    const [aRevoquer, setARevoquer] = useState(null);
    const [copie, setCopie] = useState(false);
    const [recherche, setRecherche] = useState('');
    const [entrepriseCle, setEntrepriseCle] = useState('');
    const [etatCle, setEtatCle] = useState('');

    const nouvelleCle = flash.cle_en_clair ?? null;

    // Demande d'une entreprise : accordee avec un formulaire prerempli, ou
    // refusee avec un motif. L'administrateur ne voit jamais la cle accordee.
    const [aAccorder, setAAccorder] = useState(null);
    const [aRefuser, setARefuser] = useState(null);
    const accord = useForm({ nom: '', permissions: [], ips: '', expire_le: '' });
    const refus = useForm({ motif: '' });

    const ouvrirAccord = (d) => {
        accord.clearErrors();
        accord.setData({ nom: t('api.nom_demande', 'Intégration :entreprise', { entreprise: d.entreprise }), permissions: d.permissions, ips: d.ips.join(', '), expire_le: '' });
        setAAccorder(d);
    };

    const accorder = (e) => {
        e.preventDefault();
        accord.post(route('api-keys.grant', aAccorder.id), { preserveScroll: true, onSuccess: () => setAAccorder(null) });
    };

    const refuser = (e) => {
        e.preventDefault();
        refus.patch(route('api-keys.refuse', aRefuser.id), { preserveScroll: true, onSuccess: () => { setARefuser(null); refus.reset(); } });
    };

    const { data, setData, post, processing, errors, reset } = useForm({
        nom: '',
        client_id: '',
        permissions: ['lecture'],
        ips: '',
        expire_le: '',
    });

    const [libelleManuel, setLibelleManuel] = useState(false);
    const entrepriseChoisie = entreprises.find((e) => String(e.valeur) === String(data.client_id)) ?? null;

    // Dans un an, au format du champ date.
    const dansUnAn = () => {
        const d = new Date();
        d.setFullYear(d.getFullYear() + 1);
        return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
    };

    // Choisir l'entreprise remplit le reste : son nom comme libelle (tant
    // qu'on ne l'a pas change a la main), lecture et ecriture pour un
    // partenaire, lecture seule pour une cle interne, expiration a un an.
    const choisirEntreprise = (valeur) => {
        const entreprise = entreprises.find((e) => String(e.valeur) === String(valeur)) ?? null;
        setData((d) => ({
            ...d,
            client_id: valeur,
            nom: libelleManuel ? d.nom : (entreprise?.libelle ?? ''),
            permissions: entreprise ? ['lecture', 'ecriture'] : ['lecture'],
            expire_le: entreprise && ! d.expire_le ? dansUnAn() : d.expire_le,
        }));
    };

    const soumettre = (e) => {
        e.preventDefault();
        post(route('api-keys.store'), {
            preserveScroll: true,
            onSuccess: () => { setCreation(false); setLibelleManuel(false); reset(); },
        });
    };

    const basculerPermission = (cle) => {
        setData('permissions', data.permissions.includes(cle)
            ? data.permissions.filter((p) => p !== cle)
            : [...data.permissions, cle]);
    };

    // Retrouver une cle a revoquer : par son nom, son prefixe (nblt_...),
    // son entreprise ou son auteur, sans recharger la page.
    const normaliser = (texte) => (texte ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const etatDe = (c) => (c.active ? 'actives' : (c.revoquee_le ? 'revoquees' : 'expirees'));
    const suggestions = useMemo(() => [...new Set(cles.flatMap((c) => [c.nom, c.prefixe, c.entreprise].filter(Boolean)))], [cles]);
    const clesAffichees = useMemo(() => {
        const mots = normaliser(recherche.trim()).split(/\s+/).filter(Boolean);
        return cles.filter((c) => {
            const texte = normaliser([c.nom, c.prefixe, c.entreprise, c.creee_par].join(' '));
            return mots.every((m) => texte.includes(m))
                && (entrepriseCle === '' || (entrepriseCle === 'interne' ? c.entreprise === null : c.entreprise === entrepriseCle))
                && (etatCle === '' || etatDe(c) === etatCle);
        });
    }, [cles, recherche, entrepriseCle, etatCle]);
    const entreprisesDesCles = useMemo(() => [...new Set(cles.map((c) => c.entreprise).filter(Boolean))].sort(), [cles]);
    const filtreActif = recherche !== '' || entrepriseCle !== '' || etatCle !== '';

    const filtrer = (champ, valeur) => {
        router.get(route('api-keys.index'), { ...filtres, [champ]: valeur || undefined, page: undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Recherche du journal : la saisie s'affiche tout de suite, la requete
    // part quand on s'arrete de taper.
    const [rechercheJournal, setRechercheJournal] = useState(filtres.q ?? '');
    const minuteur = useRef(null);
    const chercherJournal = (valeur) => {
        setRechercheJournal(valeur);
        clearTimeout(minuteur.current);
        minuteur.current = setTimeout(() => filtrer('q', valeur.trim()), 300);
    };
    const entreprisesJournal = useMemo(() => {
        const vues = new Map();
        cles.forEach((c) => { if (c.entreprise_id) vues.set(String(c.entreprise_id), c.entreprise); });
        return [...vues].map(([valeur, libelle]) => ({ valeur, libelle }));
    }, [cles]);

    const codeStatut = (statut) => statut < 300
        ? 'bg-status-delivered/10 text-status-delivered'
        : statut < 500 ? 'bg-status-incident/10 text-status-incident' : 'bg-slate-200 text-slate-700';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-marine">{t('api.titre', 'API REST')}</h1>
                        <p className="text-sm text-slate-600">
                            {t('api.sous_titre', 'Les clés d\'accès des partenaires et leur activité.')}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {/* Swagger UI, dans un nouvel onglet : on y essaie la cle generee ici. */}
                        <a
                            href="/api/docs"
                            target="_blank"
                            rel="noopener"
                            className="rounded-lg border border-marine px-4 py-2 text-sm font-bold text-marine transition hover:bg-marine hover:text-white"
                        >
                            {t('api.documentation', 'Documentation de l\'API')}
                        </a>
                        <button
                            type="button"
                            onClick={() => setCreation(true)}
                            className="rounded-lg bg-marine px-4 py-2 text-sm font-bold text-white transition hover:bg-marine-deep"
                        >
                            {t('api.nouvelle', 'Générer une clé')}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={t('api.titre', 'API REST')} />


            {}
            {nouvelleCle && (
                <div className="mb-4 rounded-2xl border-2 border-action bg-action/5 p-5">
                    <p className="font-bold text-marine">
                        {t('api.creee', 'Clé « :nom » créée', { nom: nouvelleCle.nom })}
                    </p>
                    <p className="mt-1 text-sm text-slate-700">
                        {t('api.copiez', 'Copiez-la maintenant : elle ne sera plus jamais affichée. Seule son empreinte est conservée.')}
                    </p>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <code className="flex-1 overflow-x-auto rounded-lg bg-marine px-4 py-3 font-mono text-sm text-white">
                            {nouvelleCle.valeur}
                        </code>
                        <button
                            type="button"
                            onClick={() => {
                                navigator.clipboard?.writeText(nouvelleCle.valeur);
                                setCopie(true);
                            }}
                            className="rounded-lg bg-action px-4 py-3 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                        >
                            {copie ? t('api.copiee', 'Copiée') : t('api.copier', 'Copier')}
                        </button>
                    </div>
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Carte
                    intitule={t('api.cles_actives', 'Clés actives')}
                    valeur={statistiques.cles_actives}
                    detail={t('api.sur_total', 'sur :total au total', { total: statistiques.cles_total })}
                />
                <Carte
                    intitule={t('api.appels_24h', 'Appels sur 24 h')}
                    valeur={statistiques.appels_24h.toLocaleString(locale)}
                    detail={t('api.sur_7j', ':n sur 7 jours', { n: statistiques.appels_7j.toLocaleString(locale) })}
                />
                <Carte
                    intitule={t('api.refuses_24h', 'Refusés sur 24 h')}
                    valeur={statistiques.refus_24h.toLocaleString(locale)}
                    detail={t('api.sur_7j', ':n sur 7 jours', { n: statistiques.refus_7j.toLocaleString(locale) })}
                    alerte={statistiques.refus_24h > 0}
                />
                <Carte
                    intitule={t('api.duree_moyenne', 'Temps de réponse moyen')}
                    valeur={`${statistiques.duree_moyenne} ms`}
                    detail={t('api.appels_servis', 'appels servis, 7 jours')}
                />
            </div>

            {statistiques.motifs.length > 0 && (
                <div className="mt-4 rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        {t('api.motifs_refus', 'Motifs de refus, 7 derniers jours')}
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        {statistiques.motifs.map((m) => (
                            <span key={m.motif} className="rounded-full bg-status-incident/10 px-3 py-1 text-xs font-medium text-status-incident">
                                {t('api_motif.' + m.motif, m.libelle)} · {m.total}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {demandes.length > 0 && (
                <div className="mt-6 overflow-hidden rounded-2xl border-2 border-action bg-white shadow-sm">
                    <h2 className="border-b border-slate-100 px-5 py-4 font-semibold text-marine">
                        {t('api.demandes_attente', 'Demandes en attente')}
                        <span className="ml-2 rounded-full bg-action px-2 py-0.5 text-xs font-bold text-marine-deep">{demandes.length}</span>
                    </h2>
                    <ul className="divide-y divide-slate-100">
                        {demandes.map((d) => (
                            <li key={d.id} className="flex flex-col gap-3 p-5 text-sm sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0">
                                    <p className="font-semibold text-marine">{d.entreprise}</p>
                                    <p className="text-slate-600">{d.demandeur}{d.email && ` · ${d.email}`} · {d.demandee_le}</p>
                                    <p className="mt-1 text-slate-700">
                                        {d.permissions.map((p) => t('api_permission.' + p, permissions[p] ?? p)).join(', ')}
                                        {d.ips.length > 0 && <span className="font-mono text-xs text-slate-600"> · {d.ips.join(', ')}</span>}
                                    </p>
                                    {d.message && <p className="mt-1 text-slate-600">« {d.message} »</p>}
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => { refus.clearErrors(); setARefuser(d); }}
                                        className="rounded-lg border border-status-incident/40 px-4 py-2 text-sm font-semibold text-status-incident transition hover:bg-status-incident/5"
                                    >
                                        {t('api.refuser', 'Refuser')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => ouvrirAccord(d)}
                                        className="rounded-lg bg-marine px-4 py-2 text-sm font-bold text-white transition hover:bg-marine-deep"
                                    >
                                        {t('api.accorder', 'Accorder')}
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <h2 className="font-semibold text-marine">
                        {t('api.les_cles', 'Les clés')}
                        {filtreActif && (
                            <span className="ml-2 text-xs font-normal text-slate-600">
                                {t('api.n_sur_total', ':n sur :total', { n: clesAffichees.length, total: cles.length })}
                            </span>
                        )}
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        <input
                            type="search"
                            list="suggestions-cles"
                            value={recherche}
                            onChange={(e) => setRecherche(e.target.value)}
                            placeholder={t('api.rechercher_cle', 'Nom, préfixe nblt_… ou entreprise')}
                            aria-label={t('api.rechercher_cle', 'Nom, préfixe nblt_… ou entreprise')}
                            className="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                        <datalist id="suggestions-cles">
                            {suggestions.map((v) => <option key={v} value={v} />)}
                        </datalist>
                        <ListeRecherche
                            value={entrepriseCle}
                            onChange={setEntrepriseCle}
                            vide={t('api.toutes_entreprises', 'Toutes les entreprises')}
                            aria-label={t('api.entreprise', 'Entreprise')}
                            options={[
                                { valeur: 'interne', libelle: t('api.interne_court', 'Interne') },
                                ...entreprisesDesCles.map((e) => ({ valeur: e, libelle: e })),
                            ]}
                            className="w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                        <ListeRecherche
                            value={etatCle}
                            onChange={setEtatCle}
                            vide={t('api.tous_etats', 'Tous les états')}
                            aria-label={t('api.etat', 'État')}
                            trier={false}
                            options={[
                                { valeur: 'actives', libelle: t('api.actives', 'Actives') },
                                { valeur: 'revoquees', libelle: t('api.revoquees', 'Révoquées') },
                                { valeur: 'expirees', libelle: t('api.expirees', 'Expirées') },
                            ]}
                            className="w-44 rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                    </div>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.nom', 'Nom')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.prefixe', 'Préfixe')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.entreprise', 'Entreprise')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.permissions', 'Permissions')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.restriction_ip', 'Adresses autorisées')}</th>
                                <th scope="col" className="px-4 py-3 text-right font-semibold">{t('api.appels', 'Appels')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.etat', 'État')}</th>
                                <th scope="col" className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {clesAffichees.map((c) => (
                                <tr key={c.id} className="border-b border-slate-50 last:border-0">
                                    <td className="px-4 py-3 font-semibold text-marine">
                                        {c.nom}
                                        <span className="block text-xs font-normal text-slate-600">
                                            {t('api.creee_par', 'par :qui le :date', { qui: c.creee_par, date: c.creee_le })}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-brand-blue">{c.prefixe}</td>
                                    <td className="px-4 py-3 text-slate-700">
                                        {c.entreprise ?? <span className="text-slate-500">{t('api.interne', 'Interne — accès complet')}</span>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="flex flex-wrap gap-1">
                                            {c.permissions.map((p) => (
                                                <span key={p} className="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                                    {t('api_permission.' + p, permissions[p] ?? p)}
                                                </span>
                                            ))}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-600">
                                        {c.ips.length > 0
                                            ? c.ips.join(', ')
                                            : <span className="font-sans text-slate-500">{t('api.sans_restriction', 'Aucune')}</span>}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        <span className="font-bold text-marine">{c.appels.toLocaleString(locale)}</span>
                                        <span className="block text-xs text-slate-600">
                                            {c.dernier_usage ?? t('api.jamais', 'jamais utilisée')}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        {c.active ? (
                                            <span className="rounded-full bg-status-delivered/10 px-3 py-1 text-xs font-semibold text-status-delivered">
                                                {t('api.active', 'Active')}
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                                {c.revoquee_le
                                                    ? t('api.revoquee_le', 'Révoquée le :date', { date: c.revoquee_le })
                                                    : t('api.expiree_le', 'Expirée le :date', { date: c.expire_le })}
                                            </span>
                                        )}
                                        {c.active && c.expire_le && (
                                            <span className="block text-xs text-slate-600">
                                                {t('api.jusquau', 'jusqu\'au :date', { date: c.expire_le })}
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        {c.active && (
                                            <button
                                                type="button"
                                                onClick={() => setARevoquer(c)}
                                                className="text-xs font-semibold text-status-incident hover:underline"
                                            >
                                                {t('api.revoquer', 'Révoquer')}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {cles.length > 0 && clesAffichees.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan={8}>
                                        {t('api.aucune_cle_trouvee', 'Aucune clé ne correspond à la recherche.')}
                                    </td>
                                </tr>
                            )}
                            {cles.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan={8}>
                                        {t('api.aucune_cle', 'Aucune clé n\'a encore été générée.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <h2 className="font-semibold text-marine">{t('api.journal', 'Journal d\'accès')}</h2>
                    <div className="flex flex-wrap gap-2">
                        <input
                            type="search"
                            list="suggestions-cles"
                            value={rechercheJournal}
                            onChange={(e) => chercherJournal(e.target.value)}
                            placeholder={t('api.rechercher_journal', 'Clé, préfixe nblt_…, entreprise ou IP')}
                            aria-label={t('api.rechercher_journal', 'Clé, préfixe nblt_…, entreprise ou IP')}
                            className="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                        <ListeRecherche
                            value={filtres.entreprise ?? ''}
                            onChange={(valeur) => filtrer('entreprise', valeur)}
                            vide={t('api.toutes_entreprises', 'Toutes les entreprises')}
                            aria-label={t('api.entreprise_journal', 'Entreprise du journal')}
                            options={[{ valeur: 'interne', libelle: t('api.interne_court', 'Interne') }, ...entreprisesJournal]}
                            className="w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                        <ListeRecherche
                            value={filtres.etat ?? ''}
                            onChange={(valeur) => filtrer('etat', valeur)}
                            vide={t('api.tout', 'Tout')}
                            aria-label={t('api.etat_journal', 'État des appels')}
                            trier={false}
                            options={[
                                { valeur: 'servis', libelle: t('api.servis', 'Servis') },
                                { valeur: 'refuses', libelle: t('api.refuses', 'Refusés') },
                            ]}
                            className="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                    </div>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="px-4 py-3 font-semibold">{t('journal.date', 'Date')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.cle', 'Clé')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.appel', 'Appel')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('api.code', 'Code')}</th>
                                <th scope="col" className="px-4 py-3 font-semibold">{t('journal.ip', 'Adresse IP')}</th>
                                <th scope="col" className="px-4 py-3 text-right font-semibold">{t('api.duree', 'Durée')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {journal.data.map((l) => (
                                <tr key={l.id} className="border-b border-slate-50 last:border-0">
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{l.horodatage}</td>
                                    <td className="px-4 py-3">
                                        {l.cle
                                            ? <><span className="text-marine">{l.cle}</span>
                                                <span className="block font-mono text-xs text-slate-500">{l.prefixe}</span></>
                                            : <span className="text-slate-500">—</span>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="font-mono text-xs font-bold text-slate-700">{l.methode}</span>
                                        <span className="ml-2 font-mono text-xs text-slate-600">/{l.chemin}</span>
                                        {l.refus && (
                                            <span className="block text-xs font-medium text-status-incident">
                                                {t('api_motif.' + l.refus, l.refus_libelle)}
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className={`rounded px-2 py-0.5 font-mono text-xs font-bold ${codeStatut(l.statut)}`}>
                                            {l.statut}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600">{l.ip ?? '—'}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">
                                        {l.duree !== null ? `${l.duree} ms` : '—'}
                                    </td>
                                </tr>
                            ))}
                            {journal.data.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan={6}>
                                        {t('api.journal_vide', 'Aucun appel enregistré.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {journal.last_page > 1 && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {journal.links.map((lien, i) => (
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

            <Modal show={creation} onClose={() => setCreation(false)} maxWidth="lg">
                <form onSubmit={soumettre} className="p-6">
                    <h2 className="text-lg font-bold text-marine">{t('api.nouvelle', 'Générer une clé')}</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {t('api.nouvelle_aide', 'La valeur ne s\'affichera qu\'une seule fois, juste après la création.')}
                    </p>

                    <div className="mt-4 space-y-4">
                        <div>
                            <label htmlFor="entreprise" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.entreprise', 'Entreprise')}
                            </label>
                            {/* Les entreprises inscrites et validees : on tape pour chercher, on ne saisit pas un nom. */}
                            <ListeRecherche
                                id="entreprise"
                                value={data.client_id}
                                onChange={choisirEntreprise}
                                options={entreprises}
                                vide={t('api.interne', 'Interne — accès complet')}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            {entrepriseChoisie && (
                                <p className="mt-2 rounded-md bg-surface px-3 py-2 text-xs text-slate-700">
                                    {[entrepriseChoisie.detail, entrepriseChoisie.ville, entrepriseChoisie.contact].filter(Boolean).join(' · ')}
                                </p>
                            )}
                            <p className="mt-1 text-xs text-slate-600">
                                {t('api.entreprise_liste_aide', 'Tapez pour chercher parmi les entreprises inscrites et validées. Une clé rattachée ne voit que les expéditions de cette entreprise.')}
                            </p>
                            {errors.client_id && <p className="mt-1 text-xs text-status-incident">{errors.client_id}</p>}
                        </div>

                        <div>
                            <label htmlFor="nom" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.libelle', 'Libellé')}
                            </label>
                            <input
                                id="nom"
                                value={data.nom}
                                onChange={(e) => { setLibelleManuel(true); setData('nom', e.target.value); }}
                                placeholder={entrepriseChoisie?.libelle ?? t('api.cle_interne', 'Clé interne')}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            <p className="mt-1 text-xs text-slate-600">
                                {t('api.libelle_aide', 'Rempli avec le nom de l\'entreprise. Il sert à reconnaître la clé dans la liste.')}
                            </p>
                            {errors.nom && <p className="mt-1 text-sm text-status-incident">{errors.nom}</p>}
                        </div>

                        <div>
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.permissions', 'Permissions')}
                            </span>
                            <div className="flex flex-wrap gap-4">
                                {Object.entries(permissions).map(([cle, libelle]) => (
                                    <label key={cle} className="flex items-center gap-2 text-sm text-marine">
                                        <input
                                            type="checkbox"
                                            checked={data.permissions.includes(cle)}
                                            onChange={() => basculerPermission(cle)}
                                            className="rounded border-gray-300 text-marine focus:ring-marine"
                                        />
                                        {t('api_permission.' + cle, libelle)}
                                    </label>
                                ))}
                            </div>
                            {errors.permissions && <p className="mt-1 text-sm text-status-incident">{errors.permissions}</p>}
                        </div>

                        <div>
                            <label htmlFor="ips" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.restriction_ip', 'Adresses autorisées')}
                            </label>
                            <input
                                id="ips"
                                value={data.ips}
                                onChange={(e) => setData('ips', e.target.value)}
                                placeholder="203.0.113.7, 198.51.100.24"
                                className="w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            <p className="mt-1 text-xs text-slate-600">
                                {t('api.ip_aide', 'Séparées par des virgules. Laisser vide autorise toutes les adresses.')}
                            </p>
                            {errors.ips && <p className="mt-1 text-sm text-status-incident">{errors.ips}</p>}
                        </div>

                        <div>
                            <label htmlFor="expire" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.expiration', 'Expiration')}
                            </label>
                            <input
                                id="expire"
                                type="date"
                                value={data.expire_le}
                                onChange={(e) => setData('expire_le', e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            <p className="mt-1 text-xs text-slate-600">
                                {t('api.expiration_aide', 'Facultatif. Sans date, la clé reste valable jusqu\'à sa révocation.')}
                            </p>
                            {errors.expire_le && <p className="mt-1 text-sm text-status-incident">{errors.expire_le}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setCreation(false)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button disabled={processing} className="rounded-lg bg-marine px-4 py-2 text-sm font-semibold text-white transition hover:bg-marine-deep disabled:opacity-50">
                            {t('api.generer', 'Générer')}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal show={aRevoquer !== null} onClose={() => setARevoquer(null)} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-bold text-marine">{t('api.revoquer', 'Révoquer')}</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        {t('api.revoquer_aide', 'La clé « :nom » cessera immédiatement de fonctionner. Cette opération ne s\'annule pas : il faudra en générer une autre.', { nom: aRevoquer?.nom ?? '' })}
                    </p>
                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setARevoquer(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                router.patch(route('api-keys.revoke', aRevoquer.id), {}, {
                                    preserveScroll: true,
                                    onFinish: () => setARevoquer(null),
                                });
                            }}
                            className="rounded-lg bg-status-incident px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                        >
                            {t('api.revoquer', 'Révoquer')}
                        </button>
                    </div>
                </div>
            </Modal>
            <Modal show={aAccorder !== null} onClose={() => setAAccorder(null)} maxWidth="lg">
                {aAccorder && (
                    <form onSubmit={accorder} className="p-6">
                        <h2 className="text-lg font-bold text-marine">{t('api.accorder_titre', 'Accorder l\'accès à :entreprise', { entreprise: aAccorder.entreprise })}</h2>
                        <p className="mt-1 text-sm text-slate-600">
                            {t('api.accorder_aide', 'La clé est générée maintenant, mais vous ne la verrez pas : le client l\'affichera une seule fois dans son espace.')}
                        </p>
                        <div className="mt-4 space-y-4">
                            <div>
                                <label htmlFor="accord-nom" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">{t('api.nom', 'Nom')}</label>
                                <input id="accord-nom" value={accord.data.nom} onChange={(e) => accord.setData('nom', e.target.value)} maxLength={80} className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine" />
                                {accord.errors.nom && <p className="mt-1 text-sm text-status-incident">{accord.errors.nom}</p>}
                            </div>
                            <div>
                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">{t('api.permissions', 'Permissions')}</span>
                                <div className="flex flex-wrap gap-4">
                                    {Object.entries(permissions).map(([cle, libelle]) => (
                                        <label key={cle} className="flex items-center gap-2 text-sm text-marine">
                                            <input
                                                type="checkbox"
                                                checked={accord.data.permissions.includes(cle)}
                                                onChange={() => accord.setData('permissions', accord.data.permissions.includes(cle)
                                                    ? accord.data.permissions.filter((p) => p !== cle)
                                                    : [...accord.data.permissions, cle])}
                                                className="rounded border-gray-300 text-marine focus:ring-marine"
                                            />
                                            {t('api_permission.' + cle, libelle)}
                                        </label>
                                    ))}
                                </div>
                                {accord.errors.permissions && <p className="mt-1 text-sm text-status-incident">{accord.errors.permissions}</p>}
                            </div>
                            <div>
                                <label htmlFor="accord-ips" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">{t('api.restriction_ip', 'Adresses autorisées')}</label>
                                <input id="accord-ips" value={accord.data.ips} onChange={(e) => accord.setData('ips', e.target.value)} placeholder="203.0.113.7, 198.51.100.24" className="w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-marine focus:ring-marine" />
                                <p className="mt-1 text-xs text-slate-600">{t('api.ip_aide', 'Séparées par des virgules. Laisser vide autorise toutes les adresses.')}</p>
                                {accord.errors.ips && <p className="mt-1 text-sm text-status-incident">{accord.errors.ips}</p>}
                            </div>
                            <div>
                                <label htmlFor="accord-expire" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">{t('api.expiration', 'Expiration')}</label>
                                <input id="accord-expire" type="date" value={accord.data.expire_le} onChange={(e) => accord.setData('expire_le', e.target.value)} className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine" />
                                <p className="mt-1 text-xs text-slate-600">{t('api.expiration_aide', 'Facultatif. Sans date, la clé reste valable jusqu\'à sa révocation.')}</p>
                                {accord.errors.expire_le && <p className="mt-1 text-sm text-status-incident">{accord.errors.expire_le}</p>}
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-2">
                            <button type="button" onClick={() => setAAccorder(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                                {t('action.annuler', 'Annuler')}
                            </button>
                            <button disabled={accord.processing} className="rounded-lg bg-marine px-4 py-2 text-sm font-semibold text-white transition hover:bg-marine-deep disabled:opacity-50">
                                {t('api.accorder', 'Accorder')}
                            </button>
                        </div>
                    </form>
                )}
            </Modal>

            <Modal show={aRefuser !== null} onClose={() => setARefuser(null)} maxWidth="md">
                {aRefuser && (
                    <form onSubmit={refuser} className="p-6">
                        <h2 className="text-lg font-bold text-marine">{t('api.refuser_titre', 'Refuser la demande de :entreprise', { entreprise: aRefuser.entreprise })}</h2>
                        <label htmlFor="refus-motif" className="mt-4 mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">{t('api.motif_refus', 'Motif, envoyé au client')}</label>
                        <textarea id="refus-motif" rows={3} maxLength={300} value={refus.data.motif} onChange={(e) => refus.setData('motif', e.target.value)} className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine" />
                        {refus.errors.motif && <p className="mt-1 text-sm text-status-incident">{refus.errors.motif}</p>}
                        <div className="mt-6 flex justify-end gap-2">
                            <button type="button" onClick={() => setARefuser(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                                {t('action.annuler', 'Annuler')}
                            </button>
                            <button disabled={refus.processing} className="rounded-lg bg-status-incident px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50">
                                {t('api.refuser', 'Refuser')}
                            </button>
                        </div>
                    </form>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
