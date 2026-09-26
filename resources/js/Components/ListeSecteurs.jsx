import ListeRecherche from '@/Components/ListeRecherche';
import { useTraduction } from '@/traduire';

// Les secteurs de la nomenclature NACE, par section, par ordre
// alphabetique et avec recherche. La valeur est le libelle francais,
// rangee telle quelle ; l'etiquette suit la langue.
export default function ListeSecteurs({ id, value, onChange, groupes, className }) {
    const t = useTraduction();
    const options = groupes.flatMap((g) => g.secteurs.map((s) => ({ valeur: s.valeur, libelle: s.libelle, groupe: g.section })));

    return (
        <ListeRecherche
            id={id}
            value={value}
            onChange={onChange}
            options={options}
            placeholder={t('secteur.choisir', 'Choisissez un secteur…')}
            className={className}
        />
    );
}
