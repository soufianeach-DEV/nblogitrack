import ChoixLangue from '@/Components/ChoixLangue';
import Icone from '@/Components/Icone';
import { useTraduction } from '@/traduire';
import { Head, Link } from '@inertiajs/react';

// Trame de points, posee en fond des sections claires.
const TRAME = {
    backgroundImage: 'radial-gradient(circle, rgb(20 50 79 / 0.07) 1px, transparent 1px)',
    backgroundSize: '22px 22px',
};

function Service({ icone, titre, texte }) {
    return (
        <article className="group rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:border-brand-blue/40 hover:shadow-xl hover:shadow-marine/10">
            <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-blue/10 text-brand-blue transition-all duration-300 group-hover:scale-110 group-hover:bg-brand-blue group-hover:text-white">
                <Icone nom={icone} className="h-6 w-6" />
            </span>
            <h3 className="mt-5 text-lg font-bold text-marine">{titre}</h3>
            <p className="mt-2 text-sm leading-relaxed text-slate-600">{texte}</p>
        </article>
    );
}

function Tarif({ icone, titre, texte }) {
    return (
        <li className="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-all duration-300 hover:translate-x-1.5 hover:border-action hover:shadow-md">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue transition-colors duration-300 group-hover:bg-action group-hover:text-marine-deep">
                <Icone nom={icone} className="h-5 w-5" />
            </span>
            <div>
                <h3 className="font-bold text-marine">{titre}</h3>
                <p className="text-sm text-slate-600">{texte}</p>
            </div>
        </li>
    );
}

function Certification({ icone, texte }) {
    return (
        <li className="group flex cursor-default items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-200 transition-colors duration-300 hover:text-white">
            <Icone nom={icone} className="h-5 w-5 text-action transition-transform duration-300 group-hover:scale-125" />
            {texte}
        </li>
    );
}

