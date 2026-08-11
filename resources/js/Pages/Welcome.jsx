import Icone from '@/Components/Icone';
import { Head, Link } from '@inertiajs/react';

function Service({ icone, titre, texte, mise }) {
    return (
        <article
            className={
                'rounded-2xl bg-white p-7 transition ' +
                (mise ? 'border-2 border-action shadow-md' : 'border border-slate-200 shadow-sm')
            }
        >
            <span
                className={
                    'flex h-12 w-12 items-center justify-center rounded-xl ' +
                    (mise ? 'bg-action text-marine-deep' : 'bg-brand-blue/10 text-brand-blue')
                }
            >
                <Icone nom={icone} className="h-6 w-6" />
            </span>
            <h3 className="mt-5 text-lg font-bold text-marine">{titre}</h3>
            <p className="mt-2 text-sm leading-relaxed text-slate-600">{texte}</p>
        </article>
    );
}

function Tarif({ icone, titre, texte }) {
    return (
        <li className="flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-4">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue">
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
        <li className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-200">
            <Icone nom={icone} className="h-5 w-5 text-action" />
            {texte}
        </li>
    );
}

function ColonnePied({ titre, liens }) {
    return (
        <div>
            <h3 className="text-sm font-bold text-white">{titre}</h3>
            <ul className="mt-4 space-y-2 text-sm text-slate-300">
                {liens.map((l) => <li key={l}>{l}</li>)}
            </ul>
        </div>
    );
}

