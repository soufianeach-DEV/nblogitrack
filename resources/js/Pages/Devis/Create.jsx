import AdresseAutocompletion from '@/Components/AdresseAutocompletion';
import ChampRecherche from '@/Components/ChampRecherche';
import ListeRecherche from '@/Components/ListeRecherche';
import ListeSecteurs from '@/Components/ListeSecteurs';
import InputError from '@/Components/InputError';
import VitrineLayout from '@/Layouts/VitrineLayout';
import { useLangue, useTraduction } from '@/traduire';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const CHAMP = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine';
const BROUILLON = 'nblogitrack.devis.brouillon';

// Pays proposes pour l'adresse de facturation : ceux que l'on dessert.
const PAYS = ['AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

// Champs de chaque etape : une erreur du serveur ramene a son etape.
const ETAPES = [
    ['vat_number', 'company_name', 'legal_form', 'sector', 'eori_number', 'billing_street', 'billing_postal_code', 'billing_city', 'billing_country', 'correspondence_language', 'customer_type', 'end_client_name'],
    ['contact_name', 'contact_function', 'email', 'phone', 'mobile_phone', 'billing_email', 'preferred_channel', 'callback_slot'],
    ['pickup_address', 'pickup_lat', 'delivery_address', 'delivery_lat', 'delivery_country', 'pickup_date', 'delivery_date', 'trip_type', 'frequency', 'date_flexibility', 'monthly_volume', 'pickup_', 'delivery_'],
    ['goods_type', 'weight', 'volume', 'packages', 'declared_value', 'vehicle_type', 'insurance_value', 'temperature_min', 'temperature_max', 'un_number', 'adr_class', 'packing_group'],
    ['budget', 'response_deadline', 'attachments', 'special_instructions', 'privacy'],
];

// Les adresses se reprennent dans la liste (coordonnees verifiees) et les
// fichiers ne se gardent pas : le brouillon ne les contient pas.
const HORS_BROUILLON = ['pickup_address', 'pickup_lat', 'pickup_lng', 'pickup_country', 'delivery_address', 'delivery_lat', 'delivery_lng', 'delivery_country', 'attachments', 'privacy'];

// Horaires de quai les plus courants ; « Autres horaires » ouvre un champ libre.
const OUVERTURES = ['Lun-ven 7 h - 16 h', 'Lun-ven 8 h - 17 h', 'Lun-ven 6 h - 22 h', 'Lun-sam 7 h - 16 h', '24 h/24, 7 j/7'];
const AUTRE = '__autre__';

const colisVide = () => ({ type: 'palette_europe', quantite: 1, longueur: 120, largeur: 80, hauteur: '', poids_unitaire: '', empilable: true });

// Dimensions usuelles, en cm.
const DIMENSIONS = {
    palette_europe: [120, 80],
    palette_industrielle: [120, 100],
    demi_palette: [80, 60],
};

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

export default function Create({ choix, listes }) {
    const t = useTraduction();
    const langue = useLangue();
    const nomPays = useMemo(() => new Intl.DisplayNames([langue], { type: 'region' }), [langue]);

    // Meme regle que Trajet::type cote serveur, qui a le dernier mot.
    const typeTrajet = (depart = 'BE', arrivee = '') => {
        if (! arrivee) return choix.trajets[depart === 'BE' ? 0 : 2];
        if (depart === 'BE') return choix.trajets[arrivee === 'BE' ? 0 : 1];
        return choix.trajets[arrivee === 'BE' ? 2 : 3];
    };

    const initial = {
        vat_number: '', company_name: '', legal_form: '', sector: '', eori_number: '',
        billing_street: '', billing_postal_code: '', billing_city: '', billing_country: 'BE',
        correspondence_language: ['fr', 'nl', 'en'].includes(langue) ? langue : 'fr',
        customer_type: choix.clients[0],
        end_client_name: '',
        contact_name: '', contact_function: '', email: '', phone: '', mobile_phone: '', billing_email: '',
        preferred_channel: 'email', callback_slot: 'indifferent',
        pickup_address: '', pickup_lat: '', pickup_lng: '', pickup_country: 'BE',
        delivery_address: '', delivery_lat: '', delivery_lng: '', delivery_country: '',
        pickup_date: '', delivery_date: '', trip_type: choix.trajets[0],
        frequency: choix.frequences[0], date_flexibility: choix.flexibilites[0], monthly_volume: choix.volumes[0],
        pickup_contact_name: '', pickup_contact_phone: '', pickup_opening_hours: '', pickup_time_slot: '',
        pickup_has_dock: '', pickup_appointment: false, pickup_access: [], pickup_access_notes: '',
        delivery_contact_name: '', delivery_contact_phone: '', delivery_opening_hours: '', delivery_time_slot: '',
        delivery_has_dock: '', delivery_appointment: false, delivery_access: [], delivery_access_notes: '',
        goods_type: '', weight: '', volume: '', packages: [colisVide()], declared_value: '',
        vehicle_type: choix.vehicules[0], insurance_value: choix.assurances[0],
        needs_tail_lift: false, is_hazardous: false, needs_express: false, needs_ecmr: false,
        needs_temperature: false, temperature_min: '', temperature_max: '',
        un_number: '', adr_class: '', packing_group: '',
        budget: '', response_deadline: '', attachments: [], special_instructions: '', privacy: false,
    };

    const { data, setData, post, processing, errors, transform } = useForm(initial);
    const [etape, setEtape] = useState(0);
    const [manques, setManques] = useState({});
    const [brouillonRepris, setBrouillonRepris] = useState(false);
    const haut = useRef(null);

    // Brouillon : reprise au chargement, sauvegarde a chaque saisie.
    useEffect(() => {
        try {
            const garde = JSON.parse(window.localStorage.getItem(BROUILLON) ?? 'null');
            if (garde && typeof garde === 'object') {
                const repris = Object.fromEntries(Object.entries(garde).filter(([cle]) => cle in initial && ! HORS_BROUILLON.includes(cle)));
                setData((actuel) => ({ ...actuel, ...repris }));
                setBrouillonRepris(Object.keys(repris).length > 0);
            }
        } catch {
            // Stockage indisponible (navigation privee...) : pas de brouillon.
        }
    }, []);

    useEffect(() => {
        const minuteur = setTimeout(() => {
            try {
                const aGarder = Object.fromEntries(Object.entries(data).filter(([cle]) => ! HORS_BROUILLON.includes(cle)));
                window.localStorage.setItem(BROUILLON, JSON.stringify(aGarder));
            } catch {
                // Rien a faire : le brouillon est un confort.
            }
        }, 500);
        return () => clearTimeout(minuteur);
    }, [data]);

    const oublierBrouillon = () => {
        try { window.localStorage.removeItem(BROUILLON); } catch { /* ignore */ }
        setData(initial);
        setBrouillonRepris(false);
    };

    // ----- Verification du numero de TVA -----
    const [vies, setVies] = useState(null);
    const [verification, setVerification] = useState(false);
    const relances = useRef(0);
    // Ce que le registre a rempli : un autre numero le remplace, un numero
    // refuse l'efface. Un champ retouche a la main n'est plus touche.
    const repris = useRef({});
    // Le numero dont viennent ces donnees.
    const reprisPour = useRef('');

    const reprendreDuRegistre = (valeurs) => setData((actuel) => {
        const suivant = { ...actuel };
        const anciens = repris.current;
        const nouveaux = {};

        for (const cle of new Set([...Object.keys(anciens), ...Object.keys(valeurs)])) {
            if (cle in anciens) {
                // Retouche a la main depuis : on n'y touche plus.
                if (actuel[cle] !== anciens[cle]) continue;
                suivant[cle] = valeurs[cle] || initial[cle];
            } else {
                // Le contact saisi par l'utilisateur prime sur le dirigeant.
                if (! valeurs[cle] || (['contact_name', 'contact_function'].includes(cle) && actuel[cle])) continue;
                suivant[cle] = valeurs[cle];
            }

            if (valeurs[cle]) nouveaux[cle] = valeurs[cle];
        }

        repris.current = nouveaux;
        if (Object.keys(nouveaux).length === 0) reprisPour.current = '';

        return suivant;
    });
    const minuteurRelance = useRef(null);

    const paysDuNumero = (tva) => {
        const code = { EL: 'GR', XI: 'GB', CH: 'CH' }[tva.slice(0, 2)] ?? tva.slice(0, 2);
        return PAYS.includes(code) ? code : '';
    };

    const verifierTva = async (automatique = false) => {
        const tva = data.vat_number.toUpperCase().replace(/[^0-9A-Z]/g, '');

        if (! automatique) {
            relances.current = 0;
            clearTimeout(minuteurRelance.current);
        }

        if (tva.length < 6) {
            setVies({ statut: 'format', message: t('devis.tva_format', 'Saisissez le numéro complet, code pays inclus (ex. BE0123456749).') });
            return;
        }

        setVerification(true);

        try {
            const reponse = await fetch(`/verification-tva?tva=${encodeURIComponent(tva)}`);
            const resultat = await reponse.json();
            setVies(resultat);

            if (resultat.statut === 'valide') {
                const dirigeant = resultat.entreprise?.dirigeant;
                const adresse = resultat.adresse ?? {};

                if (resultat.tva) setData('vat_number', resultat.tva);
                reprisPour.current = resultat.tva || tva;
                reprendreDuRegistre({
                    company_name: resultat.nom,
                    contact_name: dirigeant ? `${dirigeant.prenom} ${dirigeant.nom}`.trim() : '',
                    contact_function: dirigeant?.fonction ?? '',
                    legal_form: resultat.entreprise?.forme_juridique ?? '',
                    sector: resultat.entreprise?.secteur ?? '',
                    billing_street: adresse.rue,
                    billing_postal_code: adresse.code_postal,
                    billing_city: adresse.ville,
                    billing_country: adresse.pays,
                });
            } else if (['invalide', 'format'].includes(resultat.statut)) {
                // Numero refuse : rien de l'ancienne entreprise ne reste ;
                // le pays suit le prefixe saisi.
                reprendreDuRegistre({ billing_country: paysDuNumero(tva) });
            } else if (tva !== reprisPour.current) {
                // Registre muet sur un autre numero : l'ancienne entreprise
                // s'efface, le pays se deduit du prefixe (EL -> Grece).
                reprendreDuRegistre({ billing_country: paysDuNumero(tva) });
            }

            // Registre sature : nouvel essai automatique, trois fois au plus.
            if (resultat.statut === 'indisponible' && relances.current < 3) {
                relances.current += 1;
                const delai = [3, 8, 15][relances.current - 1];
                setVies({ ...resultat, relance: delai });
                minuteurRelance.current = setTimeout(() => verifierTva(true), delai * 1000);
            }
        } catch {
            if (tva !== reprisPour.current) reprendreDuRegistre({ billing_country: paysDuNumero(tva) });
            setVies({ statut: 'indisponible', message: t('devis.registre_injoignable', 'Le registre européen est momentanément injoignable.') });
        } finally {
            setVerification(false);
        }
    };

    useEffect(() => () => clearTimeout(minuteurRelance.current), []);

    // ----- Calculs -----
    const douane = listes.paysDouane.includes(data.pickup_country) || listes.paysDouane.includes(data.delivery_country);
    const poidsColis = data.packages.reduce((s, c) => s + (Number(c.quantite) || 0) * (Number(c.poids_unitaire) || 0), 0);
    const volumeColis = data.packages.reduce((s, c) => s + (Number(c.quantite) || 0) * (Number(c.longueur) || 0) * (Number(c.largeur) || 0) * (Number(c.hauteur) || 0) / 1e6, 0);
    const nombre = (n, dec = 0) => Number(n).toLocaleString(langue, { maximumFractionDigits: dec });

    // ----- Champs -----
    const erreur = (nom) => errors[nom] ?? manques[nom];

    const etiquette = (nom, libelle, obligatoire = false) => (
        <label htmlFor={nom} className="text-xs font-semibold uppercase tracking-wide text-slate-600">
            {libelle}{obligatoire && <span className="text-status-incident"> *</span>}
        </label>
    );

    const champ = (nom, libelle, options = {}) => (
        <div className={options.large ? 'sm:col-span-2' : ''}>
            {etiquette(nom, libelle, options.obligatoire)}
            {options.suggestions ? (
                <ChampRecherche id={nom} value={data[nom]} onChange={(v) => setData(nom, v)} suggestions={options.suggestions} local placeholder={options.exemple} className={CHAMP} />
            ) : (
            <input
                id={nom}
                type={options.type ?? 'text'}
                min={options.min}
                max={options.max}
                step={options.step}
                value={data[nom]}
                placeholder={options.exemple}
                autoComplete={options.autocomplete}
                onChange={(e) => setData(nom, options.majuscules ? e.target.value.toUpperCase() : e.target.value)}
                className={CHAMP}
            />
            )}
            {options.aide && <p className="mt-1 text-xs text-slate-500">{options.aide}</p>}

            <InputError message={erreur(nom)} className="mt-1" />
        </div>
    );

    const liste = (nom, libelle, valeurs, libelleDe = (v) => v) => (
        <div>
            {etiquette(nom, libelle)}
            <select id={nom} value={data[nom]} onChange={(e) => setData(nom, e.target.value)} className={CHAMP}>
                {valeurs.map((v) => <option key={v} value={v}>{libelleDe(v)}</option>)}
            </select>
            <InputError message={erreur(nom)} className="mt-1" />
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

    const LIBELLES_CRENEAU = {
        matin: t('devis.creneau_matin', 'Le matin'),
        apres_midi: t('devis.creneau_apres_midi', 'L\'après-midi'),
        journee: t('devis.creneau_journee', 'Toute la journée'),
    };
    const LIBELLES_ACCES = {
        centre_ville: t('devis.acces_centre_ville', 'Centre-ville'),
        zone_basses_emissions: t('devis.acces_zbe', 'Zone de basses émissions'),
        limite_tonnage: t('devis.acces_tonnage', 'Limite de tonnage'),
        rue_etroite: t('devis.acces_rue_etroite', 'Rue étroite'),
        sans_stationnement: t('devis.acces_stationnement', 'Pas de stationnement pour un camion'),
    };
    const LIBELLES_COLIS = {
        palette_europe: t('devis.colis_palette_europe', 'Palette Europe (120 × 80)'),
        palette_industrielle: t('devis.colis_palette_industrielle', 'Palette industrielle (120 × 100)'),
        demi_palette: t('devis.colis_demi_palette', 'Demi-palette (80 × 60)'),
        colis: t('devis.colis_colis', 'Colis'),
        caisse: t('devis.colis_caisse', 'Caisse'),
        rouleau: t('devis.colis_rouleau', 'Rouleau'),
        vrac: t('devis.colis_vrac', 'Vrac'),
        autre: t('devis.colis_autre', 'Autre'),
    };

    // Le bloc « sur place » est le meme a l'enlevement et a la livraison.
    const surPlace = (lieu, titre) => {
        const basculer = (valeur) => {
            const actuel = data[lieu + '_access'] ?? [];
            setData(lieu + '_access', actuel.includes(valeur) ? actuel.filter((v) => v !== valeur) : [...actuel, valeur]);
        };

        return (
            <fieldset className="rounded-xl border border-slate-200 p-4">
                <legend className="px-1 text-sm font-semibold text-marine">{titre}</legend>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        {etiquette(lieu + '_opening_hours', t('devis.heures_ouverture', 'Heures d\'ouverture du quai'))}
                        <select
                            id={lieu + '_opening_hours'}
                            value={OUVERTURES.includes(data[lieu + '_opening_hours']) || data[lieu + '_opening_hours'] === '' ? data[lieu + '_opening_hours'] : AUTRE}
                            onChange={(e) => setData(lieu + '_opening_hours', e.target.value === AUTRE ? ' ' : e.target.value)}
                            className={CHAMP}
                        >
                            <option value="">{t('devis.ne_sait_pas', 'Je ne sais pas')}</option>
                            {OUVERTURES.map((o) => <option key={o} value={o}>{o}</option>)}
                            <option value={AUTRE}>{t('devis.autres_horaires', 'Autres horaires…')}</option>
                        </select>
                        {data[lieu + '_opening_hours'] !== '' && ! OUVERTURES.includes(data[lieu + '_opening_hours']) && (
                            <input
                                value={data[lieu + '_opening_hours'].trimStart()}
                                onChange={(e) => setData(lieu + '_opening_hours', e.target.value || ' ')}
                                placeholder={t('devis.heures_ex', 'Ex : lun-ven 7 h - 16 h')}
                                className={CHAMP + ' mt-2'}
                            />
                        )}
                    </div>
                    <div>
                        {etiquette(lieu + '_time_slot', t('devis.creneau', 'Créneau souhaité'))}
                        <select id={lieu + '_time_slot'} value={data[lieu + '_time_slot']} onChange={(e) => setData(lieu + '_time_slot', e.target.value)} className={CHAMP}>
                            <option value="">{t('devis.indifferent', 'Indifférent')}</option>
                            {listes.creneaux.map((c) => <option key={c} value={c}>{LIBELLES_CRENEAU[c]}</option>)}
                        </select>
                    </div>
                    <div>
                        {etiquette(lieu + '_has_dock', t('devis.quai', 'Quai de chargement'))}
                        <select id={lieu + '_has_dock'} value={String(data[lieu + '_has_dock'])} onChange={(e) => setData(lieu + '_has_dock', e.target.value === '' ? '' : e.target.value === 'true')} className={CHAMP}>
                            <option value="">{t('devis.ne_sait_pas', 'Je ne sais pas')}</option>
                            <option value="true">{t('devis.quai_oui', 'Oui, un quai')}</option>
                            <option value="false">{t('devis.quai_non', 'Non : hayon nécessaire')}</option>
                        </select>
                    </div>
                    <label className="mt-6 flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" checked={data[lieu + '_appointment']} onChange={(e) => setData(lieu + '_appointment', e.target.checked)} className="rounded border-slate-300 text-action focus:ring-action" />
                        {t('devis.rendez_vous', 'Prise de rendez-vous obligatoire')}
                    </label>
                </div>
                <details className="mt-4 rounded-lg bg-surface/60 px-3 py-2" open={Boolean(data[lieu + '_contact_name'] || data[lieu + '_contact_phone'] || (data[lieu + '_access'] ?? []).length || data[lieu + '_access_notes'])}>
                <summary className="cursor-pointer text-sm font-semibold text-marine">{t('devis.plus_details', 'Contact et accès (facultatif)')}</summary>
                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                    {champ(lieu + '_contact_name', t('devis.contact_sur_place', 'Contact sur place'), { exemple: t('devis.contact_ex', 'Nom et prénom') })}
                    {champ(lieu + '_contact_phone', t('devis.telephone_sur_place', 'Téléphone sur place'), { type: 'tel', exemple: '+32 470 00 00 00' })}
                </div>
                <p className="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-600">{t('devis.acces', 'Accès difficile')}</p>
                <div className="mt-2 flex flex-wrap gap-2">
                    {listes.acces.map((a) => (
                        <button
                            key={a}
                            type="button"
                            aria-pressed={(data[lieu + '_access'] ?? []).includes(a)}
                            onClick={() => basculer(a)}
                            className={'rounded-full border px-3 py-1 text-xs transition ' + ((data[lieu + '_access'] ?? []).includes(a) ? 'border-action bg-action/10 font-semibold text-action-dark' : 'border-slate-300 text-slate-600 hover:border-slate-400')}
                        >
                            {LIBELLES_ACCES[a]}
                        </button>
                    ))}
                </div>
                <div className="mt-3">
                    {champ(lieu + '_access_notes', t('devis.acces_precisions', 'Précisions sur l\'accès'), { exemple: t('devis.acces_precisions_ex', 'Ex : entrée par la rue arrière, hauteur limitée à 3,80 m') })}
                </div>
                </details>
            </fieldset>
        );
    };

    // ----- Colis -----
    const changerColis = (i, cle, valeur) => {
        const colis = data.packages.map((c, j) => {
            if (j !== i) return c;
            const suivant = { ...c, [cle]: valeur };
            // Une palette standard a ses dimensions.
            if (cle === 'type' && DIMENSIONS[valeur]) [suivant.longueur, suivant.largeur] = DIMENSIONS[valeur];
            return suivant;
        });
        setData('packages', colis);
    };

    // ----- Etapes -----
    const titres = [
        t('devis.etape_societe', 'Société'),
        t('devis.etape_contact', 'Contact'),
        t('devis.etape_trajet', 'Trajet'),
        t('devis.etape_marchandise', 'Marchandise'),
        t('devis.etape_precisions', 'Précisions'),
    ];

    // Ce qui manque pour passer a l'etape suivante (le serveur revalide tout).
    const aCompleter = (n) => {
        const requis = t('devis.champ_requis', 'Champ obligatoire.');
        const m = {};
        if (n === 0) {
            // Ce que le registre n'a pas rempli, le client le renseigne.
            for (const cle of ['company_name', 'legal_form', 'sector', 'billing_street', 'billing_city']) {
                if (! String(data[cle] ?? '').trim()) m[cle] = requis;
            }
            if (data.billing_country !== 'IE' && ! data.billing_postal_code.trim()) m.billing_postal_code = requis;
            if (data.customer_type === choix.clients[2] && ! data.end_client_name.trim()) m.end_client_name = requis;
            if (douane && ! data.eori_number.trim()) m.eori_number = t('msg.devis_eori_requis', 'Pour la Suisse, le Royaume-Uni et la Norvège, la douane exige votre numéro EORI.');
        }
        if (n === 1) {
            if (! data.contact_name.trim()) m.contact_name = requis;
            if (! /^\S+@\S+\.\S+$/.test(data.email)) m.email = t('devis.email_invalide', 'Adresse e-mail invalide.');
            if (! data.phone.trim()) m.phone = requis;
        }
        if (n === 2) {
            if (! data.pickup_lat) m.pickup_address = t('msg.devis_adresse_enlevement', 'Sélectionne l\'adresse d\'enlèvement dans les listes proposées.');
            if (! data.delivery_lat) m.delivery_address = t('msg.devis_adresse_livraison', 'Sélectionne l\'adresse de livraison dans les listes proposées.');
            if (! data.pickup_date) m.pickup_date = requis;
        }
        if (n === 3) {
            if (! data.goods_type.trim()) m.goods_type = requis;
            if (data.is_hazardous && (! /^\d{4}$/.test(data.un_number) || ! data.adr_class)) m.un_number = t('msg.devis_onu_requis', 'Pour une marchandise dangereuse, indiquez le numéro ONU (4 chiffres) et la classe ADR.');
            if (data.needs_temperature && (data.temperature_min === '' || data.temperature_max === '')) m.temperature_min = t('msg.devis_temperature_requise', 'Indiquez la plage de température à respecter.');
        }
        if (n === 4 && ! data.privacy) m.privacy = t('msg.devis_confidentialite', 'Acceptez la politique de confidentialité pour envoyer votre demande.');
        return m;
    };

    const allerA = (n) => {
        setEtape(n);
        haut.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const suivant = () => {
        const m = aCompleter(etape);
        setManques(m);
        if (Object.keys(m).length === 0) allerA(etape + 1);
    };

    // Une erreur du serveur ramene a l'etape du premier champ en faute.
    useEffect(() => {
        const cles = Object.keys(errors);
        if (cles.length === 0) return;
        const n = ETAPES.findIndex((champs) => cles.some((cle) => champs.some((c) => cle === c || cle.startsWith(c.endsWith('_') ? c : c + '.'))));
        if (n >= 0) allerA(n);
    }, [errors]);

    const envoyer = (e) => {
        e.preventDefault();
        // Entree dans un champ d'une etape avant la derniere : on avance.
        if (etape < 4) {
            suivant();
            return;
        }
        const m = aCompleter(4);
        setManques(m);
        if (Object.keys(m).length) return;

        transform((d) => ({
            ...d,
            pickup_has_dock: d.pickup_has_dock === '' ? null : d.pickup_has_dock,
            delivery_has_dock: d.delivery_has_dock === '' ? null : d.delivery_has_dock,
            packages: d.packages.filter((c) => Number(c.quantite) > 0),
        }));
        post(route('devis.store'), {
            forceFormData: true,
            onSuccess: () => { try { window.localStorage.removeItem(BROUILLON); } catch { /* ignore */ } },
        });
    };

    return (
        <VitrineLayout>
            <Head title={t('devis.titre', 'Demander un devis de transport')} />

            <div className="bg-marine-deep">
                <div className="mx-auto max-w-4xl px-4 py-12 sm:px-6">
                    <span className="inline-block rounded-full bg-action px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-marine-deep">
                        {t('devis.gratuit', 'Devis gratuit et sans engagement')}
                    </span>
                    <h1 className="mt-4 text-3xl font-extrabold text-white sm:text-4xl">
                        {t('devis.titre', 'Demander un devis de transport')}
                    </h1>
                    <p className="mt-3 max-w-2xl leading-relaxed text-slate-300">
                        {t('devis.intro', 'Décrivez votre besoin d\'enlèvement et de livraison. Notre équipe vous transmet une estimation tarifaire adaptée à votre marchandise et à votre trajet, en Belgique comme à l\'international.')}
                    </p>
                </div>
            </div>

            <form onSubmit={envoyer} className="mx-auto max-w-4xl space-y-5 px-4 py-10 sm:px-6" ref={haut}>
                {/* Progression : chaque etape deja vue reste cliquable. */}
                <nav aria-label={t('devis.progression', 'Progression')} className="rounded-2xl bg-white p-4 shadow-sm">
                    <div className="mb-3 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div className="h-full rounded-full bg-action transition-all" style={{ width: `${((etape + 1) / titres.length) * 100}%` }} />
                    </div>
                    <ol className="flex flex-wrap gap-2 text-xs">
                        {titres.map((titre, i) => (
                            <li key={titre}>
                                <button
                                    type="button"
                                    onClick={() => i < etape && allerA(i)}
                                    aria-current={i === etape ? 'step' : undefined}
                                    className={'rounded-full px-3 py-1 font-semibold ' + (i === etape ? 'bg-marine text-white' : i < etape ? 'bg-surface text-marine hover:bg-slate-200' : 'text-slate-400')}
                                >
                                    {i + 1}. {titre}
                                </button>
                            </li>
                        ))}
                    </ol>
                    {brouillonRepris && (
                        <p className="mt-3 text-xs text-slate-600">
                            {t('devis.brouillon_repris', 'Votre brouillon a été repris (les adresses et les pièces jointes sont à ressaisir).')}
                            {' '}<button type="button" onClick={oublierBrouillon} className="font-semibold text-brand-blue underline">{t('devis.brouillon_effacer', 'Tout effacer')}</button>
                        </p>
                    )}
                </nav>

                {etape === 0 && (
                    <Bloc numero="1" titre={t('devis.bloc_coordonnees', 'Votre société')}>
                        {etiquette('vat_number', t('compte.numero_tva', 'Numéro de TVA'))}
                        <div className="mt-1 flex gap-2">
                            <input
                                id="vat_number"
                                value={data.vat_number}
                                onChange={(e) => { setData('vat_number', e.target.value); setVies(null); }}
                                placeholder={t('devis.tva_exemple_europe', 'BE0123456749, FR40303265045, CHE-123.456.788…')}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); verifierTva(); } }}
                                className={CHAMP + ' mt-0 flex-1'}
                            />
                            <button
                                type="button"
                                onClick={() => verifierTva()}
                                disabled={verification}
                                className="shrink-0 rounded-lg bg-marine px-5 text-sm font-bold text-white transition hover:bg-marine-deep disabled:opacity-50"
                            >
                                {verification ? t('auth.verification', 'Vérification…') : t('auth.verifier', 'Vérifier')}
                            </button>
                        </div>
                        <p className="mt-1 text-xs text-slate-500">{t('devis.tva_aide', 'Union européenne, Suisse, Royaume-Uni et Norvège : la raison sociale et l\'adresse du siège se remplissent depuis le registre officiel.')}</p>
                        {vies?.statut === 'valide' && (
                            <p className="mt-2 rounded-lg bg-status-delivered/10 px-3 py-2 text-xs text-status-delivered">
                                {t('devis.tva_valide_registre', 'Numéro actif (:registre).', { registre: vies.registre ?? 'VIES' })}
                                {vies.entreprise?.situation && ! vies.entreprise.situation.acceptable && <span className="font-semibold text-status-incident"> {vies.entreprise.situation.libelle}.</span>}
                                {vies.peppol && <> {t('auth.peppol', 'Identifiant Peppol :')} <span className="font-mono text-brand-blue">{vies.peppol}</span>.</>}
                                {vies.entreprise?.dirigeant && ' ' + t('devis.dirigeant', 'Dirigeant repris du registre national, vérifiez-le.')}
                                {vies.entreprise?.forme_deduite && ' ' + t('devis.forme_deduite', 'Forme juridique lue dans le nom de la société, vérifiez-la.')}
                            </p>
                        )}
                        {vies?.statut === 'valide' && vies.non_publie?.length > 0 && (
                            <p className="mt-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                {t('devis.non_publie', 'Numéro valide, mais le registre de ce pays ne publie pas : :champs. Merci de les compléter ci-dessous.', {
                                    champs: vies.non_publie.map((c) => ({
                                        nom: t('devis.societe', 'Société'),
                                        adresse: t('devis.adresse_facturation', 'Adresse de facturation'),
                                        forme_juridique: t('devis.forme_juridique', 'Forme juridique'),
                                        secteur: t('devis.secteur', 'Secteur d\'activité'),
                                    }[c] ?? c).toLowerCase()).join(', '),
                                })}
                            </p>
                        )}
                        {vies && vies.statut !== 'valide' && (
                            <p className={'mt-2 text-xs ' + (vies.statut === 'non_verifie' ? 'text-slate-600' : 'text-status-incident')}>
                                {vies.message}
                                {vies.relance && ' ' + t('devis.tva_relance', 'Nouvel essai automatique dans :n s.', { n: vies.relance })}
                            </p>
                        )}
                        <InputError message={erreur('vat_number')} className="mt-1" />

                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            {champ('company_name', t('devis.societe', 'Raison sociale'), { obligatoire: true, exemple: t('devis.societe_ex', 'Ex : Meubles Van Damme SPRL'), autocomplete: 'organization' })}
                            {champ('legal_form', t('devis.forme_juridique', 'Forme juridique'), { obligatoire: true, exemple: t('devis.forme_ex', 'Ex : SRL, SA, SAS, GmbH') })}
                            <div>
                                {etiquette('sector', t('devis.secteur', 'Secteur d\'activité'), true)}
                                <ListeSecteurs id="sector" value={data.sector} onChange={(v) => setData('sector', v)} groupes={listes.secteurs} className={CHAMP} />
                                <InputError message={erreur('sector')} className="mt-1" />
                            </div>
                            {champ('eori_number', t('devis.eori', 'Numéro EORI'), {
                                obligatoire: douane,
                                majuscules: true,
                                exemple: 'BE0123456749',
                                aide: douane
                                    ? t('devis.eori_aide_douane', 'Obligatoire pour la Suisse, le Royaume-Uni et la Norvège (dédouanement).')
                                    : t('devis.eori_aide', 'Utile seulement pour les envois hors Union européenne.'),
                            })}
                        </div>

                        <p className="mt-6 text-xs font-semibold uppercase tracking-wide text-slate-600">{t('devis.adresse_facturation', 'Adresse de facturation')}</p>
                        <div className="mt-2 grid gap-5 sm:grid-cols-4">
                            <div className="sm:col-span-4">{champ('billing_street', t('devis.rue_numero', 'Rue et numéro'), { obligatoire: true, autocomplete: 'street-address' })}</div>
                            {champ('billing_postal_code', t('devis.code_postal', 'Code postal'), { obligatoire: data.billing_country !== 'IE', autocomplete: 'postal-code' })}
                            <div className="sm:col-span-2">{champ('billing_city', t('devis.ville', 'Ville'), { obligatoire: true, autocomplete: 'address-level2' })}</div>
                            <div>
                                {etiquette('billing_country', t('auth.pays', 'Pays'))}
                                <ListeRecherche id="billing_country" value={data.billing_country} onChange={(v) => setData('billing_country', v)} options={PAYS.map((c) => ({ valeur: c, libelle: nomPays.of(c) }))} className={CHAMP} />
                                <InputError message={erreur('billing_country')} className="mt-1" />
                            </div>
                        </div>

                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                {liste('customer_type', t('devis.relation', 'Votre relation avec NBLogiTrack'), choix.clients)}
                            </div>
                            {data.customer_type === choix.clients[2] && (
                                <div className="sm:col-span-2">
                                    {champ('end_client_name', t('devis.client_final', 'Entreprise pour laquelle vous demandez'), {
                                        obligatoire: true,
                                        exemple: t('devis.client_final_ex', 'Ex : Brasserie Dupont SA'),
                                        aide: t('devis.client_final_aide', 'Vos coordonnées restent celles de votre société ; le transport est réalisé pour cette entreprise.'),
                                    })}
                                </div>
                            )}
                            {liste('correspondence_language', t('devis.langue', 'Langue de correspondance'), ['fr', 'nl', 'en'], (l) => ({ fr: 'Français', nl: 'Nederlands', en: 'English' })[l])}
                        </div>
                    </Bloc>
                )}

                {etape === 1 && (
                    <Bloc numero="2" titre={t('devis.bloc_contact', 'Votre contact')}>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {champ('contact_name', t('auth.personne_contact', 'Personne de contact'), { obligatoire: true, exemple: t('devis.contact_ex', 'Nom et prénom'), autocomplete: 'name' })}
                            {champ('contact_function', t('devis.fonction', 'Fonction'), { exemple: t('devis.fonction_ex', 'Ex : Responsable logistique') })}
                            {champ('email', t('devis.email', 'Adresse e-mail'), { obligatoire: true, type: 'email', exemple: t('devis.email_ex', 'contact@societe.be'), autocomplete: 'email' })}
                            {champ('billing_email', t('devis.email_facturation', 'E-mail de facturation'), { type: 'email', exemple: t('devis.email_facturation_ex', 'comptabilite@societe.be'), aide: t('devis.email_facturation_aide', 'Si les factures partent ailleurs.') })}
                            {champ('phone', t('auth.telephone', 'Téléphone'), { obligatoire: true, type: 'tel', exemple: '+32 (0) 2 000 00 00', autocomplete: 'tel' })}
                            {champ('mobile_phone', t('devis.portable', 'Téléphone portable'), { type: 'tel', exemple: '+32 470 00 00 00' })}
                        </div>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <fieldset>
                                <legend className="text-xs font-semibold uppercase tracking-wide text-slate-600">{t('devis.canal', 'Comment vous répondre ?')}</legend>
                                <div className="mt-2 flex gap-5">
                                    {[['email', t('devis.canal_email', 'Par e-mail')], ['phone', t('devis.canal_telephone', 'Par téléphone')]].map(([valeur, libelle]) => (
                                        <label key={valeur} className="flex items-center gap-2 text-sm text-slate-700">
                                            <input type="radio" name="preferred_channel" checked={data.preferred_channel === valeur} onChange={() => setData('preferred_channel', valeur)} className="text-marine focus:ring-marine" />
                                            {libelle}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                            {liste('callback_slot', t('devis.rappel', 'Meilleur moment pour vous rappeler'), ['indifferent', 'matin', 'apres_midi'], (c) => ({
                                indifferent: t('devis.indifferent', 'Indifférent'), matin: t('devis.creneau_matin', 'Le matin'), apres_midi: t('devis.creneau_apres_midi', 'L\'après-midi'),
                            })[c])}
                        </div>
                    </Bloc>
                )}

                {etape === 2 && (
                    <Bloc numero="3" titre={t('devis.bloc_transport', 'Votre transport')}>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <AdresseAutocompletion
                                    label={t('commande.enlevement', 'Adresse d\'enlèvement')}
                                    required
                                    onChange={() => setData((d) => ({ ...d, pickup_address: '', pickup_lat: '', pickup_lng: '' }))}
                                    onSelect={({ address, lat, lng, pays }) => setData((d) => ({
                                        ...d, pickup_address: address, pickup_lat: lat, pickup_lng: lng, pickup_country: pays,
                                        trip_type: typeTrajet(pays, d.delivery_country),
                                    }))}
                                    error={erreur('pickup_address') || errors.pickup_lat}
                                />
                                {data.pickup_address && <p className="mt-1 text-xs text-status-delivered">{data.pickup_address}</p>}
                            </div>
                            <div className="sm:col-span-2">
                                <AdresseAutocompletion
                                    label={t('commande.livraison', 'Adresse de livraison')}
                                    required
                                    onChange={() => setData((d) => ({ ...d, delivery_address: '', delivery_lat: '', delivery_lng: '', delivery_country: '' }))}
                                    onSelect={({ address, lat, lng, pays }) => setData((d) => ({
                                        ...d, delivery_address: address, delivery_lat: lat, delivery_lng: lng, delivery_country: pays,
                                        trip_type: typeTrajet(d.pickup_country, pays),
                                    }))}
                                    error={erreur('delivery_address') || errors.delivery_lat || errors.delivery_country}
                                />
                                {data.delivery_address && <p className="mt-1 text-xs text-status-delivered">{data.delivery_address}</p>}
                            </div>

                            {champ('pickup_date', t('devis.date_souhaitee', 'Date d\'enlèvement souhaitée'), { obligatoire: true, type: 'date', min: new Date().toISOString().slice(0, 10) })}
                            {champ('delivery_date', t('devis.date_livraison', 'Date de livraison souhaitée'), { type: 'date', min: data.pickup_date || new Date().toISOString().slice(0, 10) })}
                            {liste('date_flexibility', t('devis.flexibilite', 'Flexibilité de date'), choix.flexibilites)}
                            {liste('frequency', t('devis.frequence', 'Fréquence'), choix.frequences)}
                            {liste('monthly_volume', t('devis.volume_mensuel', 'Volume prévu'), choix.volumes)}
                        </div>
                        {data.pickup_lat && data.delivery_lat && (
                            <p className="mt-4 rounded-lg bg-surface px-3 py-2 text-sm text-marine">
                                <span className="font-semibold">{t('devis.type_trajet', 'Type de trajet')} : </span>
                                {nomPays.of(data.pickup_country || 'BE')} → {nomPays.of(data.delivery_country)} · {
                                    data.pickup_country === data.delivery_country
                                        ? t('devis.trajet_national', 'national')
                                        : douane
                                            ? t('devis.trajet_hors_ue', 'hors Union européenne (douane)')
                                            : t('devis.trajet_intracommunautaire', 'intracommunautaire')
                                }
                            </p>
                        )}
                        {douane && (
                            <p className="mt-4 rounded-lg bg-action/10 px-3 py-2 text-xs text-action-dark">
                                {t('devis.douane_aide', 'Trajet hors union douanière : prévoyez votre numéro EORI, la facture commerciale et le code des marchandises. Nous nous chargeons du transit.')}
                            </p>
                        )}
                        <div className="mt-6 grid gap-5 lg:grid-cols-2">
                            {surPlace('pickup', t('devis.sur_place_enlevement', 'À l\'enlèvement'))}
                            {surPlace('delivery', t('devis.sur_place_livraison', 'À la livraison'))}
                        </div>
                    </Bloc>
                )}

                {etape === 3 && (
                    <Bloc numero="4" titre={t('devis.bloc_marchandise', 'La marchandise')}>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {champ('goods_type', t('commande.marchandise', 'Type de marchandise'), { obligatoire: true, exemple: t('devis.marchandise_ex', 'Ex : palettes, mobilier, matériel'), suggestions: choix.marchandises })}
                            {champ('declared_value', t('devis.valeur_declaree', 'Valeur de la marchandise (€ HT)'), { type: 'number', min: 0, exemple: '0', aide: t('devis.valeur_aide', 'Pour l\'assurance ad valorem.') })}
                        </div>

                        <div className="mt-6">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">{t('devis.colis_titre', 'Colis et palettes')}</p>
                            <div className="mt-2 space-y-3">
                                {data.packages.map((c, i) => (
                                    <div key={i} className="grid items-end gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-8">
                                        <label className="sm:col-span-2 text-xs text-slate-600">
                                            {t('devis.colis_type', 'Type')}
                                            <select value={c.type} onChange={(e) => changerColis(i, 'type', e.target.value)} className={CHAMP}>
                                                {listes.colis.map((code) => <option key={code} value={code}>{LIBELLES_COLIS[code]}</option>)}
                                            </select>
                                        </label>
                                        {[['quantite', t('devis.colis_quantite', 'Nombre'), 1], ['longueur', t('devis.colis_longueur', 'L (cm)'), 1], ['largeur', t('devis.colis_largeur', 'l (cm)'), 1], ['hauteur', t('devis.colis_hauteur', 'H (cm)'), 1], ['poids_unitaire', t('devis.colis_poids', 'kg / unité'), 0]].map(([cle, libelle, min]) => (
                                            <label key={cle} className="text-xs text-slate-600">
                                                {libelle}
                                                <input type="number" min={min} value={c[cle]} onChange={(e) => changerColis(i, cle, e.target.value)} className={CHAMP} />
                                            </label>
                                        ))}
                                        <div className="flex items-center justify-between gap-2 pb-2">
                                            <label className="flex items-center gap-1 text-xs text-slate-600">
                                                <input type="checkbox" checked={c.empilable} onChange={(e) => changerColis(i, 'empilable', e.target.checked)} className="rounded border-slate-300 text-action focus:ring-action" />
                                                {t('devis.colis_empilable', 'Empilable')}
                                            </label>
                                            {data.packages.length > 1 && (
                                                <button type="button" onClick={() => setData('packages', data.packages.filter((_, j) => j !== i))} className="text-xs font-semibold text-status-incident" aria-label={t('action.supprimer', 'Supprimer')}>×</button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <InputError message={Object.entries(errors).find(([cle]) => cle.startsWith('packages'))?.[1]} className="mt-1" />
                            {data.packages.length < 20 && (
                                <button type="button" onClick={() => setData('packages', [...data.packages, colisVide()])} className="mt-2 text-sm font-semibold text-brand-blue">
                                    + {t('devis.colis_ajouter', 'Ajouter une ligne')}
                                </button>
                            )}
                            {(poidsColis > 0 || volumeColis > 0) && (
                                <p className="mt-2 text-xs text-slate-600">
                                    {t('devis.colis_total', 'Total déclaré : :poids kg · :volume m³', { poids: nombre(poidsColis), volume: nombre(volumeColis, 2) })}
                                </p>
                            )}
                        </div>

                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            {champ('weight', t('devis.poids_total', 'Poids total (kg)'), { type: 'number', min: 0, exemple: poidsColis > 0 ? nombre(poidsColis) : '0', aide: t('devis.poids_aide', 'Laissé vide : la somme des colis.') })}
                            {champ('volume', t('devis.volume_palettes', 'Volume / palettes'), { exemple: t('devis.volume_ex', 'Ex : 6 palettes ou 12 m³') })}
                            {liste('vehicle_type', t('devis.type_vehicule', 'Type de véhicule souhaité'), choix.vehicules)}
                            {liste('insurance_value', t('devis.assurance', 'Valeur estimée (assurance)'), choix.assurances)}
                        </div>

                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            {option('needs_tail_lift', t('devis.hayon', 'Hayon élévateur'), t('devis.hayon_texte', 'Chargement et déchargement sans quai'))}
                            {option('is_hazardous', t('devis.adr', 'Marchandise dangereuse (ADR)'), t('devis.adr_texte', 'Véhicule et chauffeur certifiés'))}
                            {option('needs_temperature', t('devis.temperature', 'Température dirigée'), t('devis.temperature_texte', 'Camion frigorifique, plage à respecter'))}
                            {option('needs_express', t('devis.express', 'Livraison express'), t('devis.express_texte', 'Enlèvement le jour même'))}
                            {option('needs_ecmr', t('devis.ecmr', 'Preuve de livraison (e-CMR)'), t('devis.ecmr_texte', 'Document signé numérique'))}
                        </div>

                        {data.is_hazardous && (
                            <div className="mt-5 grid gap-5 rounded-xl bg-status-incident/5 p-4 sm:grid-cols-3">
                                {champ('un_number', t('devis.numero_onu', 'Numéro ONU'), { obligatoire: true, exemple: '1203', aide: t('devis.onu_aide', 'Quatre chiffres, sur la fiche de données de sécurité.') })}
                                <div>
                                    {etiquette('adr_class', t('devis.classe_adr', 'Classe ADR'), true)}
                                    <select id="adr_class" value={data.adr_class} onChange={(e) => setData('adr_class', e.target.value)} className={CHAMP}>
                                        <option value="">—</option>
                                        {listes.classesAdr.map((c) => <option key={c} value={c}>{c}</option>)}
                                    </select>
                                    <InputError message={erreur('adr_class')} className="mt-1" />
                                </div>
                                <div>
                                    {etiquette('packing_group', t('devis.groupe_emballage', 'Groupe d\'emballage'))}
                                    <select id="packing_group" value={data.packing_group} onChange={(e) => setData('packing_group', e.target.value)} className={CHAMP}>
                                        <option value="">—</option>
                                        {['I', 'II', 'III'].map((g) => <option key={g} value={g}>{g}</option>)}
                                    </select>
                                </div>
                            </div>
                        )}

                        {data.needs_temperature && (
                            <div className="mt-5 grid gap-5 rounded-xl bg-brand-blue/5 p-4 sm:grid-cols-2">
                                {champ('temperature_min', t('devis.temperature_min', 'Température minimale (°C)'), { obligatoire: true, type: 'number', min: -30, max: 30, step: 0.5, exemple: '2' })}
                                {champ('temperature_max', t('devis.temperature_max', 'Température maximale (°C)'), { obligatoire: true, type: 'number', min: -30, max: 30, step: 0.5, exemple: '8' })}
                            </div>
                        )}
                    </Bloc>
                )}

                {etape === 4 && (
                    <Bloc numero="5" titre={t('devis.bloc_precisions', 'Précisions')}>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {champ('budget', t('devis.budget', 'Budget indicatif (€ HT)'), { type: 'number', min: 0, aide: t('devis.budget_aide', 'Facultatif : il nous aide à proposer la bonne formule.') })}
                            {champ('response_deadline', t('devis.reponse_avant', 'Réponse souhaitée avant le'), { type: 'date', min: new Date().toISOString().slice(0, 10) })}
                        </div>

                        <div className="mt-5">
                            {etiquette('special_instructions', t('commande.instructions', 'Instructions particulières'))}
                            <textarea
                                id="special_instructions"
                                rows="4"
                                value={data.special_instructions}
                                onChange={(e) => setData('special_instructions', e.target.value)}
                                placeholder={t('devis.instructions_ex2', 'Ex : marchandise fragile, sanglage particulier, documents à remettre au destinataire…')}
                                className={CHAMP}
                            />
                            <InputError message={erreur('special_instructions')} className="mt-1" />
                        </div>

                        <div className="mt-5">
                            {etiquette('attachments', t('devis.pieces_jointes', 'Pièces jointes'))}
                            <input
                                id="attachments"
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.docx,.doc,.csv"
                                onChange={(e) => setData('attachments', Array.from(e.target.files ?? []).slice(0, 5))}
                                className="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-surface file:px-4 file:py-2 file:text-sm file:font-semibold file:text-marine"
                            />
                            <p className="mt-1 text-xs text-slate-500">{t('devis.pieces_aide', 'Bon de commande, fiche de données de sécurité, plan d\'accès… 5 fichiers, 10 Mo chacun au plus.')}</p>
                            <InputError message={Object.entries(errors).find(([cle]) => cle.startsWith('attachments'))?.[1]} className="mt-1" />
                        </div>

                        <label className="mt-6 flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" checked={data.privacy} onChange={(e) => setData('privacy', e.target.checked)} className="mt-0.5 rounded border-slate-300 text-action focus:ring-action" />
                            <span>
                                {t('devis.confidentialite_accepte', 'J\'accepte que NBLogiTrack traite ces informations pour répondre à ma demande, selon sa')}{' '}
                                <a href={route('pages.show', 'confidentialite')} target="_blank" rel="noreferrer" className="font-semibold text-brand-blue underline">{t('devis.politique_confidentialite', 'politique de confidentialité')}</a>.
                                <span className="text-status-incident"> *</span>
                            </span>
                        </label>
                        <InputError message={erreur('privacy')} className="mt-1" />
                    </Bloc>
                )}

                <div className="flex flex-col items-start gap-4 rounded-2xl bg-marine-deep p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div>
                        <p className="text-lg font-bold text-white">
                            {etape < 4 ? t('devis.etape_n', 'Étape :n sur :total', { n: etape + 1, total: titres.length }) : t('devis.pret', 'Prêt à recevoir votre estimation ?')}
                        </p>
                        <p className="mt-1 text-sm text-slate-300">
                            {t('devis.reponse', 'Réponse d\'un conseiller sous 24 h ouvrées. Devis gratuit et sans engagement.')}
                        </p>
                    </div>
                    <div className="flex shrink-0 gap-3">
                        {etape > 0 && (
                            <button type="button" onClick={() => allerA(etape - 1)} className="rounded-lg border border-slate-500 px-5 py-3.5 text-sm font-bold text-white transition hover:bg-white/10">
                                ← {t('devis.precedent', 'Précédent')}
                            </button>
                        )}
                        {etape < 4 ? (
                            <button type="button" onClick={suivant} className="rounded-lg bg-action px-7 py-3.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark">
                                {t('devis.suivant', 'Suivant')} →
                            </button>
                        ) : (
                            <button type="submit" disabled={processing} className="rounded-lg bg-action px-7 py-3.5 text-sm font-bold text-marine-deep transition hover:bg-action-dark disabled:opacity-50">
                                {processing ? t('devis.envoi', 'Envoi…') : t('devis.recevoir', 'Recevoir mon devis →')}
                            </button>
                        )}
                    </div>
                </div>
            </form>
        </VitrineLayout>
    );
}
