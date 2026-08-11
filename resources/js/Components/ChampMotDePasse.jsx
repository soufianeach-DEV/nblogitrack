import TextInput from '@/Components/TextInput';
import { useState } from 'react';

export default function ChampMotDePasse({ className = '', ...props }) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <TextInput {...props} type={visible ? 'text' : 'password'} className={className + ' pr-10'} />
            <button
                type="button"
                tabIndex={-1}
                onClick={() => setVisible(! visible)}
                aria-label={visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                title={visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 transition hover:text-marine"
            >
                {visible ? (
                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3.28 2.22a.75.75 0 1 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.75-1.75A9.75 9.75 0 0 0 19.5 10S16.5 3.5 10 3.5c-1.61 0-3.02.4-4.22 1.02L3.28 2.22Zm4.6 4.6 1.15 1.15a2.5 2.5 0 0 1 3 3l1.15 1.15a4 4 0 0 0-5.3-5.3Z" />
                        <path d="M10 16.5c1.06 0 2.02-.17 2.89-.45l-1.6-1.6a4 4 0 0 1-4.74-4.74l-2.2-2.2A9.7 9.7 0 0 0 .5 10S3.5 16.5 10 16.5Z" />
                    </svg>
                ) : (
                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 3.5C3.5 3.5.5 10 .5 10S3.5 16.5 10 16.5 19.5 10 19.5 10 16.5 3.5 10 3.5Zm0 11a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Z" />
                        <circle cx="10" cy="10" r="2.25" />
                    </svg>
                )}
            </button>
        </div>
    );
}
