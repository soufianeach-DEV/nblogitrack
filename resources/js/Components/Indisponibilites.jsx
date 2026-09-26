import { useTraduction } from '@/traduire';
import { router, useForm } from '@inertiajs/react';

const LIBELLES = {
    CONGE: ['indispo.conge', 'Congé'],
    MALADIE: ['indispo.maladie', 'Maladie'],
    FORMATION: ['indispo.formation', 'Formation'],
    ENTRETIEN: ['indispo.entretien', 'Entretien'],
    REPARATION: ['indispo.reparation', 'Réparation'],
    CONTROLE: ['indispo.controle', 'Contrôle technique'],
    AUTRE: ['indispo.autre', 'Autre'],
};

/**
 * Conges et immobilisations dates a l'avance. La fiche qui l'accueille est
 * deja un formulaire : pas de <form> imbrique, l'ajout part d'un bouton.
 */
export default function Indisponibilites({ liste = [], motifs, routeAjout, peutModifier }) {
    const t = useTraduction();
    const aujourdhui = new Date().toISOString().slice(0, 10);
    const { data, setData, post, processing, errors, reset } = useForm({ du: '', au: '', motif: motifs[0], commentaire: '' });

    const ajouter = () => post(routeAjout, {
        preserveScroll: true,
        onSuccess: () => reset('du', 'au', 'commentaire'),
    });

    const champ = 'mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine';

    return (
        <div className="space-y-2">
            <p className="text-xs uppercase tracking-wide text-slate-600">{t('indispo.titre', 'Indisponibilités à venir')}</p>
            {liste.length === 0 ? (
                <p className="text-sm text-slate-600">{t('indispo.aucune', 'Aucune indisponibilité prévue.')}</p>
            ) : (
                <ul className="space-y-1">
                    {liste.map((i) => (
                        <li key={i.id} className="flex items-center justify-between gap-3 rounded-lg bg-surface px-3 py-1.5 text-sm text-marine">
                            <span>{i.resume}{i.commentaire ? ` — ${i.commentaire}` : ''}</span>
                            {peutModifier && (
                                <button
                                    type="button"
                                    onClick={() => router.delete(route('unavailability.destroy', i.id), { preserveScroll: true })}
                                    className="text-xs font-semibold text-status-incident hover:underline"
                                >
                                    {t('action.supprimer', 'Supprimer')}
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {peutModifier && (
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div>
                        <label htmlFor="indispo-du" className="text-xs text-slate-600">{t('indispo.du', 'Du')}</label>
                        <input id="indispo-du" type="date" min={aujourdhui} value={data.du} onChange={(e) => setData('du', e.target.value)} className={champ} />
                    </div>
                    <div>
                        <label htmlFor="indispo-au" className="text-xs text-slate-600">{t('indispo.au', 'Au')}</label>
                        <input id="indispo-au" type="date" min={data.du || aujourdhui} value={data.au} onChange={(e) => setData('au', e.target.value)} className={champ} />
                    </div>
                    <div>
                        <label htmlFor="indispo-motif" className="text-xs text-slate-600">{t('indispo.motif', 'Motif')}</label>
                        <select id="indispo-motif" value={data.motif} onChange={(e) => setData('motif', e.target.value)} className={champ}>
                            {motifs.map((m) => <option key={m} value={m}>{t(...LIBELLES[m])}</option>)}
                        </select>
                    </div>
                    <div className="flex items-end">
                        <button
                            type="button"
                            onClick={ajouter}
                            disabled={processing || ! data.du || ! data.au}
                            className="w-full rounded-lg border border-marine px-3 py-2 text-sm font-semibold text-marine transition hover:bg-surface disabled:opacity-50"
                        >
                            {t('indispo.ajouter', 'Ajouter')}
                        </button>
                    </div>
                    {(errors.du || errors.au || errors.motif) && (
                        <p className="col-span-2 text-xs text-status-incident sm:col-span-4">{errors.du || errors.au || errors.motif}</p>
                    )}
                </div>
            )}
        </div>
    );
}
