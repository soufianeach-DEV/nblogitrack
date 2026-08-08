import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ tariffGrids }) {
    const { data, setData, post, processing, errors } = useForm({
        pickup_address: '', delivery_address: '', weight: '', goods_type: '',
        priority: 'NORMAL', requested_delivery_date: '', tariff_grid_id: '', special_instructions: '',
    });

    const submit = (e) => { e.preventDefault(); post(route('transport-orders.store')); };
    const selectCls = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-marine focus:ring-marine';
     const selectedGrid = tariffGrids.find((g) => String(g.id) === String(data.tariff_grid_id));
    const estimate = selectedGrid && data.weight
        ? Number(selectedGrid.base_rate) + Number(selectedGrid.price_per_kg) * Number(data.weight)
        : null;

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-bold text-marine">Nouvelle expédition</h1>}>
            <Head title="Nouvelle expédition" />

            <form onSubmit={submit} className="max-w-2xl space-y-5 rounded-2xl bg-white p-8 shadow-sm">
                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="pickup_address" value="Adresse de départ" />
                        <TextInput id="pickup_address" value={data.pickup_address} onChange={(e) => setData('pickup_address', e.target.value)} className="mt-1 block w-full" required />
                        <InputError message={errors.pickup_address} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="delivery_address" value="Adresse de destination" />
                        <TextInput id="delivery_address" value={data.delivery_address} onChange={(e) => setData('delivery_address', e.target.value)} className="mt-1 block w-full" required />
                        <InputError message={errors.delivery_address} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="weight" value="Poids (kg)" />
                        <TextInput id="weight" type="number" step="0.01" value={data.weight} onChange={(e) => setData('weight', e.target.value)} className="mt-1 block w-full" required />
                        <InputError message={errors.weight} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="goods_type" value="Type de marchandise" />
                        <TextInput id="goods_type" value={data.goods_type} onChange={(e) => setData('goods_type', e.target.value)} className="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel htmlFor="priority" value="Priorité" />
                        <select id="priority" value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={selectCls}>
                            <option value="LOW">Basse</option>
                            <option value="NORMAL">Normale</option>
                            <option value="HIGH">Haute</option>
                            <option value="URGENT">Urgente</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="requested_delivery_date" value="Livraison souhaitée" />
                        <TextInput id="requested_delivery_date" type="date" value={data.requested_delivery_date} onChange={(e) => setData('requested_delivery_date', e.target.value)} className="mt-1 block w-full" />
                        <InputError message={errors.requested_delivery_date} className="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="tariff_grid_id" value="Grille tarifaire" />
                    <select id="tariff_grid_id" value={data.tariff_grid_id} onChange={(e) => setData('tariff_grid_id', e.target.value)} className={selectCls} required>
                        <option value="">— Choisir —</option>
                        {tariffGrids.map((g) => (
                            <option key={g.id} value={g.id}>{g.label} ({g.zone})</option>
                        ))}
                    </select>
                    <InputError message={errors.tariff_grid_id} className="mt-2" />
                </div>

                
                {estimate !== null && (
                    <div className="flex items-center justify-between rounded-xl bg-surface p-4">
                        <div>
                            <p className="text-sm font-medium text-marine">Estimation du prix</p>
                            <p className="text-xs text-gray-500">
                                Base {Number(selectedGrid.base_rate).toFixed(2)} € + {Number(selectedGrid.price_per_kg).toFixed(4)} €/kg × {data.weight || 0} kg
                            </p>
                        </div>
                        <p className="text-3xl font-bold text-action-dark">{estimate.toFixed(2)} €</p>
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