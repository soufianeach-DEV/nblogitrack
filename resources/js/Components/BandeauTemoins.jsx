import { useTraduction } from '@/traduire';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const NOM = 'temoins_vus';
const DUREE = 180 * 24 * 60 * 60;
const EVENEMENT = 'nblogitrack:temoins';

const dejaChoisi = () => document.cookie.split('; ').some((c) => c.startsWith(`${NOM}=`));

export function ouvrirTemoins() {
    window.dispatchEvent(new Event(EVENEMENT));
}

function Categorie({ titre, description, etat, actif }) {
    return (
        <div className="flex items-start justify-between gap-4 rounded-lg bg-white/5 px-4 py-3">
            <div>
                <p className="font-semibold text-white">{titre}</p>
                <p className="mt-0.5 text-xs text-slate-300">{description}</p>
            </div>
            <span
                className={
                    'mt-0.5 shrink-0 rounded-full px-3 py-1 text-xs font-bold ' +
                    (actif ? 'bg-status-delivered/20 text-status-delivered' : 'bg-white/10 text-slate-300')
                }
            >
                {etat}
            </span>
        </div>
    );
}

export default function BandeauTemoins() {
    const t = useTraduction();
    const [visible, setVisible] = useState(() => ! dejaChoisi());
    const [panneau, setPanneau] = useState(false);

    useEffect(() => {
        const rouvrir = () => {
            setVisible(true);
            setPanneau(true);
        };

        window.addEventListener(EVENEMENT, rouvrir);

        return () => window.removeEventListener(EVENEMENT, rouvrir);
    }, []);

    if (! visible) return null;

    const choisir = (valeur) => {
        document.cookie = `${NOM}=${valeur}; max-age=${DUREE}; path=/; samesite=lax`;
        setVisible(false);
        setPanneau(false);
    };

    return (
        <div className="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-marine-deep px-4 py-4 text-sm text-slate-200 shadow-2xl">
            <div className="mx-auto max-w-7xl">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p className="flex-1">
                        {t('temoins.bandeau', 'Ce site ne dépose que des témoins (cookies) indispensables à son fonctionnement et à la sécurité de votre session. Aucun témoin publicitaire, aucune mesure d\'audience.')}{' '}
                        <Link
                            href={route('pages.show', 'politique-cookies')}
                            className="font-semibold text-action underline-offset-2 hover:underline"
                        >
                            {t('temoins.savoir_plus', 'En savoir plus')}
                        </Link>
                    </p>
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setPanneau(! panneau)}
                            className="rounded-lg px-4 py-2.5 font-semibold text-slate-300 transition hover:text-white"
                        >
                            {t('temoins.personnaliser', 'Personnaliser')}
                        </button>
                        <button
                            type="button"
                            onClick={() => choisir('essentiels')}
                            className="rounded-lg border border-white/25 px-4 py-2.5 font-semibold text-white transition hover:bg-white/10"
                        >
                            {t('temoins.sans_accepter', 'Continuer sans accepter')}
                        </button>
                        <button
                            type="button"
                            onClick={() => choisir('tout')}
                            className="rounded-lg bg-action px-5 py-2.5 font-bold text-marine-deep transition hover:bg-action-dark"
                        >
                            {t('temoins.tout_accepter', 'Tout accepter')}
                        </button>
                    </div>
                </div>

                {panneau && (
                    <div className="mt-4 space-y-2 border-t border-white/10 pt-4">
                        <Categorie
                            titre={t('temoins.essentiels', 'Témoins essentiels')}
                            description={t('temoins.essentiels_detail', 'Session, sécurité des formulaires et mémorisation de votre choix. Sans eux, la connexion est impossible.')}
                            etat={t('temoins.toujours_actifs', 'Toujours actifs')}
                            actif
                        />
                        <Categorie
                            titre={t('temoins.audience', 'Mesure d\'audience')}
                            description={t('temoins.audience_detail', 'Aucun outil de statistiques n\'est installé sur ce site.')}
                            etat={t('temoins.non_utilises', 'Non utilisés')}
                        />
                        <Categorie
                            titre={t('temoins.publicite', 'Publicité et réseaux sociaux')}
                            description={t('temoins.publicite_detail', 'Aucun témoin de ce type n\'est déposé, aucune donnée n\'est partagée avec des tiers.')}
                            etat={t('temoins.non_utilises', 'Non utilisés')}
                        />
                        <div className="flex justify-end pt-1">
                            <button
                                type="button"
                                onClick={() => choisir('essentiels')}
                                className="rounded-lg border border-white/25 px-5 py-2.5 font-bold text-white transition hover:bg-white/10"
                            >
                                {t('temoins.enregistrer', 'Enregistrer mes choix')}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
