import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLocale, useTraduction } from '@/traduire';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

function Tuile({ libelle, valeur, detail }) {
    return (
        <div className="rounded-2xl bg-white p-5 shadow-sm">
            <p className="text-xs uppercase tracking-wide text-slate-600">{libelle}</p>
            <p className="mt-1 text-2xl font-bold text-marine">{valeur}</p>
            {detail && <p className="mt-0.5 text-xs text-slate-500">{detail}</p>}
        </div>
    );
}

// Pages vues par jour : une seule serie, barres fines ancrees a la base,
// valeur au survol ou au clavier. Les chiffres existent aussi en tableau
// (classements ci-dessous et totaux).
function Histogramme({ serie, locale, t }) {
    const [survol, setSurvol] = useState(null);
    const max = Math.max(1, ...serie.map((j) => j.vues));
    const jourCourt = (iso) => new Date(iso + 'T12:00:00').toLocaleDateString(locale, { day: '2-digit', month: '2-digit' });
    const actif = survol !== null ? serie[survol] : null;

    return (
        <div>
            <div className="mb-2 h-5 text-sm text-slate-600" aria-live="polite">
                {actif
                    ? t('audience.infobulle', ':date : :vues pages vues, :entrees arrivées', {
                        date: new Date(actif.jour + 'T12:00:00').toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long' }),
                        vues: actif.vues.toLocaleString(locale),
                        entrees: actif.entrees.toLocaleString(locale),
                    })
                    : t('audience.survoler', 'Survolez une barre pour voir le détail du jour.')}
            </div>
            <div className="flex h-44 items-end gap-[2px] border-b border-slate-200" role="list" aria-label={t('audience.par_jour', 'Pages vues par jour')}>
                {serie.map((j, i) => (
                    <button
                        key={j.jour}
                        type="button"
                        role="listitem"
                        onMouseEnter={() => setSurvol(i)}
                        onMouseLeave={() => setSurvol(null)}
                        onFocus={() => setSurvol(i)}
                        onBlur={() => setSurvol(null)}
                        aria-label={`${jourCourt(j.jour)} : ${j.vues}`}
                        className="group flex h-full flex-1 items-end focus:outline-none"
                    >
                        <span
                            className={'block w-full rounded-t-[4px] transition-colors ' + (survol === i ? 'bg-action' : 'bg-marine group-focus-visible:bg-action')}
                            style={{ height: j.vues === 0 ? '0' : `${Math.max(2, (100 * j.vues) / max)}%` }}
                        />
                    </button>
                ))}
            </div>
            <div className="mt-1 flex justify-between text-xs text-slate-500">
                <span>{jourCourt(serie[0].jour)}</span>
                <span>{t('audience.max', 'Maximum : :n', { n: max.toLocaleString(locale) })}</span>
                <span>{jourCourt(serie[serie.length - 1].jour)}</span>
            </div>
        </div>
    );
}

function Classement({ titre, lignes, total, vide, locale }) {
    return (
        <div className="rounded-2xl bg-white p-5 shadow-sm">
            <h2 className="text-sm font-bold text-marine">{titre}</h2>
            {lignes.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">{vide}</p>
            ) : (
                <table className="mt-3 w-full text-sm">
                    <tbody>
                        {lignes.map((l) => (
                            <tr key={l.libelle} className="border-t border-slate-100">
                                <td className="py-1.5 pr-3">
                                    <span className="block truncate text-slate-700">{l.libelle}</span>
                                    <span className="mt-1 block h-1 rounded-full bg-marine/80" style={{ width: `${Math.max(2, (100 * l.nombre) / Math.max(1, total))}%` }} />
                                </td>
                                <td className="w-16 py-1.5 text-right font-semibold tabular-nums text-marine">{l.nombre.toLocaleString(locale)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}

export default function Index({ jours, periodes, totaux, serie, pages, sources, campagnes }) {
    const t = useTraduction();
    const locale = useLocale();
    const n = (v) => (v ?? 0).toLocaleString(locale);
    const sommeEntrees = sources.reduce((s, l) => s + l.nombre, 0);
    const vide = t('audience.vide', 'Aucune visite mesurée sur cette période.');

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">{t('nav.audience', 'Audience du site')}</h1>}>
            <Head title={t('nav.audience', 'Audience du site')} />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-3xl text-sm text-slate-600">
                    {t('audience.intro', 'Visites du site public par les personnes qui ont accepté la mesure d\'audience dans le bandeau des témoins. Les comptes connectés et les robots ne sont pas comptés ; aucune donnée ne permet d\'identifier un visiteur.')}
                </p>
                <div className="flex gap-1 rounded-lg bg-white p-1 shadow-sm">
                    {periodes.map((p) => (
                        <Link
                            key={p}
                            href={route('audience.index', { jours: p })}
                            preserveScroll
                            className={'rounded-md px-3 py-1.5 text-sm font-semibold transition ' + (p === jours ? 'bg-marine text-white' : 'text-slate-600 hover:bg-surface')}
                        >
                            {t('audience.jours', ':n jours', { n: p })}
                        </Link>
                    ))}
                </div>
            </div>

            <div className="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <Tuile libelle={t('audience.vues', 'Pages vues')} valeur={n(totaux.vues)} detail={totaux.mobile !== null ? t('audience.mobile', ':n % sur mobile', { n: totaux.mobile }) : null} />
                <Tuile libelle={t('audience.entrees', 'Arrivées sur le site')} valeur={n(totaux.entrees)} />
                <Tuile libelle={t('audience.devis', 'Demandes de devis')} valeur={n(totaux.devis)} detail={totaux.conversion !== null ? t('audience.conversion', ':n % des arrivées', { n: totaux.conversion.toLocaleString(locale) }) : null} />
                <Tuile libelle={t('audience.inscriptions', 'Inscriptions')} valeur={n(totaux.inscriptions)} />
                <Tuile libelle={t('audience.simulations', 'Simulations de tarif')} valeur={n(totaux.simulations)} />
            </div>

            <div className="mb-5 rounded-2xl bg-white p-5 shadow-sm">
                <h2 className="mb-3 text-sm font-bold text-marine">{t('audience.par_jour', 'Pages vues par jour')}</h2>
                <Histogramme serie={serie} locale={locale} t={t} />
            </div>

            <div className="grid gap-5 lg:grid-cols-3">
                <Classement titre={t('audience.pages', 'Pages les plus vues')} lignes={pages} total={pages[0]?.nombre ?? 1} vide={vide} locale={locale} />
                <Classement titre={t('audience.sources', 'Provenance des arrivées')} lignes={sources} total={sommeEntrees} vide={vide} locale={locale} />
                <Classement
                    titre={t('audience.campagnes', 'Campagnes (utm_campaign)')}
                    lignes={campagnes}
                    total={campagnes[0]?.nombre ?? 1}
                    vide={t('audience.campagnes_vide', 'Aucune campagne : ajoutez ?utm_source=linkedin&utm_campaign=nom aux liens que vous partagez.')}
                    locale={locale}
                />
            </div>
        </AuthenticatedLayout>
    );
}
