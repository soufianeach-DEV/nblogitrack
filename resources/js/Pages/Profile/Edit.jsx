import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ChauffeurLayout from '@/Layouts/ChauffeurLayout';
import { useTraduction } from '@/traduire';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status, peutSupprimer }) {
    const t = useTraduction();
    // Le chauffeur garde son interface (barre d'onglets) sur son profil.
    const Mise = usePage().props.auth.user.role === 'DRIVER' ? ChauffeurLayout : AuthenticatedLayout;

    return (
        <Mise
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {t('nav.profil', 'Mon profil')}
                </h2>
            }
        >
            <Head title={t('nav.profil', 'Mon profil')} />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    {peutSupprimer && (
                        <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                            <DeleteUserForm className="max-w-xl" />
                        </div>
                    )}
                </div>
            </div>
        </Mise>
    );
}
