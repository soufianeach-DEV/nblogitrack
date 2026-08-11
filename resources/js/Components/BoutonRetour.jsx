import Icone from '@/Components/Icone';
import { Link } from '@inertiajs/react';

// Un retour est une action, pas une note de bas de page : il porte donc un
// contour et un fond, comme les autres commandes de l'interface. Ecrit une
// seule fois pour que les quatre retours de l'application se ressemblent.
const STYLE = 'inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 '
    + 'text-xs font-semibold text-marine shadow-sm transition hover:border-slate-300 hover:bg-surface '
    + 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marine';

export default function BoutonRetour({ href, onClick, className = '', children }) {
    const contenu = (
        <>
            <Icone nom="retour" className="h-4 w-4" />
            {children}
        </>
    );

    if (href) {
        return (
            <Link href={href} className={`${STYLE} ${className}`}>
                {contenu}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} className={`${STYLE} ${className}`}>
            {contenu}
        </button>
    );
}
