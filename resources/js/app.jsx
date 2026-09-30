import '../css/app.css';
import './bootstrap';
import './calendrier';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Les routes de Ziggy sont ecrites dans la page HTML au premier chargement,
// avec la langue de ce moment-la. Une navigation Inertia ne recharge pas
// cette page : apres une connexion depuis /fr/login sur un compte en
// neerlandais, tous les liens seraient restes en /fr. La langue de chaque
// page recue devient donc celle des routes, avant que la page soit rendue.
function suivreLangue(langue) {
    if (!langue || typeof Ziggy === 'undefined') {
        return;
    }

    if (Ziggy.defaults?.langue !== langue) {
        Ziggy.defaults = { ...Ziggy.defaults, langue };
    }

    if (document.documentElement.lang !== langue) {
        document.documentElement.lang = langue;
    }
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <App {...props}>
                {({ Component, props: donnees, key }) => {
                    suivreLangue(donnees.langue);

                    return createElement(Component, { key, ...donnees });
                }}
            </App>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
