import { Link, usePage } from '@inertiajs/react';

/**
 * Trois codes de deux lettres, sans drapeau.
 *
 * Un drapeau designe un pays, pas une langue : le neerlandais n'est pas
 * hollandais et l'anglais n'est pas britannique. En Belgique la nuance
 * n'est pas cosmetique.
 */
export default function ChoixLangue({ className = '', sombre = false }) {
    const { langue, langues = {} } = usePage().props;
    const codes = Object.keys(langues);

    if (codes.length < 2) return null;

    return (
        <div className={`flex items-center gap-0.5 ${className}`}>
            {codes.map((code) => (
                <Link
                    key={code}
                    href={route('langue', code)}
                    preserveScroll
                    aria-current={code === langue ? 'true' : undefined}
                    title={langues[code]}
                    className={`rounded px-1.5 py-0.5 text-xs font-semibold uppercase transition ${
                        code === langue
                            ? sombre ? 'bg-white/20 text-white' : 'bg-marine text-white'
                            : sombre ? 'text-white/60 hover:text-white' : 'text-slate-500 hover:text-marine'
                    }`}
                >
                    {code}
                </Link>
            ))}
        </div>
    );
}
