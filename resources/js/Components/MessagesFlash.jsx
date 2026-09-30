import { usePage } from '@inertiajs/react';

/**
 * Les messages que le serveur renvoie apres une action (succes ou erreur).
 * Affiches par chaque mise en page : une confirmation ne se perd plus sur
 * un ecran qui aurait oublie de la montrer.
 */
export default function MessagesFlash({ className = 'mb-4' }) {
    const flash = usePage().props.flash ?? {};

    if (! flash.success && ! flash.error) {
        return null;
    }

    return (
        <div className={`space-y-2 ${className}`} role="status" aria-live="polite">
            {flash.success && (
                <div className="rounded-lg bg-status-delivered/10 px-4 py-3 text-sm font-medium text-status-delivered">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div role="alert" className="rounded-lg bg-status-incident/10 px-4 py-3 text-sm font-medium text-status-incident">
                    {flash.error}
                </div>
            )}
        </div>
    );
}
