import AdresseAutocompletion from '@/Components/AdresseAutocompletion';
import InputError from '@/Components/InputError';
import VitrineLayout from '@/Layouts/VitrineLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

const CHAMP = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine';

function Bloc({ numero, titre, children }) {
    return (
        <section className="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
            <h2 className="mb-6 flex items-center gap-3 text-lg font-bold text-marine">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-marine text-sm font-bold text-white">
                    {numero}
                </span>
                {titre}
            </h2>
            {children}
        </section>
    );
}

export default function Create({ choix }) {
    const { data, setData, post, processing, errors } = useForm({
        company_name: '', contact_name: '', email: '', phone: '', vat_number: '',
        customer_type: choix.clients[0],
        pickup_address: '', pickup_lat: '', pickup_lng: '',
        delivery_address: '', delivery_lat: '', delivery_lng: '', delivery_country: '',
        pickup_date: '', trip_type: choix.trajets[0],
        frequency: choix.frequences[0], date_flexibility: choix.flexibilites[0],
        goods_type: '', weight: '', volume: '',
        vehicle_type: choix.vehicules[0], insurance_value: choix.assurances[0],
        needs_tail_lift: false, is_hazardous: false, needs_express: false, needs_ecmr: false,
        special_instructions: '',
    });

    const [vies, setVies] = useState(null);
    const [verification, setVerification] = useState(false);

    // Meme service que le formulaire d'inscription : VIES pour la validite et la
    // raison sociale, les registres belge et francais pour le dirigeant.
    const verifierTva = async () => {
        const tva = data.vat_number.toUpperCase().replace(/[^0-9A-Z]/g, '');

        if (tva.length < 6) {
            setVies({ statut: 'format', message: 'Saisissez le numéro complet, code pays inclus (ex. BE0123456789).' });

            return;
        }

        setVerification(true);

        try {
            const reponse = await fetch(`/verification-tva?tva=${encodeURIComponent(tva)}`);
            const resultat = await reponse.json();
            setVies(resultat);

            if (resultat.statut === 'valide') {
                const dirigeant = resultat.entreprise?.dirigeant;

                setData((actuel) => ({
                    ...actuel,
                    vat_number: resultat.tva ?? actuel.vat_number,
                    company_name: resultat.nom || actuel.company_name,
                    contact_name: dirigeant ? `${dirigeant.prenom} ${dirigeant.nom}`.trim() : actuel.contact_name,
                }));
            }
        } catch {
            setVies({ statut: 'indisponible', message: 'Le registre européen est momentanément injoignable.' });
        } finally {
            setVerification(false);
        }
    };

    const envoyer = (e) => {
        e.preventDefault();
        post(route('devis.store'));
    };

    const etiquette = (nom, libelle, obligatoire = false) => (
        <label htmlFor={nom} className="text-xs font-semibold uppercase tracking-wide text-slate-600">
            {libelle}{obligatoire && <span className="text-status-incident"> *</span>}
        </label>
    );

    const champ = (nom, libelle, options = {}) => (
        <div className={options.large ? 'sm:col-span-2' : ''}>
            {etiquette(nom, libelle, options.obligatoire)}
            <input
                id={nom}
                type={options.type ?? 'text'}
                min={options.min}
                value={data[nom]}
                placeholder={options.exemple}
                list={options.suggestions ? nom + '-liste' : undefined}
                onChange={(e) => setData(nom, e.target.value)}
                className={CHAMP}
            />
            {options.suggestions && (
                <datalist id={nom + '-liste'}>
                    {options.suggestions.map((v) => <option key={v} value={v} />)}
                </datalist>
            )}
            <InputError message={errors[nom]} className="mt-1" />
        </div>
    );

    const liste = (nom, libelle, valeurs) => (
        <div>
            {etiquette(nom, libelle)}
            <select id={nom} value={data[nom]} onChange={(e) => setData(nom, e.target.value)} className={CHAMP}>
                {valeurs.map((v) => <option key={v} value={v}>{v}</option>)}
            </select>
            <InputError message={errors[nom]} className="mt-1" />
        </div>
    );

    const option = (nom, titre, texte) => (
        <label
            className={
                'flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition ' +
                (data[nom] ? 'border-action bg-action/10' : 'border-slate-200 hover:border-slate-300')
            }
        >
            <input
                type="checkbox"
                checked={data[nom]}
                onChange={(e) => setData(nom, e.target.checked)}
                className="mt-0.5 rounded border-slate-300 text-action focus:ring-action"
            />
            <span>
                <span className="block text-sm font-semibold text-marine">{titre}</span>
                <span className="block text-xs text-slate-600">{texte}</span>
            </span>
        </label>
    );

    return (
        <VitrineLayout>
            <Head title="Demander un devis de transport" />

            <div className="bg-marine-deep">
                <div className="mx-auto max-w-4xl px-4 py-12 sm:px-6">
                    <span className="inline-block rounded-full bg-action px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-marine-deep">
                        Devis gratuit et sans engagement
                    </span>
                    <h1 className="mt-4 text-3xl font-extrabold text-white sm:text-4xl">
                        Demander un devis de transport
                    </h1>
                    <p className="mt-3 max-w-2xl leading-relaxed text-slate-300">
                        Décrivez votre besoin d'enlèvement et de livraison. Notre équipe vous transmet une
                        estimation tarifaire adaptée à votre marchandise et à votre trajet, en Belgique
                        comme à l'international.
                    </p>
                </div>
            </div>

            <form onSubmit={envoyer} className="mx-auto max-w-4xl space-y-5 px-4 py-10 sm:px-6">
                <Bloc numero="1" titre="Vos coordonnées">
                    <div>
                        {etiquette('vat_number', 'Numéro de TVA')}
                        <div className="mt-1 flex gap-2">
                            <input
                                id="vat_number"
                                value={data.vat_number}
                                placeholder="BE0123456789 — ou un SIREN / SIRET français"
                                onChange={(e) => { setData('vat_number', e.target.value.toUpperCase()); setVies(null); }}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); verifierTva(); } }}
                                className="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            <button
                                type="button"
                                onClick={verifierTva}
                                disabled={verification}
                                className="shrink-0 rounded-lg bg-marine px-5 text-sm font-bold text-white transition hover:bg-marine-deep disabled:opacity-50"
                            >
                                {verification ? 'Vérification…' : 'Vérifier'}
                            </button>
                        </div>
                        <InputError message={errors.vat_number} className="mt-1" />

                        {vies?.statut === 'valide' && (
                            <p className="mt-2 text-xs text-status-delivered">
                                Entreprise identifiée dans le registre européen.
                                {vies.peppol && (
                                    <> Identifiant Peppol : <span className="font-mono text-brand-blue">{vies.peppol}</span>.</>
                                )}
                                {vies.entreprise?.dirigeant && ' Dirigeant repris du registre national, vérifiez-le.'}
                            </p>
                        )}
                        {vies && vies.statut !== 'valide' && (
                            <p className="mt-2 text-xs text-status-incident">{vies.message}</p>
                        )}
                    </div>

                    <div className="mt-5 grid gap-5 sm:grid-cols-2">
                        {champ('company_name', 'Société', { obligatoire: true, exemple: 'Ex : Meubles Van Damme SPRL' })}
                        {champ('contact_name', 'Personne de contact', { obligatoire: true, exemple: 'Nom et prénom' })}
                        {champ('email', 'Adresse e-mail', { obligatoire: true, type: 'email', exemple: 'contact@societe.be' })}
                        {champ('phone', 'Téléphone', { obligatoire: true, exemple: '+32 (0) 2 000 00 00' })}
                        {liste('customer_type', 'Vous êtes', choix.clients)}
                    </div>
                </Bloc>

                <Bloc numero="2" titre="Votre transport">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <AdresseAutocompletion
                                label="Adresse d'enlèvement"
                                required
                                onChange={() => setData((d) => ({ ...d, pickup_address: '', pickup_lat: '', pickup_lng: '' }))}
                                onSelect={({ address, lat, lng }) => setData((d) => ({
                                    ...d, pickup_address: address, pickup_lat: lat, pickup_lng: lng,
                                }))}
                                error={errors.pickup_address || errors.pickup_lat}
                            />
                        </div>

                        <div className="sm:col-span-2">
                            <AdresseAutocompletion
                                label="Adresse de livraison"
                                required
                                onChange={() => setData((d) => ({ ...d, delivery_address: '', delivery_lat: '', delivery_lng: '', delivery_country: '' }))}
                                onSelect={({ address, lat, lng, pays }) => setData((d) => ({
                                    ...d,
                                    delivery_address: address,
                                    delivery_lat: lat,
                                    delivery_lng: lng,
                                    delivery_country: pays,
                                    // Le type de trajet decoule du pays de destination.
                                    trip_type: pays === 'BE' ? choix.trajets[0] : choix.trajets[1],
                                }))}
                                error={errors.delivery_address || errors.delivery_lat || errors.delivery_country}
                            />
                        </div>

                        {champ('pickup_date', 'Date d\'enlèvement souhaitée', {
                            obligatoire: true,
                            type: 'date',
                            min: new Date().toISOString().slice(0, 10),
                        })}
                        {liste('trip_type', 'Type de trajet', choix.trajets)}
                        {liste('frequency', 'Fréquence', choix.frequences)}
                        {liste('date_flexibility', 'Flexibilité de date', choix.flexibilites)}
                    </div>
                </Bloc>

                <Bloc numero="3" titre="La marchandise">
                    <div className="grid gap-5 sm:grid-cols-3">
                        {champ('goods_type', 'Type de marchandise', {
                            obligatoire: true,
                            exemple: 'Ex : palettes, mobilier, matériel',
                            suggestions: choix.marchandises,
                        })}
                        {champ('weight', 'Poids total (kg)', { type: 'number', min: 0, exemple: '0' })}
                        {champ('volume', 'Volume / palettes', { exemple: 'Ex : 6 palettes ou 12 m³' })}
                    </div>

                    <div className="mt-5 grid gap-5 sm:grid-cols-2">
                        {liste('vehicle_type', 'Type de véhicule souhaité', choix.vehicules)}
                        {liste('insurance_value', 'Valeur estimée (assurance)', choix.assurances)}
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                        {option('needs_tail_lift', 'Hayon élévateur', 'Chargement et déchargement sans quai')}
                        {option('is_hazardous', 'Marchandise dangereuse (ADR)', 'Véhicule et chauffeur certifiés')}
                        {option('needs_express', 'Livraison express', 'Enlèvement le jour même')}
                        {option('needs_ecmr', 'Preuve de livraison (e-CMR)', 'Document signé numérique')}
                    </div>
                </Bloc>

                <Bloc numero="4" titre="Précisions">
                    {etiquette('special_instructions', 'Instructions particulières')}
                    <textarea
                        id="special_instructions"
                        rows="4"
                        value={data.special_instructions}
                        onChange={(e) => setData('special_instructions', e.target.value)}
                        placeholder="Ex : créneau de livraison imposé, contact sur place, restrictions d'accès, horaires de quai…"
                        className={CHAMP}
                    />
                    <InputError message={errors.special_instructions} className="mt-1" />
                </Bloc>

                <div className="flex flex-col items-start gap-4 rounded-2xl bg-marine-deep p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div>
                        <p className="text-lg font-bold text-white">Prêt à recevoir votre estimation ?</p>
                        <p className="mt-1 text-sm text-slate-300">
                            Réponse d'un conseiller sous 24 h ouvrées. Devis gratuit et sans engagement.
                        </p>
                    </div>
                    <button
                        type="submit"
                        disabled={processing}
                        className="shrink-0 rounded-lg bg-action px-7 py-3.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark disabled:opacity-50"
                    >
                        {processing ? 'Envoi…' : 'Recevoir mon devis →'}
                    </button>
                </div>

                <p className="text-center text-xs text-slate-600">
                    En envoyant ce formulaire, vous acceptez d'être recontacté par NBLogiTrack au sujet de
                    votre demande. Vos données ne sont jamais revendues (RGPD).
                </p>
            </form>
        </VitrineLayout>
    );
}
