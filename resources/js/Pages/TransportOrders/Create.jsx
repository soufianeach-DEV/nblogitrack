import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ListeRecherche from '@/Components/ListeRecherche';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AdresseAutocompletion from '@/Components/AdresseAutocompletion';
import { useLangue, useLocale, useTraduction, useVocabulaire } from '@/traduire';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const MARCHANDISES = [
    'Boissons',
    'Colis express',
    'Machines',
    'Matériaux de construction',
    'Matériel électronique',
    'Mobilier',
    'Palettes',
    'Pièces automobiles',
    'Produits alimentaires',
    'Produits chimiques',
    'Produits pharmaceutiques',
    'Textile',
    'Autre',
];

const NOMS_OFFRE = {
    ECO: ['commande.offre_eco', 'Éco'],
    STANDARD: ['commande.offre_standard', 'Standard'],
    EXPRESS: ['commande.offre_express', 'Express'],
};

const ORDRE_OFFRE = { ECO: 0, STANDARD: 1, EXPRESS: 2 };

export default function Create({ tariffGrids, marchandisesAdr = [], poidsMax = 44000, volumeMax = 120, flotte = [], paysEnlevement = ['BE'], remiseFretRetour = 0 }) {
    const t = useTraduction();
    const v = useVocabulaire();
    const locale = useLocale();

    const nomRegion = new Intl.DisplayNames([useLangue()], { type: 'region' });
    const { data, setData, post, processing, errors, transform } = useForm({
        pickup_address: '', pickup_country: 'BE', delivery_address: '', delivery_country: '',
        shipper_name: '', shipper_phone: '', loading_reference: '',
        pickup_lat: '', pickup_lng: '', delivery_lat: '', delivery_lng: '',
        weight: '', volume: '', goods_type: '', is_hazardous: false, needs_tail_lift: false, priority: 'NORMAL',
        pickup_date: '', requested_delivery_date: '',
        tariff_grid_id: '', special_instructions: '',
    });

    const paysDepart = data.pickup_country || 'BE';
    const etranger = paysDepart !== 'BE';

    const [distance, setDistance] = useState(null);
    const [loadingDist, setLoadingDist] = useState(false);

    const pad = (n) => String(n).padStart(2, '0');
    const maintenant = new Date();
    const today = `${maintenant.getFullYear()}-${pad(maintenant.getMonth() + 1)}-${pad(maintenant.getDate())}`;
    const nowLocal = `${today}T${pad(maintenant.getHours())}:${pad(maintenant.getMinutes())}`;

    // Tout ce que le serveur calcule : prix (ligne et fret retour), delais,
    // premier enlevement possible hors de Belgique.
    const [estim, setEstim] = useState({});
    const [relance, setRelance] = useState(0);
    const prix = estim.prix ?? {};

    // Le prix vient du serveur, qui calcule exactement ce qui sera
    // enregistre : les formules restent coherentes entre elles.
    useEffect(() => {
        if (!data.pickup_lat || !data.delivery_lat || !data.delivery_country) { setDistance(null); setEstim({}); return; }
        setLoadingDist(true);
        const minuteur = setTimeout(() => {
            window.axios.post(route('transport-orders.estimation'), {
                pickup_country: paysDepart,
                pickup_address: data.pickup_address,
                delivery_country: data.delivery_country,
                pickup_lat: data.pickup_lat, pickup_lng: data.pickup_lng,
                delivery_lat: data.delivery_lat, delivery_lng: data.delivery_lng,
                pickup_date: data.pickup_date || null,
                weight: data.weight || null,
                volume: data.volume || null,
                is_hazardous: Boolean(data.is_hazardous),
                needs_tail_lift: Boolean(data.needs_tail_lift),
            })
                .then(({ data: r }) => { setDistance(r.distance_km); setEstim(r ?? {}); })
                .catch((e) => { setDistance(null); setEstim(e.response?.data?.erreur ? { erreur: e.response.data.erreur } : { indisponible: true }); })
                .finally(() => setLoadingDist(false));
        }, 400);
        return () => clearTimeout(minuteur);
    }, [data.pickup_lat, data.pickup_lng, data.delivery_lat, data.delivery_lng, data.delivery_country, paysDepart, data.pickup_date, data.weight, data.volume, data.is_hazardous, data.needs_tail_lift, relance]);

    // Le tarif a change entre l'affichage et l'envoi : on relit le prix.
    useEffect(() => {
        if (errors.tariff_grid_id) setRelance((n) => n + 1);
    }, [errors.tariff_grid_id]);

    const premier = estim.premier_enlevement ?? null;
    const minEnlevement = etranger && premier ? premier.local : nowLocal;

    const dateHeure = (iso) => new Date(iso).toLocaleString(locale, { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    const fr = (n, dec = 2) => Number(n).toLocaleString(locale, { minimumFractionDigits: dec, maximumFractionDigits: dec });
    // Montant avec le symbole a sa place selon la langue (« 1 234,50 € », « €1,234.50 »).
    const euros = (n) => Number(n).toLocaleString(locale, { style: 'currency', currency: 'EUR' });
    const kmTxt = distance != null ? distance.toLocaleString(locale, { maximumFractionDigits: 1 }) : '';

    // La zone tarifaire est le pays etranger du trajet, comme Trajet::zone
    // cote serveur : un import Lyon -> Bruxelles coute le prix de l'export
    // Bruxelles -> Lyon. Deux pays etrangers : sur devis.
    const zone = ! data.delivery_country ? null
        : paysDepart === 'BE' ? data.delivery_country
            : data.delivery_country === 'BE' ? paysDepart : null;
    const surDevis = Boolean(data.delivery_country) && zone === null;
    const grillesVisibles = zone ? tariffGrids.filter((g) => g.zone === zone) : [];

    const offres = [...grillesVisibles].sort((a, b) => (ORDRE_OFFRE[a.service_level] ?? 9) - (ORDRE_OFFRE[b.service_level] ?? 9));

    const jourSeul = (valeur) => {
        const d = new Date(valeur);
        d.setHours(0, 0, 0, 0);
        return d;
    };

    const delaiJours = data.requested_delivery_date
        ? Math.round((jourSeul(data.requested_delivery_date) - jourSeul(data.pickup_date ? data.pickup_date.slice(0, 10) : today)) / 86400000)
        : null;

    // Delai promis par le serveur : la formule, jamais moins que la route.
    const delai = (g) => Number(estim.delais?.[g.id] ?? g.delivery_days);

    const formuleAdaptee = () => {
        if (grillesVisibles.length === 0) return null;
        const parDelai = [...grillesVisibles].sort((a, b) => delai(b) - delai(a));
        if (delaiJours === null) {
            return parDelai.find((g) => g.service_level === 'STANDARD') || parDelai[0];
        }
        return parDelai.find((g) => delai(g) <= delaiJours) || parDelai[parDelai.length - 1];
    };

    const grilleAuto = formuleAdaptee();
    const delaiTropCourt = delaiJours !== null && grilleAuto && delai(grilleAuto) > delaiJours;

    useEffect(() => {
        if (!zone || !grilleAuto) return;
        if (String(data.tariff_grid_id) !== String(grilleAuto.id)) {
            setData('tariff_grid_id', String(grilleAuto.id));
        }
    }, [zone, delaiJours, grillesVisibles.length, JSON.stringify(estim.delais ?? {})]);

    const urgence48h = delaiJours !== null && delaiJours <= 2;

    useEffect(() => {
        if (urgence48h && data.priority !== 'URGENT') setData('priority', 'URGENT');
    }, [urgence48h]);

    const [soumis, setSoumis] = useState(false);
    // Produits chimiques, batteries, airbags... : le client dit
    // explicitement si l'envoi est soumis a l'ADR, la case decochee par
    // defaut ne vaut pas declaration.
    const aDeclarer = marchandisesAdr.includes(data.goods_type);

    // Chaque limite prise a part ne suffit pas : un envoi ADR avec hayon
    // de 20 t doit trouver un camion qui reunit les trois. Dit tout de
    // suite, pas a l'envoi du formulaire.
    const kg = Number(data.weight) || 0;
    const m3 = Number(data.volume) || null;
    const tropLourd = kg > poidsMax;
    const tropVolumineux = m3 !== null && m3 > volumeMax;
    const horsFlotte = kg > 0 && ! tropLourd && ! tropVolumineux && flotte.length > 0 && ! flotte.some((v) => v.kg >= kg
        && (m3 === null || v.m3 === null || v.m3 >= m3)
        && (! data.is_hazardous || v.adr)
        && (! data.needs_tail_lift || v.hayon));
    const refusFlotte = horsFlotte
        ? t('msg.flotte_incapable', 'Aucun camion de notre flotte ne réunit ces conditions (:conditions) : demandez un devis.', {
            conditions: [
                kg.toLocaleString(locale) + ' kg',
                m3 !== null ? m3.toLocaleString(locale) + ' m³' : null,
                data.is_hazardous ? t('commande.cond_adr', 'équipement ADR') : null,
                data.needs_tail_lift ? t('commande.cond_hayon', 'hayon élévateur') : null,
            ].filter(Boolean).join(', '),
        })
        : null;
    const messagePoids = tropLourd ? t('msg.poids_flotte', 'Aucun camion de notre flotte ne charge plus de :max t : demandez un devis.', { max: (poidsMax / 1000).toLocaleString(locale) }) : null;
    const messageVolume = tropVolumineux ? t('msg.volume_flotte', 'Aucun camion de notre flotte ne charge plus de :max m³ : demandez un devis.', { max: volumeMax.toLocaleString(locale) }) : null;

    const manque = {
        pickup: !data.pickup_lat ? t('commande.manque_depart', 'Complétez l\'adresse de départ : pays, ville, code postal, rue et numéro.') : null,
        delivery: !data.delivery_lat ? t('commande.manque_destination', 'Complétez l\'adresse de destination : pays, ville, code postal, rue et numéro.') : null,
        weight: !data.weight
            ? t('commande.manque_poids', 'Indiquez le poids de la marchandise.')
            : messagePoids,
        volume: messageVolume,
        flotte: refusFlotte,
        adr: aDeclarer && data.is_hazardous === null ? t('msg.declaration_adr_requise', 'Pour ce type de marchandise, indiquez si l\'envoi est soumis à l\'ADR (matière dangereuse) ou non.') : null,
        goods: !data.goods_type ? t('commande.manque_marchandise', 'Choisissez le type de marchandise.') : null,
        grille: !data.tariff_grid_id ? t('commande.manque_formule', 'Choisissez une formule de livraison.') : null,
        devis: surDevis || estim.erreur ? (estim.erreur ?? t('msg.trajet_sur_devis', 'Ce trajet ne commence ni ne finit en Belgique : il se traite sur devis.')) : null,
        enlevement: etranger && ! data.pickup_date ? t('msg.enlevement_etranger_date_requise', 'Hors de Belgique, la date d\'enlèvement est obligatoire (au plus tôt le :date).', { date: premier ? dateHeure(premier.local) : '…' }) : null,
        expediteur: etranger && (! data.shipper_name.trim() || ! data.shipper_phone.trim()) ? t('msg.expediteur_requis', 'Indiquez le nom et le téléphone de l\'expéditeur qui charge à l\'étranger.') : null,
        delai: delaiTropCourt ? t('commande.delai_impossible', 'Aucune formule ne tient ce délai : comptez au moins :n jours vers cette destination.', { n: delai(grilleAuto) }) : null,
    };

    const submit = (e) => {
        e.preventDefault();
        setSoumis(true);
        if (Object.values(manque).some(Boolean)) return;
        // Le prix vu part avec la commande : s'il a change entre-temps, le
        // serveur refuse au lieu de facturer un montant jamais affiche.
        transform((d) => ({ ...d, prix_annonce: total }));
        post(route('transport-orders.store'));
    };
    const selectCls = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-marine focus:ring-marine';

    const prixDe = (g) => (g && prix[g.id] != null ? Number(prix[g.id]) : null);

    const selectedGrid = tariffGrids.find((g) => String(g.id) === String(data.tariff_grid_id));
    // Pas de prix pour un envoi qu'aucun camion ne peut prendre.
    const total = refusFlotte || messageVolume ? null : prixDe(selectedGrid);

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">{t('commande.titre', 'Nouvelle expédition')}</h1>}>
            <Head title={t('commande.titre', 'Nouvelle expédition')} />

            <form onSubmit={submit} className="w-full space-y-5 rounded-2xl bg-white p-8 shadow-sm">
                <div className="grid gap-5 sm:grid-cols-2">
                    <AdresseAutocompletion
                        label={t('commande.adresse_depart', 'Adresse de départ')}
                        required
                        pays={paysEnlevement}
                        onChange={(v) => setData({ ...data, pickup_address: v, pickup_lat: '', pickup_lng: '' })}
                        onSelect={({ address, lat, lng, pays }) => setData({ ...data, pickup_address: address, pickup_lat: lat, pickup_lng: lng, pickup_country: pays, tariff_grid_id: '', pickup_date: pays !== 'BE' ? '' : data.pickup_date })}
                        error={(soumis && manque.pickup) || errors.pickup_address || errors.pickup_lat || errors.pickup_country}
                    />
                    <AdresseAutocompletion
                        label={t('commande.adresse_destination', 'Adresse de destination')}
                        required
                        onChange={(v) => setData({ ...data, delivery_address: v, delivery_lat: '', delivery_lng: '', delivery_country: '', tariff_grid_id: '' })}
                        onSelect={({ address, lat, lng, pays }) => setData({ ...data, delivery_address: address, delivery_lat: lat, delivery_lng: lng, delivery_country: pays, tariff_grid_id: '' })}
                        error={(soumis && manque.delivery) || errors.delivery_address || errors.delivery_lat || errors.delivery_country}
                    />
                    <div>
                        <InputLabel htmlFor="weight">{t('commande.poids_kg', 'Poids (kg)')} <span className="text-status-incident">*</span></InputLabel>
                        <TextInput id="weight" type="number" step="0.01" min="0" value={data.weight} onChange={(e) => setData('weight', e.target.value)} placeholder={t('commande.poids_ex', 'ex. 300')} className="mt-1 block w-full" />
                        <InputError message={messagePoids || (soumis && manque.weight) || errors.weight} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="goods_type">{t('commande.marchandise', 'Type de marchandise')} <span className="text-status-incident">*</span></InputLabel>
                        <ListeRecherche
                            id="goods_type"
                            value={data.goods_type}
                            onChange={(type) => setData({ ...data, goods_type: type, is_hazardous: marchandisesAdr.includes(type) ? null : Boolean(data.is_hazardous) })}
                            placeholder={t('commande.choisir', '— Choisir —')}
                            options={MARCHANDISES.map((m) => ({ valeur: m, libelle: v('marchandise', m) }))}
                            className={selectCls}
                        />
                        <InputError message={(soumis && manque.goods) || errors.goods_type} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="volume">{t('commande.volume_m3', 'Volume (m³)')}</InputLabel>
                        <TextInput id="volume" type="number" step="0.1" min="0" value={data.volume} onChange={(e) => setData('volume', e.target.value)} placeholder={t('commande.volume_ex', 'facultatif, ex. 12')} className="mt-1 block w-full" />
                        <InputError message={messageVolume || errors.volume} className="mt-2" />
                    </div>
                    {aDeclarer ? (
                        <fieldset className="sm:col-span-2">
                            <legend className="text-sm font-medium text-slate-700">
                                {t('commande.adr_question', 'Cet envoi est-il soumis à l\'ADR (matière dangereuse) ?')} <span className="text-status-incident">*</span>
                            </legend>
                            <div className="mt-1 flex gap-6">
                                <label className="flex items-center gap-2 text-sm text-slate-600">
                                    <input type="radio" name="is_hazardous" checked={data.is_hazardous === true} onChange={() => setData('is_hazardous', true)} className="text-marine focus:ring-marine" />
                                    {t('commande.adr_oui', 'Oui, matière dangereuse (ADR)')}
                                </label>
                                <label className="flex items-center gap-2 text-sm text-slate-600">
                                    <input type="radio" name="is_hazardous" checked={data.is_hazardous === false} onChange={() => setData('is_hazardous', false)} className="text-marine focus:ring-marine" />
                                    {t('commande.adr_non', 'Non, non soumis à l\'ADR')}
                                </label>
                            </div>
                            <InputError message={(soumis && manque.adr) || errors.is_hazardous} className="mt-2" />
                        </fieldset>
                    ) : (
                        <label className="flex items-center gap-2 sm:col-span-2">
                            <Checkbox name="is_hazardous" checked={Boolean(data.is_hazardous)} onChange={(e) => setData('is_hazardous', e.target.checked)} />
                            <span className="text-sm text-slate-600">{t('devis.adr', 'Marchandise dangereuse (ADR)')}</span>
                        </label>
                    )}
                    <label className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox name="needs_tail_lift" checked={data.needs_tail_lift} onChange={(e) => setData('needs_tail_lift', e.target.checked)} />
                        <span className="text-sm text-slate-600">{t('commande.hayon_long', 'Hayon élévateur nécessaire (pas de quai au chargement ou à la livraison)')}</span>
                    </label>
                    {(refusFlotte || errors.flotte) && (
                        <InputError message={refusFlotte || errors.flotte} className="sm:col-span-2" />
                    )}
                    {etranger && (
                        // Le chauffeur charge chez un tiers qu'il ne connait pas :
                        // la lettre de voiture CMR (art. 6) nomme l'expediteur.
                        <fieldset className="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 p-4 sm:col-span-2 sm:grid-cols-3">
                            <legend className="px-1 text-sm font-medium text-slate-700">{t('commande.expediteur_bloc', 'Lieu de chargement à l\'étranger')}</legend>
                            <div>
                                <InputLabel htmlFor="shipper_name">{t('commande.expediteur', 'Expéditeur au lieu de chargement')} <span className="text-status-incident">*</span></InputLabel>
                                <TextInput id="shipper_name" value={data.shipper_name} maxLength={150} onChange={(e) => setData('shipper_name', e.target.value)} className="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel htmlFor="shipper_phone">{t('commande.expediteur_telephone', 'Téléphone du lieu de chargement')} <span className="text-status-incident">*</span></InputLabel>
                                <TextInput id="shipper_phone" type="tel" value={data.shipper_phone} maxLength={30} onChange={(e) => setData('shipper_phone', e.target.value)} className="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel htmlFor="loading_reference">{t('commande.reference_chargement', 'Référence de chargement')}</InputLabel>
                                <TextInput id="loading_reference" value={data.loading_reference} maxLength={60} onChange={(e) => setData('loading_reference', e.target.value)} className="mt-1 block w-full" />
                            </div>
                            <InputError message={(soumis && manque.expediteur) || errors.shipper_name || errors.shipper_phone} className="sm:col-span-3" />
                        </fieldset>
                    )}
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="priority">{t('commande.priorite', 'Priorité')} <span className="text-status-incident">*</span></InputLabel>
                        <select id="priority" value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={selectCls} disabled={urgence48h}>
                            <option value="LOW">{t('priorite.basse', 'Basse')}</option>
                            <option value="NORMAL">{t('priorite.normale', 'Normale')}</option>
                            <option value="HIGH">{t('priorite.haute', 'Haute')}</option>
                            <option value="URGENT">{t('priorite.urgente', 'Urgente')}</option>
                        </select>
                        {urgence48h && (
                            <p className="mt-1 text-xs text-slate-600">{t('commande.urgence48', 'Livraison souhaitée sous 48 h : priorité Urgente appliquée automatiquement.')}</p>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-5 sm:col-span-2">
                        <div>
                            <InputLabel htmlFor="pickup_date" value={t('commande.chargement_dh', 'Chargement (date et heure)')} />
                            <TextInput
                                id="pickup_date"
                                type="datetime-local"
                                min={minEnlevement}
                                value={data.pickup_date}
                                onChange={(e) => { const v = e.target.value; setData('pickup_date', v && v < minEnlevement ? minEnlevement : v); }}
                                className="mt-1 block w-full"
                            />
                            {etranger && premier && (
                                <p className="mt-1 text-xs text-slate-600">
                                    {t('commande.enlevement_etranger_aide', 'Heure locale. Au plus tôt le :date : route depuis Bruxelles et repos du chauffeur compris.', { date: dateHeure(premier.local) })}
                                    {premier.fuseau_different && ' ' + t('commande.heure_bruxelles', '(:heure à Bruxelles)', { heure: new Date(premier.bruxelles).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }) })}
                                </p>
                            )}
                            <InputError message={(soumis && manque.enlevement) || errors.pickup_date} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="requested_delivery_date" value={t('ordres.livraison_souhaitee', 'Livraison souhaitée')} />
                            <TextInput
                                id="requested_delivery_date"
                                type="date"
                                min={data.pickup_date ? data.pickup_date.slice(0, 10) : today}
                                value={data.requested_delivery_date}
                                onChange={(e) => { const minD = data.pickup_date ? data.pickup_date.slice(0, 10) : today; const v = e.target.value; setData('requested_delivery_date', v && v < minD ? minD : v); }}
                                className="mt-1 block w-full"
                            />
                            <InputError message={errors.requested_delivery_date} className="mt-2" />
                        </div>
                    </div>
                </div>

                <div>
                    <InputLabel>
                        {t('commande.formule', 'Formule de livraison')}
                        {zone ? ' — ' + nomRegion.of(paysDepart) + ' → ' + nomRegion.of(data.delivery_country) : ''}
                        {' '}<span className="text-status-incident">*</span>
                    </InputLabel>
                    {(surDevis || estim.erreur) ? (
                        <div className="mt-1 rounded-xl bg-action/10 p-4 text-sm text-action-dark" role="alert">
                            <p>{estim.erreur ?? t('msg.trajet_sur_devis', 'Ce trajet ne commence ni ne finit en Belgique : il se traite sur devis.')}</p>
                            <Link href={route('devis.create')} className="mt-2 inline-block font-semibold underline">{t('commande.demander_devis', 'Demander un devis')}</Link>
                        </div>
                    ) : zone ? (
                        <>
                        {estim.indisponible && (
                            <p className="mt-1 rounded-lg bg-status-incident/10 px-3 py-2 text-sm text-status-incident" role="alert">
                                {t('commande.estimation_indisponible', 'Le prix n\'a pas pu être calculé (service momentanément indisponible).')}{' '}
                                <button type="button" onClick={() => setRelance((n) => n + 1)} className="font-semibold underline">{t('commande.reessayer', 'Réessayer')}</button>
                            </p>
                        )}
                        <div className="mt-1 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            {offres.map((g) => {
                                const p = prixDe(g);
                                const actif = String(data.tariff_grid_id) === String(g.id);
                                const tropLent = delaiJours !== null && delai(g) > delaiJours;
                                const ligne = estim.prix_ligne?.[g.id] != null ? Number(estim.prix_ligne[g.id]) : null;
                                const remise = estim.tarif === 'BACKHAUL' && ligne != null && p != null && p < ligne;
                                return (
                                    <button
                                        type="button"
                                        key={g.id}
                                        onClick={() => setData('tariff_grid_id', String(g.id))}
                                        disabled={tropLent}
                                        aria-pressed={actif}
                                        className={`rounded-xl border p-4 text-left transition ${actif ? 'border-brand-blue bg-brand-blue/5 ring-1 ring-brand-blue' : tropLent ? 'border-gray-200 bg-gray-50 opacity-50' : 'border-gray-200 bg-white hover:border-gray-300'}`}
                                    >
                                        <p className="text-sm font-semibold text-marine">{NOMS_OFFRE[g.service_level] ? t(...NOMS_OFFRE[g.service_level]) : g.label}</p>
                                        <p className="text-xs text-gray-500">{t('tarifs.livre_en', 'livré en')} {delai(g)} {t('ordres.j', 'j')}</p>
                                        <p className="mt-2 text-lg font-bold text-action-dark">{p != null ? euros(p) : '—'}</p>
                                        {remise && (
                                            <p className="text-xs text-slate-500 line-through">{t('commande.au_lieu_de', 'au lieu de :montant', { montant: euros(ligne) })}</p>
                                        )}
                                        {tropLent && <p className="mt-1 text-xs text-gray-400">{t('commande.trop_lent', 'trop lent pour la date demandée')}</p>}
                                    </button>
                                );
                            })}
                        </div>
                        </>
                    ) : (
                        <p className="mt-1 text-sm text-slate-600">{t('commande.destination_dabord', 'Choisissez d\'abord les adresses de départ et de destination : la zone tarifaire est déduite du trajet.')}</p>
                    )}
                    {zone && estim.tarif === 'BACKHAUL' && (
                        // Jamais le camion ni sa position : un badge et un prix.
                        <p className="mt-2 rounded-lg bg-status-delivered/10 px-3 py-2 text-xs text-status-delivered">
                            <span className="font-semibold">{t('commande.tarif_fret_retour', 'Tarif fret retour')}</span>
                            {' — '}{t('commande.tarif_fret_retour_aide', 'Un de nos camions revient de cette région à cette date : jusqu\'à :remise % de remise, jamais sous le tarif national belge.', { remise: remiseFretRetour })}
                        </p>
                    )}
                    {zone && etranger && ! data.pickup_date && remiseFretRetour > 0 && (
                        <p className="mt-2 text-xs text-slate-600">{t('commande.date_pour_fret_retour', 'Indiquez la date d\'enlèvement : un tarif fret retour peut s\'appliquer.')}</p>
                    )}
                    {data.delivery_country && delaiJours !== null && !delaiTropCourt && (
                        <p className="mt-2 text-xs text-slate-600">{t('commande.formule_auto', 'Livraison demandée en :n j : la formule la moins chère qui tient ce délai est appliquée.', { n: delaiJours })}</p>
                    )}
                    {delaiTropCourt && (
                        <p className="mt-2 text-xs text-status-incident">{t('commande.delai_court', 'Délai demandé (:n j) trop court pour cette destination : notre meilleur délai est de :min j.', { n: delaiJours, min: delai(grilleAuto) })}</p>
                    )}
                    <InputError message={(soumis && manque.grille) || errors.tariff_grid_id} className="mt-2" />
                </div>

                {(data.pickup_lat && data.delivery_lat) && (
                    <div className="rounded-xl bg-surface p-4">
                        {loadingDist ? (
                            <p className="text-sm text-slate-600">{t('commande.calcul_distance', 'Calcul de la distance…')}</p>
                        ) : (
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-medium text-marine">
                                        {total != null
                                            ? t('commande.estimation_prix', 'Estimation du prix')
                                            : t('commande.distance_km', 'Distance : :km km', { km: kmTxt })}
                                    </p>
                                    {total != null ? (
                                        <p className="text-xs text-gray-500">
                                            {selectedGrid.service_level === 'EXPRESS' ? t('tarifs.dedie', 'Véhicule dédié') : t('tarifs.groupage', 'Groupage')} · {kmTxt} km · {data.weight} kg{data.is_hazardous ? ' · ADR' : ''} · {t('tarifs.livre_en', 'livré en')} {delai(selectedGrid)} {t('ordres.j', 'j')}
                                        </p>
                                    ) : (
                                        <p className={'text-xs ' + (messagePoids || messageVolume || refusFlotte ? 'text-status-incident' : 'text-gray-500')}>
                                            {messagePoids || messageVolume || refusFlotte || t('commande.poids_pour_prix', 'Indiquez le poids pour voir les prix groupage.')}
                                        </p>
                                    )}
                                </div>
                                {total != null && <p className="text-3xl font-bold text-action-dark">{fr(total)} €</p>}
                            </div>
                        )}
                    </div>
                )}

                <div>
                    <InputLabel htmlFor="special_instructions" value={t('commande.instructions', 'Instructions particulières')} />
                    <textarea id="special_instructions" value={data.special_instructions} onChange={(e) => setData('special_instructions', e.target.value)} rows="3" placeholder={t('commande.instructions_ex', 'ex. Livraison sur rendez-vous, hayon nécessaire, sonner au quai B…')} className={selectCls} />
                </div>

                <PrimaryButton disabled={processing}>{t('commande.creer', 'Créer l\'expédition')}</PrimaryButton>
            </form>
        </AuthenticatedLayout>
    );
}
