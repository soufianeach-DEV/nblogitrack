import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            first_name: user.first_name,
            last_name: user.last_name,
            email: user.email,
        });

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Informations personnelles
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Modifiez votre nom et l'adresse électronique de votre compte.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
    <InputLabel htmlFor="first_name" value="Prénom" />

    <TextInput
        id="first_name"
        className="mt-1 block w-full"
        value={data.first_name}
        onChange={(e) => setData('first_name', e.target.value)}
        required
        isFocused
        autoComplete="given-name"
    />

    <InputError className="mt-2" message={errors.first_name} />
</div>

<div>
    <InputLabel htmlFor="last_name" value="Nom" />

    <TextInput
        id="last_name"
        className="mt-1 block w-full"
        value={data.last_name}
        onChange={(e) => setData('last_name', e.target.value)}
        required
        autoComplete="family-name"
    />

    <InputError className="mt-2" message={errors.last_name} />
</div>

                <div>
                    <InputLabel htmlFor="email" value="Adresse électronique" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800">
                            Votre adresse électronique n'est pas confirmée.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Renvoyer le lien de confirmation.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                Un nouveau lien de confirmation vient d'être
                                envoyé à votre adresse.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Enregistrer</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">
                            Enregistré.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