function ColonnePied({ titre, liens }) {
    return (
        <div>
            <h3 className="text-sm font-bold text-white">{titre}</h3>
            <ul className="mt-4 space-y-2 text-sm text-slate-300">
                {liens.map((l) => (
                    <li key={l} className="w-fit cursor-default transition-colors duration-200 hover:text-action">
                        {l}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function Welcome({ auth, canLogin, canRegister }) {
    const t = useTraduction();
    // Le trait orange se deploie sous le lien au survol.
    const lienNav = 'group relative text-[15px] font-bold text-marine transition-colors duration-200 hover:text-brand-blue';
    const soulignement = 'absolute -bottom-1.5 left-0 h-0.5 w-0 bg-action transition-all duration-300 group-hover:w-full';
    const boutonAction = 'rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-marine-deep shadow-sm transition-all duration-300 hover:bg-action-dark hover:shadow-lg hover:shadow-action/40 active:scale-95';

    return (
        <>
            <Head title={t('accueil.titre_page', 'Transport et logistique B2B')} />

            <div className="min-h-screen bg-surface">
                {/* Premier ecran : en-tete, heros et bandeau tiennent dans la hauteur de la fenetre. */}
                <div className="flex min-h-screen flex-col">
                <header className="sticky top-0 z-30 border-b border-slate-200 bg-white">
                    <div className="mx-auto flex max-w-7xl items-center gap-8 px-4 py-3 sm:px-6">
                        <Link href={route('accueil')} className="shrink-0 transition-transform duration-300 hover:scale-105">
                            <img src="/images/logo-marine.png" alt="NBLogiTrack" className="h-14 w-auto sm:h-20" />
                        </Link>

                        <nav className="hidden items-center gap-6 md:flex">
                            <a href="#services" className={lienNav}>{t('nav.services', 'Services')}<span className={soulignement} /></a>
                            <a href="#tarifs" className={lienNav}>{t('nav.tarifs', 'Tarifs')}<span className={soulignement} /></a>
                            <a href="#apropos" className={lienNav}>{t('nav.a_propos', 'À propos')}<span className={soulignement} /></a>
                        </nav>

                        <div className="ml-auto flex items-center gap-3">
                            {/* Le visiteur choisit sa langue avant de creer un
                                compte, pas apres. */}
                            <ChoixLangue />

                            {auth?.user ? (
                                <Link href={route('dashboard')} className={boutonAction}>
                                    {t('accueil.mon_espace', 'Mon espace')}
                                </Link>
                            ) : (
                                <>
                                    {canLogin && (
                                        <Link href={route('login')} className={'hidden sm:block ' + lienNav}>
                                            {t('nav.connexion', 'Se connecter')}<span className={soulignement} />
                                        </Link>
                                    )}
                                    <Link href={route('devis.create')} className={boutonAction}>
                                        {t('nav.devis', 'Demander un devis')}
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <section className="relative isolate flex flex-1 flex-col overflow-hidden">
                    {/* Lent rapprochement de l'image : le heros n'est jamais tout a fait fige. */}
                    <img
                        src="/images/login-bg.jpg"
                        alt=""
                        aria-hidden="true"
                        className="absolute inset-0 h-full w-full origin-center scale-100 animate-[zoom_28s_ease-in-out_infinite_alternate] object-cover"
                    />
                    <div className="absolute inset-0 bg-gradient-to-r from-marine-deep via-marine-deep/85 to-marine-deep/30" />
                    <div className="absolute -left-24 top-1/4 h-96 w-96 rounded-full bg-action/10 blur-3xl" />

                    <div className="relative mx-auto flex w-full max-w-7xl flex-1 flex-col justify-center px-4 py-12 sm:px-6">
                        {/* self-start : sans lui, l'element flex s'etire sur toute la largeur. */}
                        <span className="self-start rounded-full bg-white/15 px-4 py-1.5 text-xs font-bold text-white backdrop-blur">
                            {t('accueil.expertise', 'Expertise logistique belge')}
                        </span>

                        <h1 className="mt-5 max-w-2xl text-4xl font-extrabold leading-[1.1] text-white sm:text-5xl lg:text-6xl">
                            {t('accueil.titre', 'Gérez vos transports')}
                            <span className="block text-action">{t('accueil.titre_suite', 'en toute simplicité.')}</span>
                        </h1>

                        <p className="mt-5 max-w-xl text-base leading-relaxed text-slate-200 sm:text-lg">
                            {t('accueil.accroche', 'Plateforme dédiée aux professionnels belges et européens. Optimisez vos flux, maîtrisez vos coûts et sécurisez vos expéditions B2B en temps réel.')}
                        </p>

                        <div className="mt-8 flex flex-wrap gap-4">
                            <Link
                                href={canRegister ? route('register') : route('login')}
                                className="group rounded-lg bg-marine px-7 py-3.5 text-sm font-bold text-white shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:bg-brand-blue hover:shadow-xl hover:shadow-brand-blue/40 active:translate-y-0"
                            >
                                {t('accueil.demarrer', 'Démarrer l\'aventure')}
                                <span className="ml-2 inline-block transition-transform duration-300 group-hover:translate-x-1.5">→</span>
                            </Link>
                            <Link
                                href={route('tracking.show')}
                                className="rounded-lg bg-white px-7 py-3.5 text-sm font-bold text-marine shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl active:translate-y-0"
                            >
                                {t('nav.suivi', 'Suivre un envoi')}
                            </Link>
                        </div>
                    </div>

                    {/* Le bandeau ferme le premier ecran, il reste toujours visible sans defiler. */}
                    <div className="relative bg-marine-deep">
                        <ul className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-12 gap-y-3 px-4 py-5 sm:px-6">
                            <Certification icone="valide" texte={t('accueil.certif_cmr', 'e-CMR certifié')} />
                            <Certification icone="coche" texte="Viapass / OBU" />
                            <Certification icone="camion" texte="ADR compliant" />
                        </ul>
                    </div>
                </section>
                </div>

                <section id="services" className="relative isolate overflow-hidden py-20" style={TRAME}>
                    {/* Halos colores, tres dilues : ils donnent du relief sans distraire. */}
                    <div className="absolute -right-32 top-0 -z-10 h-[28rem] w-[28rem] rounded-full bg-brand-blue/10 blur-3xl" />
                    <div className="absolute -left-40 bottom-0 -z-10 h-96 w-96 rounded-full bg-action/10 blur-3xl" />

                    <div className="mx-auto max-w-7xl px-4 sm:px-6">
                        <div className="mx-auto max-w-2xl text-center">
                            <h2 className="text-3xl font-extrabold text-marine sm:text-4xl">
                                {t('accueil.services_titre', 'Une solution complète pour votre flotte')}
                            </h2>
                            <p className="mt-4 text-slate-600">
                                {t('accueil.services_texte', 'Concentrez-vous sur votre cœur de métier, nous nous occupons de l\'intelligence logistique.')}
                            </p>
                        </div>

                        <div className="mt-14 grid gap-6 lg:grid-cols-3">
                            <Service
                                icone="planning"
                                titre={t('accueil.service_reservation', 'Réservation de transport')}
                                texte={t('accueil.service_reservation_texte', 'Interface guidée pour commander vos trajets en quelques clics. Adresses vérifiées, formule adaptée au délai et prix connu avant validation.')}
                            />
                            <Service
                                icone="camion"
                                titre={t('accueil.service_suivi', 'Suivi en temps réel')}
                                texte={t('accueil.service_suivi_texte', 'Chaque expédition reçoit un numéro de suivi et un code d\'accès. Le destinataire consulte l\'état de la livraison sans avoir de compte.')}
                            />
                            <Service
                                icone="journal"
                                titre={t('accueil.service_facturation', 'Facturation simplifiée')}
                                texte={t('accueil.service_facturation_texte', 'Identifiant Peppol généré automatiquement à l\'inscription, pour une facturation électronique conforme dans toute l\'Union européenne.')}
                            />
                        </div>
                    </div>
                </section>

                <section id="tarifs" className="bg-white py-20">
                    <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center">
                        <div>
                            <h2 className="text-3xl font-extrabold text-marine sm:text-4xl">
                                {t('accueil.tarifs_titre', 'Une tarification transparente')}
                            </h2>
                            <p className="mt-4 text-slate-600">
                                {t('accueil.tarifs_texte', 'Pas de coût caché. Le prix se calcule sur la distance routière réelle, le carburant, les péages du pays traversé et le poids transporté.')}
                            </p>

                            <ul className="mt-8 space-y-3">
                                <Tarif
                                    icone="camion"
                                    titre={t('accueil.tarif_economique', 'Économique — 5 jours et plus')}
                                    texte={t('accueil.tarif_economique_texte', 'Groupage sur les axes réguliers, pour les envois non urgents.')}
                                />
                                <Tarif
                                    icone="horloge"
                                    titre={t('accueil.tarif_standard', 'Standard — 3 jours')}
                                    texte={t('accueil.tarif_standard_texte', 'Le meilleur rapport entre délai et coût pour un envoi courant.')}
                                />
                                <Tarif
                                    icone="rotation"
                                    titre={t('accueil.tarif_express', 'Express — 48 heures')}
                                    texte={t('accueil.tarif_express_texte', 'Transport dédié, facturé au coût de revient réel plus marge.')}
                                />
                            </ul>

                            <Link
                                href={route('tarifs.index')}
                                className="mt-8 inline-flex items-center gap-2 rounded-lg bg-action px-7 py-3.5 text-base font-bold text-marine-deep transition hover:bg-action-dark"
                            >
                                {t('accueil.calculer', 'Calculer mon tarif')}
                            </Link>
                        </div>

                        <div className="group relative isolate overflow-hidden rounded-2xl shadow-lg transition-shadow duration-500 hover:shadow-2xl">
                            <img
                                src="/images/login-bg.jpg"
                                alt=""
                                aria-hidden="true"
                                className="h-80 w-full object-cover transition-transform duration-700 group-hover:scale-110"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-marine-deep via-marine-deep/40 to-transparent" />
                            <div className="absolute inset-x-0 bottom-0 p-8 transition-transform duration-500 group-hover:-translate-y-1">
                                <p className="text-xl font-bold text-white">{t('accueil.appel_action', 'Prêt à optimiser vos flux ?')}</p>
                                <p className="mt-1 text-sm text-slate-200">
                                    {t('accueil.appel_action_texte', 'L\'inscription se fait avec votre numéro de TVA. Vos informations sont reprises des registres officiels européens.')}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="apropos" className="relative isolate overflow-hidden py-20" style={TRAME}>
                    <div className="absolute left-1/2 top-1/2 -z-10 h-[32rem] w-[32rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-blue/5 blur-3xl" />

                    <div className="mx-auto max-w-3xl px-4 text-center sm:px-6">
                        <h2 className="text-3xl font-extrabold text-marine sm:text-4xl">
                            {t('accueil.apropos_titre', 'L\'excellence logistique au service de l\'industrie belge')}
                        </h2>
                        <p className="mt-5 leading-relaxed text-slate-600">
                            {t('accueil.apropos_texte', 'NBLogiTrack s\'adresse aux entreprises qui expédient régulièrement en Belgique et dans l\'Union européenne. Marchandise palettisée, transport dédié ou groupage, matières dangereuses sous certification ADR. Chaque société cliente est vérifiée auprès du registre européen de la TVA avant d\'obtenir un accès.')}
                        </p>

                        {canRegister && (
                            <Link
                                href={route('register')}
                                className="mt-8 inline-block rounded-lg bg-action px-7 py-3.5 text-sm font-bold text-marine-deep shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:bg-action-dark hover:shadow-xl hover:shadow-action/40 active:translate-y-0"
                            >
                                {t('accueil.inscrire', 'Inscrire mon entreprise')}
                            </Link>
                        )}
                    </div>
                </section>

                <footer className="bg-marine-deep">
                    <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6">
                        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <img src="/images/logo-blanc.png" alt="NBLogiTrack" className="h-16 w-auto transition-transform duration-300 hover:scale-105 sm:h-20" />
                                <p className="mt-4 text-sm leading-relaxed text-slate-300">
                                    {t('accueil.pied_signature', 'L\'excellence logistique au service de l\'industrie belge. Précision, fiabilité, innovation.')}
                                </p>
                            </div>

                            <ColonnePied
                                titre={t('nav.services', 'Services')}
                                liens={[
                                    t('accueil.pied_routier', 'Transport routier'),
                                    t('accueil.pied_groupage', 'Groupage européen'),
                                    t('accueil.pied_dedie', 'Transport dédié'),
                                    t('accueil.pied_adr', 'Matières dangereuses'),
                                ]}
                            />
                            <ColonnePied
                                titre={t('accueil.pied_legal', 'Légal')}
                                liens={[
                                    t('accueil.pied_mentions', 'Mentions légales'),
                                    t('accueil.pied_rgpd', 'Confidentialité (RGPD)'),
                                    t('accueil.pied_conditions', 'Conditions générales'),
                                ]}
                            />

                            <div>
                                <h3 className="text-sm font-bold text-white">{t('nav.contact', 'Contact')}</h3>
                                <ul className="mt-4 space-y-2 text-sm text-slate-300">
                                    <li>Avenue du Port 86C, 1000 Bruxelles</li>
                                    <li>+32 (0) 2 456 78 90</li>
                                    <li>info@nblogitrack.be</li>
                                </ul>
                            </div>
                        </div>

                        <div className="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between">
                            <p>© {new Date().getFullYear()} NBLogiTrack. {t('accueil.droits', 'Tous droits réservés.')}</p>
                            <p className="text-xs uppercase tracking-widest">{t('accueil.pays', 'Belgique — Union européenne')}</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
