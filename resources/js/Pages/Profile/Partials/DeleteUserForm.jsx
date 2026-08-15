import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useTraduction } from '@/traduire';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm({ className = '' }) {
    const t = useTraduction();
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-6 ${className}`}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    {t('profil.supprimer', 'Supprimer le compte')}
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    {t('profil.supprimer_texte', 'La suppression du compte est définitive. Téléchargez au préalable les documents que vous souhaitez conserver.')}
                </p>
            </header>

            <DangerButton onClick={confirmUserDeletion}>
                {t('profil.supprimer', 'Supprimer le compte')}
            </DangerButton>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        {t('profil.supprimer_confirme', 'Confirmez-vous la suppression de votre compte ?')}
                    </h2>

                    <p className="mt-1 text-sm text-gray-600">
                        {t('profil.supprimer_mdp', 'Cette action est définitive. Saisissez votre mot de passe pour confirmer.')}
                    </p>

                    <div className="mt-6">
                        <InputLabel
                            htmlFor="password"
                            value={t('compte.mot_de_passe', 'Mot de passe')}
                            className="sr-only"
                        />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="mt-1 block w-3/4"
                            isFocused
                            placeholder={t('compte.mot_de_passe', 'Mot de passe')}
                        />

                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeModal}>
                            {t('action.annuler', 'Annuler')}
                        </SecondaryButton>

                        <DangerButton className="ms-3" disabled={processing}>
                            {t('profil.supprimer', 'Supprimer le compte')}
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
