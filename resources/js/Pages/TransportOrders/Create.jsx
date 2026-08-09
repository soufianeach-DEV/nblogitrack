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

const MARCHANDISES = ['Alimentaire', 'Frigorifique', 'Textile', 'Électronique', 'Matériaux de construction', 'Chimie', 'Automobile', 'Autre'];

export default function Create({ tariffGrids, pricing }) {
    const { data, setData, post, processing, errors } = useForm({
        pickup_address: '', delivery_address: '', delivery_country: '',
        pickup_lat: '', pickup_lng: '', delivery_lat: '', delivery_lng: '',
        weight: '', goods_type: '', is_hazardous: false, priority: 'NORMAL',
        pickup_date: '', requested_delivery_date: '',
        tariff_grid_id: '', special_instructions: '',
    });

    const [distance, setDistance] = useState(null);
    const [loadingDist, setLoadingDist] = useState(false);
    const today = new Date().toISOString().split('T')[0];

    useEffect(() => {
        if (!data.pickup_lat || !data.delivery_lat) { setDistance(null); return; }
        const fallback = () => Math.round(haversineKm(Number(data.pickup_lat), Number(data.pickup_lng), Number(data.delivery_lat), Number(data.delivery_lng)));
        setLoadingDist(true);
        fetch(`https://router.project-osrm.org/route/v1/driving/${data.pickup_lng},${data.pickup_lat};${data.delivery_lng},${data.delivery_lat}?overview=false`)
            .then((r) => r.json())
            .then((j) => setDistance(j.routes?.[0]?.distance ? Math.round(j.routes[0].distance / 1000) : fallback()))
            .catch(() => setDistance(fallback()))
            .finally(() => setLoadingDist(false));
    }, [data.pickup_lat, data.pickup_lng, data.delivery_lat, data.delivery_lng]);

    const submit = (e) => { e.preventDefault(); post(route('transport-orders.store')); };
    const selectCls = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-marine focus:ring-marine';

    const grillesVisibles = data.delivery_country
        ? tariffGrids.filter((g) => g.zone === data.delivery_country)
        : [];

    const selectedGrid = tariffGrids.find((g) => String(g.id) === String(data.tariff_grid_id));

    const detail = (() => {
        if (!selectedGrid || distance == null) return null;
        const adr = data.is_hazardous ? Number(selectedGrid.adr_coefficient) : 1;
        if (Number(selectedGrid.delivery_days) === 1) {
            const carburant = distance * pricing.consumption_l_per_100km / 100 * pricing.diesel_price;
            const peages = distance * (pricing.toll_per_km[data.delivery_country] ?? 0);
            const chauffeur = distance * pricing.driver_cost_per_km;
            const vehicule = distance * pricing.vehicle_cost_per_km;
            const total = (Number(selectedGrid.base_rate) + carburant + peages + chauffeur + vehicule) * (1 + pricing.margin) * adr;
            return { total, carburant, peages, chauffeur, vehicule };
        }
        if (!data.weight) return null;
        const total = (Number(selectedGrid.base_rate) + Number(selectedGrid.price_per_kg) * Number(data.weight) + Number(selectedGrid.price_per_km) * distance) * adr;
        return { total };
    })();

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">Nouvelle expédition</h1>}>
            <Head title="Nouvelle expédition" />

            <form onSubmit={submit} className="max-w-2xl space-y-5 rounded-2xl bg-white p-8 shadow-sm">
                <div className="grid gap-5 sm:grid-cols-2">
                    <AdresseAutocompletion
                        label="Adresse de départ"
                        onChange={(v) => setData({ ...data, pickup_address: v, pickup_lat: '', pickup_lng: '' })}
                        onSelect={({ address, lat, lng }) => setData({ ...data, pickup_address: address, pickup_lat: lat, pickup_lng: lng })}
                        error={errors.pickup_address || errors.pickup_lat}
                    />
                    <AdresseAutocompletion
                        label="Adresse de destination"
                        onChange={(v) => setData({ ...data, delivery_address: v, delivery_lat: '', delivery_lng: '', delivery_country: '', tariff_grid_id: '' })}
                        onSelect={({ address, lat, lng, pays }) => setData({ ...data, delivery_address: address, delivery_lat: lat, delivery_lng: lng, delivery_country: pays, tariff_grid_id: '' })}
                        error={errors.delivery_address || errors.delivery_lat}
                    />
                    <div>
                        <InputLabel htmlFor="weight" value="Poids (kg)" />
                        <TextInput id="weight" type="number" step="0.01" min="0" value={data.weight} onChange={(e) => setData('weight', e.target.value)} className="mt-1 block w-full" required />
                        <InputError message={errors.weight} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="goods_type" value="Type de marchandise" />
                        <select id="goods_type" value={data.goods_type} onChange={(e) => setData('goods_type', e.target.value)} className={selectCls} required>
                            <option value="">— Choisir —</option>
                            {MARCHANDISES.map((m) => (
                                <option key={m} value={m}>{m}</option>
                            ))}
                        </select>
                        <InputError message={errors.goods_type} className="mt-2" />
                    </div>
                    <label className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox name="is_hazardous" checked={data.is_hazardous} onChange={(e) => setData('is_hazardous', e.target.checked)} />
                        <span className="text-sm text-slate-600">Marchandise dangereuse (ADR)</span>
                    </label>
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="priority" value="Priorité" />
                        <select id="priority" value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={selectCls}>
                            <option value="LOW">Basse</option>
                            <option value="NORMAL">Normale</option>
                            <option value="HIGH">Haute</option>
                            <option value="URGENT">Urgente</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="pickup_date" value="Jour de chargement" />
                        <TextInput id="pickup_date" type="date" min={today} value={data.pickup_date} onChange={(e) => setData('pickup_date', e.target.value)} className="mt-1 block w-full" />
                        <InputError message={errors.pickup_date} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="requested_delivery_date" value="Livraison souhaitée" />
                        <TextInput id="requested_delivery_date" type="date" min={data.pickup_date || today} value={data.requested_delivery_date} onChange={(e) => setData('requested_delivery_date', e.target.value)} className="mt-1 block w-full" />
                        <InputError message={errors.requested_delivery_date} className="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="tariff_grid_id" value="Grille tarifaire" />
                    <select id="tariff_grid_id" value={data.tariff_grid_id} onChange={(e) => setData('tariff_grid_id', e.target.value)} className={selectCls} disabled={!data.delivery_country} required>
                        <option value="">{data.delivery_country ? '— Choisir —' : "— Choisis d'abord la destination —"}</option>
                        {grillesVisibles.map((g) => (
                            <option key={g.id} value={g.id}>{g.label} — livré en {g.delivery_days} j</option>
                        ))}
                    </select>
                    <InputError message={errors.tariff_grid_id} className="mt-2" />
                </div>

                {(data.pickup_lat && data.delivery_lat) && (
                    <div className="rounded-xl bg-surface p-4">
                        {loadingDist ? (
                            <p className="text-sm text-slate-500">Calcul de la distance…</p>
                        ) : (
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-medium text-marine">
                                        {detail ? 'Estimation du prix' : `Distance : ${distance} km`}
                                    </p>
                                    {detail ? (
                                        detail.carburant != null ? (
                                            <p className="text-xs text-gray-500">
                                                Véhicule dédié · {distance} km — Carburant {detail.carburant.toFixed(0)} € + Péages {detail.peages.toFixed(0)} € + Chauffeur {detail.chauffeur.toFixed(0)} € + Véhicule {detail.vehicule.toFixed(0)} € + Frais fixes {Number(selectedGrid.base_rate).toFixed(0)} € + Marge {Math.round(pricing.margin * 100)} %{data.is_hazardous ? ` × ADR ${Number(selectedGrid.adr_coefficient).toFixed(2)}` : ''} · livré en 1 j
                                            </p>
                                        ) : (
                                            <p className="text-xs text-gray-500">
                                                Base {Number(selectedGrid.base_rate).toFixed(2)} € + {Number(selectedGrid.price_per_kg).toFixed(3)} €/kg × {data.weight} kg + {Number(selectedGrid.price_per_km).toFixed(2)} €/km × {distance} km{data.is_hazardous ? ` × ADR ${Number(selectedGrid.adr_coefficient).toFixed(2)}` : ''} · livré en {selectedGrid.delivery_days} j
                                            </p>
                                        )
                                    ) : (
                                        <p className="text-xs text-gray-500">Choisis une grille et un poids pour l'estimation.</p>
                                    )}
                                </div>
                                {detail && <p className="text-3xl font-bold text-action-dark">{detail.total.toFixed(2)} €</p>}
                            </div>
                        )}
                    </div>
                )}

                <div>
                    <InputLabel htmlFor="special_instructions" value="Instructions particulières" />
                    <textarea id="special_instructions" value={data.special_instructions} onChange={(e) => setData('special_instructions', e.target.value)} rows="3" className={selectCls} />
                </div>

                <PrimaryButton disabled={processing}>Créer l'expédition</PrimaryButton>
            </form>
        </AuthenticatedLayout>
    );
}
