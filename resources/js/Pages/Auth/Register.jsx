import AdresseAutocompletion from '@/Components/AdresseAutocompletion';
import ChampMotDePasse from '@/Components/ChampMotDePasse';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { useLangue, useTraduction } from '@/traduire';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const PREFIXE_TVA = { Belgique: 'BE', France: 'FR', 'Pays-Bas': 'NL', Allemagne: 'DE', Luxembourg: 'LU' };

export default function Register({ secteurs, fonctions }) {
    const t = useTraduction();

    const nomRegion = new Intl.DisplayNames([useLangue()], { type: 'region' });
    const { data, setData, post, processing, errors, reset } = useForm({
        company_name: '', vat_number: '', billing_address: '', postal_code: '',
        city: '', country: '', business_sector: '',
        first_name: '', last_name: '', position: '', phone: '',
        email: '', password: '', password_confirmation: '', marque_declaree: false,
    });

    const [vies, setVies] = useState(null);
    const [verification, setVerification] = useState(false);
    const [adresseManuelle, setAdresseManuelle] = useState(false);

    const situationBloquante = vies?.entreprise?.situation?.acceptable === false;

    const submit = (e) => {
        e.preventDefault();
        if (situationBloquante) return;
        post(route('register'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    const verifierTva = async () => {
        const tva = data.vat_number.toUpperCase().replace(/[^0-9A-Z]/g, '');
        if (tva.length < 6) {
            setVies({ statut: 'format', message: t('auth.tva_format', 'Saisis le numéro complet, code pays inclus (ex. BE0123456789).') });
            return;
        }

        setVerification(true);
        try {
            const reponse = await fetch(`/verification-tva?tva=${encodeURIComponent(tva)}`);
            const resultat = await reponse.json();
            setVies(resultat);

            if (resultat.statut === 'valide') {
                setAdresseManuelle(false);
                const d = resultat.entreprise?.dirigeant;

                setData({
                    ...data,
                    vat_number: resultat.tva ?? data.vat_number,
                    company_name: resultat.nom || data.company_name,
                    billing_address: resultat.adresse.rue,
                    postal_code: resultat.adresse.code_postal,
                    city: resultat.adresse.ville,
                    country: nomRegion.of((resultat.tva ?? tva).slice(0, 2)) ?? '',
                    business_sector: resultat.entreprise?.secteur || data.business_sector,
                    first_name: d?.prenom || data.first_name,
                    last_name: d?.nom || data.last_name,
                    position: d?.fonction || data.position,
                });
            }
        } catch {
            setVies({ statut: 'indisponible', message: t('auth.vies_indisponible', 'Le service européen VIES est momentanément injoignable.') });
        } finally {
            setVerification(false);
        }
    };

    const adresseVerifiee = vies?.statut === 'valide' && ! adresseManuelle && data.billing_address;

    const peppol = vies?.statut === 'valide' ? vies.peppol : null;

    const inputCls = 'mt-0.5 block w-full py-1 text-sm';
    const selectCls = 'mt-0.5 block w-full rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-marine focus:ring-marine';
    const titre = 'mb-2 text-xs font-semibold uppercase tracking-wider text-slate-600';

    const etiquette = (nom, libelle, facultatif = false) => (
        <InputLabel htmlFor={nom} className="text-xs">
            {libelle}{facultatif ? '' : <span className="text-status-incident"> *</span>}
        </InputLabel>
    );

    const champ = (nom, libelle, options = {}) => {
        const Composant = options.type === 'password' ? ChampMotDePasse : TextInput;

        return (
            <div className={options.large ? 'sm:col-span-2' : ''}>
                {etiquette(nom, libelle, options.facultatif)}
                <Composant
                    id={nom}
                    type={options.type ?? 'text'}
                    value={data[nom]}
                    placeholder={options.exemple}
                    autoComplete={options.autoComplete}
                    className={inputCls}
                    onChange={(e) => setData(nom, e.target.value)}
                />
                <InputError message={errors[nom]} className="mt-1" />
            </div>
        );
    };

    const liste = (nom, libelle, valeurs, exemple, options = {}) => (
        <div className={options.large ? 'sm:col-span-2' : ''}>
            {etiquette(nom, libelle, true)}
            <input
                id={nom}
                list={nom + '-liste'}
                value={data[nom]}
                placeholder={exemple}
                onChange={(e) => setData(nom, e.target.value)}
                className={selectCls}
            />
            <datalist id={nom + '-liste'}>
                {valeurs.map((v) => <option key={v} value={v} />)}
            </datalist>
            <InputError message={errors[nom]} className="mt-1" />
        </div>
    );

    return (
        <GuestLayout large>
            <Head title={t('auth.inscription', 'Inscription')} />

            <div className="mb-2 flex gap-8 border-b border-slate-200 text-sm font-semibold uppercase">
                <Link href={route('login')} className="pb-2 text-slate-600 hover:text-marine">{t('auth.connexion', 'Connexion')}</Link>
                <span className="border-b-2 border-action pb-2 text-marine">{t('auth.inscription', 'Inscription')}</span>
            </div>

            <h1 className="text-lg font-bold text-marine">{t('auth.inscrire_titre', 'Inscrire votre entreprise')}</h1>
            <p className="text-xs text-slate-600">
                {t('auth.activation', 'Votre compte sera activé après vérification de votre entreprise par nos services.')}
            </p>

            <form onSubmit={submit} className="mt-3">
                <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">
                    <div>
                        <p className={titre}>{t('compte.entreprise', 'Entreprise')}</p>

                        <div>
                            {etiquette('vat_number', t('compte.numero_tva', 'Numéro de TVA'))}
                            <div className="mt-0.5 flex gap-2">
                                <TextInput
                                    id="vat_number"
                                    value={data.vat_number}
                                    placeholder={t('auth.tva_exemple', 'ex. BE0123456789 ou SIRET 34119222700013')}
                                    className="block w-full py-1 text-sm"
                                    onChange={(e) => { setData('vat_number', e.target.value.toUpperCase()); setVies(null); }}
                                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); verifierTva(); } }}
                                />
                                <button
                                    type="button"
                                    onClick={verifierTva}
                                    disabled={verification}
                                    className="shrink-0 rounded-md bg-marine px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-marine-deep disabled:opacity-50"
                                >
                                    {verification ? t('auth.verification', 'Vérification…') : t('auth.verifier', 'Vérifier')}
                                </button>
                            </div>
                            <InputError message={errors.vat_number} className="mt-1" />
                            {vies?.statut === 'valide' && vies.entreprise?.situation?.acceptable === false && (
                                <p className="mt-1 text-xs font-medium text-status-incident">
                                    {t('auth.situation_refus', 'Situation juridique : :libelle. L\'inscription ne peut pas être acceptée.', { libelle: vies.entreprise.situation.libelle })}
                                </p>
                            )}
                            {vies?.statut === 'valide' && vies.entreprise?.situation?.acceptable !== false && (
                                <p className="mt-1 text-xs text-status-delivered">
                                    {t('auth.tva_actif', 'Numéro actif')}
                                    {vies.entreprise?.situation ? ', '.concat(vies.entreprise.situation.libelle.toLowerCase()) : ''}
                                    {t('auth.tva_identifie', ', entreprise identifiée dans le registre européen VIES.')}
                                    {vies.entreprise?.dirigeant && ' ' + t('auth.dirigeant_repris', 'Dirigeant repris du registre national : vérifiez ou remplacez-le.')}
                                </p>
                            )}
                            {vies && vies.statut !== 'valide' && (
                                <p className="mt-1 text-xs text-status-incident">{vies.message}</p>
                            )}
                        </div>

                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            {champ('company_name', t('auth.raison_sociale', 'Raison sociale'), { large: true, exemple: t('auth.raison_sociale_ex', 'ex. Transports Dupont SA') })}
                            {liste('business_sector', t('auth.secteur', 'Secteur d\'activité'), secteurs, t('auth.secteur_ex', 'ex. Construction'), { large: true })}
                        </div>

                        <div className="mt-3">
                            {adresseVerifiee ? (
                                <>
                                    {etiquette('adresse_officielle', t('auth.adresse_siege', 'Adresse du siège'))}
                                    <div className="mt-1 rounded-md border border-status-delivered/40 bg-status-delivered/5 px-3 py-2 text-sm text-marine">
                                        {data.billing_address}<br />
                                        {data.postal_code} {data.city} · {data.country}
                                        <span className="mt-1 block text-xs text-status-delivered">{t('auth.adresse_officielle', 'Adresse officielle du registre VIES')}</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => { setAdresseManuelle(true); setData({ ...data, billing_address: '', postal_code: '', city: '', country: '' }); }}
                                        className="mt-1 text-xs text-brand-blue hover:underline"
                                    >
                                        {t('auth.autre_adresse', 'Saisir une autre adresse')}
                                    </button>
                                </>
                            ) : (
                                <AdresseAutocompletion
                                    label={t('auth.adresse_siege', 'Adresse du siège')}
                                    required
                                    compact
                                    onChange={() => setData({ ...data, billing_address: '', postal_code: '', city: '', country: '' })}
                                    onSelect={({ rue, cp, ville, paysNom }) => {
                                        const prefixe = PREFIXE_TVA[paysNom] ?? '';
                                        const tvaActuelle = data.vat_number.replace(/^[A-Z]{2}/, '');

                                        setData({
                                            ...data,
                                            billing_address: rue,
                                            postal_code: cp,
                                            city: ville,
                                            country: paysNom,
                                            vat_number: data.vat_number ? prefixe + tvaActuelle : prefixe,
                                        });
                                    }}
                                    error={errors.billing_address || errors.postal_code || errors.city || errors.country}
                                />
                            )}
                        </div>

                        <p className="mt-2 text-xs text-slate-600">
                            {t('auth.peppol', 'Identifiant Peppol :')}{' '}
                            {peppol
                                ? <span className="font-mono text-brand-blue">{peppol}</span>
                                : <span>{t('auth.peppol_attente', 'déduit après vérification du numéro')}</span>}
                        </p>
                    </div>

                    <div>
                        <p className={titre}>{t('auth.personne_contact', 'Personne de contact')}</p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {champ('first_name', t('auth.prenom', 'Prénom'), { autoComplete: 'given-name' })}
                            {champ('last_name', t('auth.nom', 'Nom'), { autoComplete: 'family-name' })}
                            {liste('position', t('auth.fonction', 'Fonction'), fonctions, t('auth.fonction_ex', 'ex. Directeur logistique'), { large: true })}
                            {champ('phone', t('auth.telephone', 'Téléphone'), { large: true, exemple: 'ex. +32 2 123 45 67' })}
                        </div>

                        <p className={titre + ' mt-5'}>{t('auth.identifiants_titre', 'Identifiants')}</p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {champ('email', t('compte.email', 'E-mail professionnel'), { large: true, type: 'email', autoComplete: 'username', exemple: 'nom@entreprise.be' })}
                            {champ('password', t('compte.mot_de_passe', 'Mot de passe'), { large: true, type: 'password', autoComplete: 'new-password' })}
                            {champ('password_confirmation', t('auth.confirmer_mdp', 'Confirmer le mot de passe'), { large: true, type: 'password', autoComplete: 'new-password' })}
                        </div>
                    </div>
                </div>

                <label className="mt-4 flex items-start gap-2">
                    <input
                        type="checkbox"
                        checked={data.marque_declaree}
                        onChange={(e) => setData('marque_declaree', e.target.checked)}
                        className="mt-0.5 rounded border-gray-300 text-marine focus:ring-marine"
                    />
                    <span className="text-xs text-slate-600">
                        {t('auth.marque', 'Je certifie que cette dénomination sociale ne porte pas atteinte à une marque déposée.')}
                        <span className="text-status-incident"> *</span>
                    </span>
                </label>
                <InputError message={errors.marque_declaree} className="mt-1" />

                <PrimaryButton className="mt-3 w-full" disabled={processing || situationBloquante}>
                    {t('auth.envoyer_demande', 'Envoyer la demande')}
                </PrimaryButton>
            </form>

        </GuestLayout>
    );
}
