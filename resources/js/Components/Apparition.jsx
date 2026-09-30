import { useEffect, useRef, useState } from 'react';

/**
 * Bloc qui glisse et apparait quand il entre a l'ecran. Un seul passage :
 * une fois vu, il reste en place. Sans IntersectionObserver ou avec le
 * reglage « reduire les animations », le contenu est affiche d'emblee
 * (voir .apparition dans app.css).
 */
export default function Apparition({ as: Balise = 'div', delai = 0, className = '', style, children, ...reste }) {
    const ref = useRef(null);
    const [vu, setVu] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (! el || typeof IntersectionObserver === 'undefined') {
            setVu(true);

            return undefined;
        }

        const observateur = new IntersectionObserver(([entree]) => {
            if (entree.isIntersecting) {
                setVu(true);
                observateur.disconnect();
            }
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
        observateur.observe(el);

        return () => observateur.disconnect();
    }, []);

    return (
        <Balise
            ref={ref}
            data-vu={vu ? '' : undefined}
            className={'apparition ' + className}
            style={delai ? { ...style, transitionDelay: `${delai}ms` } : style}
            {...reste}
        >
            {children}
        </Balise>
    );
}