export default function Welcome({ auth, canLogin, canRegister }) {
    const lienNav = 'text-[15px] font-bold text-marine transition hover:text-brand-blue';

    return (
        <>
            <Head title="Transport et logistique B2B" />

            <div className="min-h-screen bg-surface">
                {/* Premier ecran : en-tete, heros et bandeau tiennent dans la hauteur de la fenetre. */}
                <div className="flex min-h-screen flex-col">
                <header className="sticky top-0 z-30 border-b border-slate-200 bg-white">
                    <div className="mx-auto flex max-w-7xl items-center gap-8 px-4 py-3 sm:px-6">
                        <Link href={route('accueil')} className="shrink-0">
                            <img src="/images/logo-marine.png" alt="NBLogiTrack" className="h-14 w-auto sm:h-20" />
                        </Link>

                        <nav className="hidden items-center gap-6 md:flex">
                            <a href="#services" className={lienNav}>Services</a>
                            <a href="#tarifs" className={lienNav}>Tarifs</a>
                            <a href="#apropos" className={lienNav}>À propos</a>
                        </nav>

                        <div className="ml-auto flex items-center gap-3">
                            {auth?.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                                >
                                    Mon espace
                                </Link>
                            ) : (
                                <>
                                    {canLogin && (
                                        <Link href={route('login')} className="hidden text-[15px] font-bold text-marine transition hover:text-brand-blue sm:block">
                                            Se connecter
                                        </Link>
                                    )}
                                    {canRegister && (
                                        <Link
                                            href={route('register')}
                                            className="rounded-lg bg-action px-5 py-2.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                                        >
                                            Demander un devis
                                        </Link>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <section className="relative isolate flex flex-1 flex-col overflow-hidden">
                    <img
                        src="/images/login-bg.jpg"
                        alt=""
                        aria-hidden="true"
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                    <div className="absolute inset-0 bg-gradient-to-r from-marine-deep via-marine-deep/85 to-marine-deep/30" />

                    <div className="relative mx-auto flex w-full max-w-7xl flex-1 flex-col justify-center px-4 py-12 sm:px-6">
                        {/* self-start : sans lui, l'element flex s'etire sur toute la largeur. */}
                        <span className="self-start rounded-full bg-white/15 px-4 py-1.5 text-xs font-bold text-white backdrop-blur">
                            Expertise logistique belge
                        </span>

                        <h1 className="mt-5 max-w-2xl text-4xl font-extrabold leading-[1.1] text-white sm:text-5xl lg:text-6xl">
                            Gérez vos transports
                            <span className="block text-action">en toute simplicité.</span>
                        </h1>

                        <p className="mt-5 max-w-xl text-base leading-relaxed text-slate-200 sm:text-lg">
                            Plateforme dédiée aux professionnels belges et européens. Optimisez vos flux,
                            maîtrisez vos coûts et sécurisez vos expéditions B2B en temps réel.
                        </p>

                        <div className="mt-8 flex flex-wrap gap-4">
                            <Link
                                href={canRegister ? route('register') : route('login')}
                                className="rounded-lg bg-marine px-7 py-3.5 text-sm font-bold text-white transition hover:bg-marine-deep"
                            >
                                Démarrer l'aventure →
                            </Link>
                            <Link
                                href={route('tracking.show')}
                                className="rounded-lg bg-white px-7 py-3.5 text-sm font-bold text-marine transition hover:bg-slate-100"
                            >
                                Suivre un envoi
                            </Link>
                        </div>
                    </div>

                    {/* Le bandeau ferme le premier ecran, il reste toujours visible sans defiler. */}
                    <div className="relative bg-marine-deep">
                        <ul className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-12 gap-y-3 px-4 py-5 sm:px-6">
                            <Certification icone="valide" texte="e-CMR certifié" />
                            <Certification icone="coche" texte="Viapass / OBU" />
                            <Certification icone="camion" texte="ADR compliant" />
                        </ul>
                    </div>
                </section>
                </div>

                <section id="services" className="py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6">
                        <div className="mx-auto max-w-2xl text-center">
                            <h2 className="text-3xl font-extrabold text-marine sm:text-4xl">
                                Une solution complète pour votre flotte
                            </h2>
                            <p className="mt-4 text-slate-600">
                                Concentrez-vous sur votre cœur de métier, nous nous occupons de
                                l'intelligence logistique.
                            </p>
                        </div>

                        <div className="mt-14 grid gap-6 lg:grid-cols-3">
                            <Service
                                icone="planning"
                                titre="Réservation de transport"
                                texte="Interface guidée pour commander vos trajets en quelques clics. Adresses vérifiées, formule adaptée au délai et prix connu avant validation."
                            />
                            <Service
                                mise
                                icone="camion"
                                titre="Suivi en temps réel"
                                texte="Chaque expédition reçoit un numéro de suivi et un code d'accès. Le destinataire consulte l'état de la livraison sans avoir de compte."
                            />
                            <Service
                                icone="journal"
                                titre="Facturation simplifiée"
                                texte="Identifiant Peppol généré automatiquement à l'inscription, pour une facturation électronique conforme dans toute l'Union européenne."
                            />
                        </div>
                    </div>
                </section>

                <section id="tarifs" className="bg-white py-20">
                    <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center">
                        <div>
                            <h2 className="text-3xl font-extrabold text-marine sm:text-4xl">
                                Une tarification transparente
                            </h2>
                            <p className="mt-4 text-slate-600">
                                Pas de coût caché. Le prix se calcule sur la distance routière réelle,
                                le carburant, les péages du pays traversé et le poids transporté.
                            </p>

                            <ul className="mt-8 space-y-3">
                                <Tarif
                                    icone="camion"
                                    titre="Économique — 5 jours et plus"
                                    texte="Groupage sur les axes réguliers, pour les envois non urgents."
                                />
                                <Tarif
                                    icone="horloge"
                                    titre="Standard — 3 jours"
                                    texte="Le meilleur rapport entre délai et coût pour un envoi courant."
                                />
                                <Tarif
                                    icone="rotation"
                                    titre="Express — 48 heures"
                                    texte="Transport dédié, facturé au coût de revient réel plus marge."
                                />
                            </ul>
                        </div>

                        <div className="relative isolate overflow-hidden rounded-2xl">
                            <img src="/images/login-bg.jpg" alt="" aria-hidden="true" className="h-80 w-full object-cover" />
                            <div className="absolute inset-0 bg-gradient-to-t from-marine-deep via-marine-deep/40 to-transparent" />
                            <div className="absolute inset-x-0 bottom-0 p-8">
                                <p className="text-xl font-bold text-white">Prêt à optimiser vos flux ?</p>
                                <p className="mt-1 text-sm text-slate-200">
                                    L'inscription se fait avec votre numéro de TVA. Vos informations sont
                                    reprises des registres officiels européens.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="apropos" className="py-20">
                    <div className="mx-auto max-w-3xl px-4 text-center sm:px-6">
                        <h2 className="text-3xl font-extrabold text-marine sm:text-4xl">
                            L'excellence logistique au service de l'industrie belge
                        </h2>
                        <p className="mt-5 leading-relaxed text-slate-600">
                            NBLogiTrack s'adresse aux entreprises qui expédient régulièrement en Belgique
                            et dans l'Union européenne. Marchandise palettisée, transport dédié ou
                            groupage, matières dangereuses sous certification ADR. Chaque société cliente
                            est vérifiée auprès du registre européen de la TVA avant d'obtenir un accès.
                        </p>

                        {canRegister && (
                            <Link
                                href={route('register')}
                                className="mt-8 inline-block rounded-lg bg-action px-7 py-3.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark"
                            >
                                Inscrire mon entreprise
                            </Link>
                        )}
                    </div>
                </section>

                <footer className="bg-marine-deep">
                    <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6">
                        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <img src="/images/logo-blanc.png" alt="NBLogiTrack" className="h-9 w-auto" />
                                <p className="mt-4 text-sm leading-relaxed text-slate-300">
                                    L'excellence logistique au service de l'industrie belge.
                                    Précision, fiabilité, innovation.
                                </p>
                            </div>

                            <ColonnePied titre="Services" liens={['Transport routier', 'Groupage européen', 'Transport dédié', 'Matières dangereuses']} />
                            <ColonnePied titre="Légal" liens={['Mentions légales', 'Confidentialité (RGPD)', 'Conditions générales']} />

                            <div>
                                <h3 className="text-sm font-bold text-white">Contact</h3>
                                <ul className="mt-4 space-y-2 text-sm text-slate-300">
                                    <li>Avenue du Port 86C, 1000 Bruxelles</li>
                                    <li>+32 (0) 2 456 78 90</li>
                                    <li>info@nblogitrack.be</li>
                                </ul>
                            </div>
                        </div>

                        <div className="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between">
                            <p>© {new Date().getFullYear()} NBLogiTrack. Tous droits réservés.</p>
                            <p className="text-xs uppercase tracking-widest">Belgique — Union européenne</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
