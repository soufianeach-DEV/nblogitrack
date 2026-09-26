import { useTraduction } from '@/traduire';

// Les secteurs de la nomenclature NACE, par section. La valeur est le
// libelle francais, rangee telle quelle ; l'etiquette suit la langue.
export default function ListeSecteurs({ id, value, onChange, groupes, className }) {
    const t = useTraduction();
    const connue = groupes.some((g) => g.secteurs.some((s) => s.valeur === value));

    return (
        <select id={id} value={value ?? ''} onChange={(e) => onChange(e.target.value)} className={className}>
            <option value="">{t('secteur.choisir', 'Choisissez un secteur…')}</option>
            {/* Ancienne valeur hors liste : gardee pour ne rien perdre. */}
            {value && ! connue && <option value={value}>{value}</option>}
            {groupes.map((g) => (
                <optgroup key={g.section} label={g.section}>
                    {g.secteurs.map((s) => <option key={s.valeur} value={s.valeur}>{s.libelle}</option>)}
                </optgroup>
            ))}
        </select>
    );
}
