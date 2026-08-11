import ChampMotDePasse from '@/Components/ChampMotDePasse';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Connexion" />

            <div className="mb-8 flex gap-8 border-b border-slate-200 text-sm font-semibold">
                <span className="border-b-2 border-action pb-3 text-marine">CONNEXION</span>
                <Link href={route('register')} className="pb-3 text-slate-600 hover:text-marine">
                    INSCRIPTION
                </Link>
            </div>

            <h1 className="text-2xl font-bold text-marine">Bon retour parmi nous</h1>
            <p className="mt-1 text-sm text-slate-600">
                Veuillez entrer vos identifiants pour accéder à votre tableau de bord.
            </p>

            {status && (
                <div className="mt-4 text-sm font-medium text-status-delivered">{status}</div>
            )}

            <form onSubmit={submit} className="mt-6">
                <div>
                    <InputLabel htmlFor="email" value="E-mail professionnel" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        placeholder="nom@entreprise.be"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <div className="flex items-center justify-between">
                        <InputLabel htmlFor="password" value="Mot de passe" />
                        {canResetPassword && (
                            <Link href={route('password.request')} className="text-sm text-brand-blue hover:underline">
                                Oublié ?
                            </Link>
                        )}
                    </div>
                    <ChampMotDePasse
                        id="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <label className="mt-4 flex items-center">
                    <Checkbox
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    <span className="ms-2 text-sm text-slate-600">Se souvenir de moi</span>
                </label>

                <PrimaryButton className="mt-6 w-full" disabled={processing}>
                    Se connecter
                </PrimaryButton>
            </form>

            <div className="mt-8 flex justify-between text-xs text-slate-600">
                <span>© 2024 NBLogiTrack Belgium</span>
                <span>Aide · Confidentialité</span>
            </div>
        </GuestLayout>
    );
}