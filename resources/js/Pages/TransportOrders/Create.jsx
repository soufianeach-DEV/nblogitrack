import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AdresseAutocompletion from '@/Components/AdresseAutocompletion';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const haversineKm = (lat1, lng1, lat2, lng2) => {
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1), dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return 6371 * 2 * Math.asin(Math.sqrt(a)) * 1.3;
};

// Meme vocabulaire que l'historique des expeditions, defini une seule fois
// dans le modele TransportOrder.
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

const NOMS_OFFRE = { ECO: 'Éco', STANDARD: 'Standard', EXPRESS: 'Express' };

const ORDRE_OFFRE = { ECO: 0, STANDARD: 1, EXPRESS: 2 };

const nomRegion = new Intl.DisplayNames(['fr'], { type: 'region' });

export default function Create({ tariffGrids, pricing }) {
    const { data, setData, post, processing, errors } = useForm({
        pickup_address: '', delivery_address: '', delivery_country: '',
        pickup_lat: '', pickup_lng: '', delivery_lat: '', delivery_lng: '',
        weight: '', goods_type: '', is_hazardous: false, needs_tail_lift: false, priority: 'NORMAL',
        pickup_date: '', requested_delivery_date: '',
        tariff_grid_id: '', special_instructions: '',
    });

    const [distance, setDistance] = useState(null);
    const [loadingDist, setLoadingDist] = useState(false);

    const pad = (n) => String(n).padStart(2, '0');
    const maintenant = new Date();
    const today = `${maintenant.getFullYear()}-${pad(maintenant.getMonth() + 1)}-${pad(maintenant.getDate())}`;
    const nowLocal = `${today}T${pad(maintenant.getHours())}:${pad(maintenant.getMinutes())}`;

    useEffect(() => {
        if (!data.pickup_lat || !data.delivery_lat) { setDistance(null); return; }
        const fallback = () => haversineKm(Number(data.pickup_lat), Number(data.pickup_lng), Number(data.delivery_lat), Number(data.delivery_lng));
        setLoadingDist(true);
        fetch(`https://router.project-osrm.org/route/v1/driving/${data.pickup_lng},${data.pickup_lat};${data.delivery_lng},${data.delivery_lat}?overview=false`)
            .then((r) => r.json())
            .then((j) => setDistance(j.routes?.[0]?.distance ? j.routes[0].distance / 1000 : fallback()))
            .catch(() => setDistance(fallback()))
            .finally(() => setLoadingDist(false));
    }, [data.pickup_lat, data.pickup_lng, data.delivery_lat, data.delivery_lng]);

    const fr = (n, dec = 2) => Number(n).toLocaleString('fr-FR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const kmTxt = distance != null ? distance.toLocaleString('fr-FR', { maximumFractionDigits: 1 }) : '';

    const grillesVisibles = data.delivery_country
        ? tariffGrids.filter((g) => g.zone === data.delivery_country)
        : [];

    const offres = [...grillesVisibles].sort((a, b) => (ORDRE_OFFRE[a.service_level] ?? 9) - (ORDRE_OFFRE[b.service_level] ?? 9));

    const jourSeul = (valeur) => {
        const d = new Date(valeur);
        d.setHours(0, 0, 0, 0);
        return d;
    };

    const delaiJours = data.requested_delivery_date
        ? Math.round((jourSeul(data.requested_delivery_date) - jourSeul(data.pickup_date ? data.pickup_date.slice(0, 10) : today)) / 86400000)
        : null;

    const formuleAdaptee = () => {
        if (grillesVisibles.length === 0) return null;
        const parDelai = [...grillesVisibles].sort((a, b) => Number(b.delivery_days) - Number(a.delivery_days));
        if (delaiJours === null) {
            return parDelai.find((g) => g.service_level === 'STANDARD') || parDelai[0];
        }
        return parDelai.find((g) => Number(g.delivery_days) <= delaiJours) || parDelai[parDelai.length - 1];
    };

    const grilleAuto = formuleAdaptee();
    const delaiTropCourt = delaiJours !== null && grilleAuto && Number(grilleAuto.delivery_days) > delaiJours;

    useEffect(() => {
        if (!data.delivery_country || !grilleAuto) return;
        if (String(data.tariff_grid_id) !== String(grilleAuto.id)) {
            setData('tariff_grid_id', String(grilleAuto.id));
        }
    }, [data.delivery_country, delaiJours, grillesVisibles.length]);

    const urgence48h = delaiJours !== null && delaiJours <= 2;

    useEffect(() => {
        if (urgence48h && data.priority !== 'URGENT') setData('priority', 'URGENT');
    }, [urgence48h]);

    const [soumis, setSoumis] = useState(false);

    const manque = {
        pickup: !data.pickup_lat ? "Complète l'adresse de départ : pays, ville, code postal, rue et numéro." : null,
        delivery: !data.delivery_lat ? "Complète l'adresse de destination : pays, ville, code postal, rue et numéro." : null,
        weight: !data.weight ? 'Indique le poids de la marchandise.' : null,
        goods: !data.goods_type ? 'Choisis le type de marchandise.' : null,
        grille: !data.tariff_grid_id ? 'Choisis une formule de livraison.' : null,
        delai: delaiTropCourt ? `Aucune formule ne tient ce délai : compte au moins ${grilleAuto.delivery_days} jours vers cette destination.` : null,
    };

    const submit = (e) => {
        e.preventDefault();
        setSoumis(true);
        if (Object.values(manque).some(Boolean)) return;
        post(route('transport-orders.store'));
    };
    const selectCls = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-marine focus:ring-marine';

    const prixDetail = (g) => {
        if (!g || distance == null || !data.delivery_country) return null;
        const adr = data.is_hazardous ? Number(g.adr_coefficient) : 1;
        if (g.service_level === 'EXPRESS') {
            const carburant = distance * pricing.consumption_l_per_100km / 100 * pricing.diesel_price;
            const peages = distance * (pricing.toll_per_km[data.delivery_country] ?? 0);
            const chauffeur = distance * pricing.driver_cost_per_km;
            const vehicule = distance * pricing.vehicle_cost_per_km;
            const total = (Number(g.base_rate) + carburant + peages + chauffeur + vehicule) * (1 + pricing.margin) * adr;
            return { total, carburant, peages, chauffeur, vehicule };
        }
        if (!data.weight) return null;
        const total = (Number(g.base_rate) + Number(g.price_per_kg) * Number(data.weight) + Number(g.price_per_km) * distance) * adr;
        return { total };
    };

    const selectedGrid = tariffGrids.find((g) => String(g.id) === String(data.tariff_grid_id));
    const detail = prixDetail(selectedGrid);

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">Nouvelle expédition</h1>}>
            <Head title="Nouvelle expédition" />

            <form onSubmit={submit} className="w-full space-y-5 rounded-2xl bg-white p-8 shadow-sm">
                <div className="grid gap-5 sm:grid-cols-2">
                    <AdresseAutocompletion
                        label="Adresse de départ"
                        required
                        onChange={(v) => setData({ ...data, pickup_address: v, pickup_lat: '', pickup_lng: '' })}
                        onSelect={({ address, lat, lng }) => setData({ ...data, pickup_address: address, pickup_lat: lat, pickup_lng: lng })}
                        error={(soumis && manque.pickup) || errors.pickup_address || errors.pickup_lat}
                    />
                    <AdresseAutocompletion
                        label="Adresse de destination"
                        required
                        onChange={(v) => setData({ ...data, delivery_address: v, delivery_lat: '', delivery_lng: '', delivery_country: '', tariff_grid_id: '' })}
                        onSelect={({ address, lat, lng, pays }) => setData({ ...data, delivery_address: address, delivery_lat: lat, delivery_lng: lng, delivery_country: pays, tariff_grid_id: '' })}
                        error={(soumis && manque.delivery) || errors.delivery_address || errors.delivery_lat || errors.delivery_country}
                    />
                    <div>
                        <InputLabel htmlFor="weight">Poids (kg) <span className="text-status-incident">*</span></InputLabel>
                        <TextInput id="weight" type="number" step="0.01" min="0" value={data.weight} onChange={(e) => setData('weight', e.target.value)} placeholder="ex. 300" className="mt-1 block w-full" />
                        <InputError message={(soumis && manque.weight) || errors.weight} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="goods_type">Type de marchandise <span className="text-status-incident">*</span></InputLabel>
                        <select id="goods_type" value={data.goods_type} onChange={(e) => setData('goods_type', e.target.value)} className={selectCls}>
                            <option value="">— Choisir —</option>
                            {MARCHANDISES.map((m) => (
                                <option key={m} value={m}>{m}</option>
                            ))}
                        </select>
                        <InputError message={(soumis && manque.goods) || errors.goods_type} className="mt-2" />
                    </div>
                    <label className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox name="is_hazardous" checked={data.is_hazardous} onChange={(e) => setData('is_hazardous', e.target.checked)} />
                        <span className="text-sm text-slate-600">Marchandise dangereuse (ADR)</span>
                    </label>
                    <label className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox name="needs_tail_lift" checked={data.needs_tail_lift} onChange={(e) => setData('needs_tail_lift', e.target.checked)} />
                        <span className="text-sm text-slate-600">Hayon élévateur nécessaire (pas de quai au chargement ou à la livraison)</span>
                    </label>
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="priority">Priorité <span className="text-status-incident">*</span></InputLabel>
                        <select id="priority" value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={selectCls} disabled={urgence48h}>
                            <option value="LOW">Basse</option>
                            <option value="NORMAL">Normale</option>
                            <option value="HIGH">Haute</option>
                            <option value="URGENT">Urgente</option>
                        </select>
                        {urgence48h && (
                            <p className="mt-1 text-xs text-slate-600">Livraison souhaitée sous 48 h : priorité Urgente appliquée automatiquement.</p>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-5 sm:col-span-2">
                        <div>
                            <InputLabel htmlFor="pickup_date" value="Chargement (date et heure)" />
                            <TextInput
                                id="pickup_date"
                                type="datetime-local"
                                min={nowLocal}
                                value={data.pickup_date}
                                onChange={(e) => { const v = e.target.value; setData('pickup_date', v && v < nowLocal ? nowLocal : v); }}
                                className="mt-1 block w-full"
                            />
                            <InputError message={errors.pickup_date} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="requested_delivery_date" value="Livraison souhaitée" />
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
                    <InputLabel>{data.delivery_country ? `Formule de livraison — ${nomRegion.of(data.delivery_country)}` : 'Formule de livraison'} <span className="text-status-incident">*</span></InputLabel>
                    {data.delivery_country ? (
                        <div className="mt-1 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            {offres.map((g) => {
                                const p = prixDetail(g);
                                const actif = String(data.tariff_grid_id) === String(g.id);
                                const tropLent = delaiJours !== null && Number(g.delivery_days) > delaiJours;
                                return (
                                    <button
                                        type="button"
                                        key={g.id}
                                        onClick={() => setData('tariff_grid_id', String(g.id))}
                                        disabled={tropLent}
                                        aria-pressed={actif}
                                        className={`rounded-xl border p-4 text-left transition ${actif ? 'border-brand-blue bg-brand-blue/5 ring-1 ring-brand-blue' : tropLent ? 'border-gray-200 bg-gray-50 opacity-50' : 'border-gray-200 bg-white hover:border-gray-300'}`}
                                    >
                                        <p className="text-sm font-semibold text-marine">{NOMS_OFFRE[g.service_level] ?? g.label}</p>
                                        <p className="text-xs text-gray-500">livré en {g.delivery_days} j</p>
                                        <p className="mt-2 text-lg font-bold text-action-dark">{p ? `${fr(p.total)} €` : '—'}</p>
                                        {tropLent && <p className="mt-1 text-xs text-gray-400">trop lent pour la date demandée</p>}
                                    </button>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="mt-1 text-sm text-slate-600">Choisis d'abord l'adresse de destination : la zone tarifaire est déduite automatiquement.</p>
                    )}
                    {data.delivery_country && delaiJours !== null && !delaiTropCourt && (
                        <p className="mt-2 text-xs text-slate-600">Livraison demandée en {delaiJours} j : la formule la moins chère qui tient ce délai est appliquée.</p>
                    )}
                    {delaiTropCourt && (
                        <p className="mt-2 text-xs text-status-incident">Délai demandé ({delaiJours} j) trop court pour cette destination : notre meilleur délai est de {grilleAuto.delivery_days} j.</p>
                    )}
                    <InputError message={(soumis && manque.grille) || errors.tariff_grid_id} className="mt-2" />
                </div>

                {(data.pickup_lat && data.delivery_lat) && (
                    <div className="rounded-xl bg-surface p-4">
                        {loadingDist ? (
                            <p className="text-sm text-slate-600">Calcul de la distance…</p>
                        ) : (
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-medium text-marine">
                                        {detail ? 'Estimation du prix' : `Distance : ${kmTxt} km`}
                                    </p>
                                    {detail ? (
                                        detail.carburant != null ? (
                                            <p className="text-xs text-gray-500">
                                                Véhicule dédié · {kmTxt} km — Carburant {fr(detail.carburant, 0)} € + Péages {fr(detail.peages, 0)} € + Chauffeur {fr(detail.chauffeur, 0)} € + Véhicule {fr(detail.vehicule, 0)} € + Frais fixes {fr(selectedGrid.base_rate, 0)} € + Marge {Math.round(pricing.margin * 100)} %{data.is_hazardous ? ` × ADR ${fr(selectedGrid.adr_coefficient)}` : ''} · livré en {selectedGrid.delivery_days} j
                                            </p>
                                        ) : (
                                            <p className="text-xs text-gray-500">
                                                Base {fr(selectedGrid.base_rate)} € + {fr(selectedGrid.price_per_kg, 3)} €/kg × {data.weight} kg + {fr(selectedGrid.price_per_km)} €/km × {kmTxt} km{data.is_hazardous ? ` × ADR ${fr(selectedGrid.adr_coefficient)}` : ''} · livré en {selectedGrid.delivery_days} j
                                            </p>
                                        )
                                    ) : (
                                        <p className="text-xs text-gray-500">Indique le poids pour voir les prix groupage.</p>
                                    )}
                                </div>
                                {detail && <p className="text-3xl font-bold text-action-dark">{fr(detail.total)} €</p>}
                            </div>
                        )}
                    </div>
                )}

                <div>
                    <InputLabel htmlFor="special_instructions" value="Instructions particulières" />
                    <textarea id="special_instructions" value={data.special_instructions} onChange={(e) => setData('special_instructions', e.target.value)} rows="3" placeholder="ex. Livraison sur rendez-vous, hayon nécessaire, sonner au quai B…" className={selectCls} />
                </div>

                <PrimaryButton disabled={processing}>Créer l'expédition</PrimaryButton>
            </form>
        </AuthenticatedLayout>
    );
}
