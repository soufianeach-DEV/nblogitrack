import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                marine: { DEFAULT: '#14324F', deep: '#001D36' },
                'brand-blue': '#0B61A1',
                action: { DEFAULT: '#F59E0B', dark: '#D97706' },
                ink: '#1A202C',
                surface: '#F5F7FA',
                // Teintes calibrees pour rester lisibles sur fond clair :
                // au moins 4,5 pour 1 de contraste, seuil WCAG AA du texte courant.
                status: {
                    pending: '#43474D',
                    progress: '#0B61A1',
                    delivered: '#15803D',
                    incident: '#BA1A1A',
                },
            },
        },
    },

    plugins: [forms],
};