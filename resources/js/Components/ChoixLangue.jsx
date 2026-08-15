import { usePage } from '@inertiajs/react';

/**
 * Trois codes de deux lettres, sans drapeau.
 *
 * Un drapeau designe un pays, pas une langue : le neerlandais n'est pas
 * hollandais et l'anglais n'est pas britannique. En Belgique la nuance
 * n'est pas cosmetique.
 *
 * Le lien est une ancre ordinaire et non un Link d'Inertia : le
 * changement de langue doit recharger la page entiere. Toutes les
 * adresses portent un prefixe de langue que Ziggy lit dans la balise
 * @routes, laquelle vit dans la mise en page Blade. Une navigation
 * Inertia ne la rejoue pas : apres une bascule, route() aurait continue
 * de fabriquer des adresses dans l'ancienne langue et le premier lien
 * clique aurait ramene l'utilisateur d'ou il venait.
 */
export default function ChoixLangue({ className = '', sombre = false }) {
    const { langue, langues = {} } = usePage().props;
    const codes = Object.keys(langues);

    if (codes.length < 2) return null;

    return (
        <div className={`flex items-center gap-0.5 ${className}`}>
            {codes.map((code) => (
                <a
                    key={code}
                    href={'/langue/' + code}
                    aria-current={code === langue ? 'true' : undefined}
                    title={langues[code]}
                    className={`rounded px-1.5 py-0.5 text-xs font-semibold uppercase transition ${
                        code === langue
                            ? sombre ? 'bg-white/20 text-white' : 'bg-marine text-white'
                            : sombre ? 'text-white/60 hover:text-white' : 'text-slate-500 hover:text-marine'
                    }`}
                >
                    {code}
                </a>
            ))}
        </div>
    );
}
