import BarreFiltres from '@/Components/BarreFiltres';
import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

const nombre = (valeur, unite, decimales = 0) => Number(valeur).toLocaleString('fr-FR', {
    minimumFractionDigits: decimales,
    maximumFractionDigits: decimales,
}) + ' ' + unite;

function Fiche({ vehicule, peutModifier, onFermer }) {
    const { data, setData, patch, processing, errors } = useForm({
        is_available: vehicule.disponible,
        inspection_date: vehicule.controle ?? '',
        inspection_valid_until: vehicule.controle_valide ?? '',
        mileage: vehicule.kilometrage,
    });

    // Un nouveau passage au controle propose une validite d'un an, la regle
    // generale ; elle reste modifiable pour les categories qui derogent.
    const passageControle = (valeur) => {
        const suggestion = valeur ? `${Number(valeur.slice(0, 4)) + 1}${valeur.slice(4)}` : '';
        setData((precedent) => ({
            ...precedent,
            inspection_date: valeur,
            inspection_valid_until: suggestion,
        }));
    };

    const enregistrer = (e) => {
        e.preventDefault();
        patch(route('vehicles.update', vehicule.immatriculation), {
            preserveScroll: true,
            onSuccess: onFermer,
        });
    };

    return (
        <form onSubmit={enregistrer} className="p-6">
            <p className="font-mono text-lg font-bold text-marine">{vehicule.immatriculation}</p>
            <p className="text-sm text-slate-600">{vehicule.marque}</p>

            <dl className="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 text-sm">
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Carrosserie</dt><dd className="font-semibold text-marine">{vehicule.type}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Norme</dt><dd className="font-semibold text-marine">{vehicule.norme} · {vehicule.carburant}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Charge utile</dt><dd className="font-semibold text-marine">{nombre(vehicule.capacite, 't', 1)}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Volume</dt><dd className="font-semibold text-marine">{nombre(vehicule.volume, 'm³', 0)}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Hayon élévateur</dt><dd className="font-semibold text-marine">{vehicule.hayon ? 'Oui' : 'Non'}</dd></div>
                <div className="col-span-2"><dt className="text-xs uppercase tracking-wide text-slate-600">Numéro de châssis</dt><dd className="font-mono text-xs text-marine">{vehicule.vin}</dd></div>
            </dl>

            {! peutModifier ? (
                <p className="mt-5 rounded-lg bg-surface px-3 py-2 text-sm text-slate-600">
                    Consultation seule — la modification du parc est réservée à l'administrateur.
                </p>
            ) : (
                <div className="mt-5 space-y-4 border-t border-slate-100 pt-4">
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.is_available}
                            onChange={(e) => setData('is_available', e.target.checked)}
                            className="rounded border-slate-300 text-marine focus:ring-marine"
                        />
                        <span className="text-sm font-semibold text-marine">Disponible pour affectation</span>
                    </label>

                    {vehicule.engage && (
                        <p className="rounded-lg bg-action/10 px-3 py-2 text-xs text-action-dark">
                            Ce véhicule porte une expédition en cours : il ne peut pas être retiré du service.
                        </p>
                    )}
                    {errors.is_available && <p className="text-xs text-status-incident">{errors.is_available}</p>}

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label htmlFor="controle" className="text-xs uppercase tracking-wide text-slate-600">
                                Contrôle passé le
                            </label>
                            <input
                                id="controle"
                                type="date"
                                value={data.inspection_date ?? ''}
                                onChange={(e) => passageControle(e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            {errors.inspection_date && <p className="mt-1 text-xs text-status-incident">{errors.inspection_date}</p>}
                        </div>
                        <div>
                            <label htmlFor="controle-validite" className="text-xs uppercase tracking-wide text-slate-600">
                                Valable jusqu'au
                            </label>
                            <input
                                id="controle-validite"
                                type="date"
                                value={data.inspection_valid_until ?? ''}
                                onChange={(e) => setData('inspection_valid_until', e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            {errors.inspection_valid_until && <p className="mt-1 text-xs text-status-incident">{errors.inspection_valid_until}</p>}
                        </div>
                    </div>

                    <div>
                        <label htmlFor="km" className="text-xs uppercase tracking-wide text-slate-600">
                            Kilométrage relevé
                        </label>
                        <input
                            id="km"
                            type="number"
                            min="0"
                            value={data.mileage ?? ''}
                            onChange={(e) => setData('mileage', e.target.value)}
                            className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                        {errors.mileage && <p className="mt-1 text-xs text-status-incident">{errors.mileage}</p>}
                    </div>
                </div>
            )}

            <div className="mt-6 flex justify-end gap-3">
                <button type="button" onClick={onFermer} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:text-marine">
                    Fermer
                </button>
                {peutModifier && (
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-marine px-5 py-2 text-sm font-bold text-white transition hover:bg-marine-deep disabled:opacity-50"
                    >
                        {processing ? 'Enregistrement…' : 'Enregistrer'}
                    </button>
                )}
            </div>
        </form>
    );
}

const CHARGES = [
    { valeur: '1', libelle: 'Porte au moins 1 t' },
    { valeur: '3', libelle: 'Porte au moins 3 t' },
    { valeur: '6', libelle: 'Porte au moins 6 t' },
    { valeur: '12', libelle: 'Porte au moins 12 t' },
    { valeur: '24', libelle: 'Porte au moins 24 t' },
];

export default function Vehicules({ vehicules = [], types = [], normes = [], compteurs, filtres = {}, peutModifier = false }) {
    const [ouvert, setOuvert] = useState(null);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">Parc de véhicules</h1>
                    <p className="text-sm text-slate-600">
                        {compteurs.total} véhicules, {compteurs.disponibles} disponibles
                    </p>
                </div>
            }
        >
            <Head title="Parc de véhicules" />

            <BarreFiltres
                adresse={route('vehicles.index')}
                filtres={filtres}
                placeholder="Immatriculation, marque, châssis…"
                listes={[
                    { champ: 'type', intitule: 'Toutes les carrosseries', options: types },
                    { champ: 'charge', intitule: 'Toutes les charges', options: CHARGES },
                    { champ: 'norme', intitule: 'Toutes les normes', options: normes },
                    { champ: 'hayon', intitule: 'Hayon indifférent', options: [{ valeur: '1', libelle: 'Avec hayon' }] },
                ]}
                compteurs={[
                    { libelle: 'Tous', valeur: null, nombre: compteurs.total },
                    { libelle: 'Disponibles', valeur: 'disponibles', nombre: compteurs.disponibles },
                    { libelle: 'Hors service', valeur: 'indisponibles', nombre: compteurs.total - compteurs.disponibles },
                    { libelle: 'Contrôle dépassé', valeur: 'controle', nombre: compteurs.controle, alerte: true },
                ]}
            />

            <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Immatriculation</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Véhicule</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Carrosserie</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">Charge</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">Kilométrage</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Contrôle</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">État</th>
                            </tr>
                        </thead>
                        <tbody>
                            {vehicules.map((v) => (
                                <tr
                                    key={v.immatriculation}
                                    onClick={() => setOuvert(v)}
                                    className="cursor-pointer border-b border-slate-50 transition last:border-0 hover:bg-surface"
                                >
                                    <td className="whitespace-nowrap px-4 py-3 font-mono font-semibold text-marine">{v.immatriculation}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-700">{v.marque}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">
                                        {v.type}
                                        <span className="ml-2 text-xs text-slate-600">{v.norme}</span>
                                        {v.hayon && (
                                            <span className="ml-2 rounded bg-brand-blue/10 px-1.5 py-0.5 text-[11px] font-semibold text-brand-blue">
                                                Hayon
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{nombre(v.capacite, 't', 1)}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{nombre(v.kilometrage, 'km')}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className={v.controle_depasse ? 'font-semibold text-status-incident' : 'text-slate-600'}>
                                            {v.controle_valide_affiche ? `valable → ${v.controle_valide_affiche}` : '—'}
                                        </span>
                                        {v.controle_affiche && (
                                            <span className="block text-xs text-slate-600">passé le {v.controle_affiche}</span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                                            v.engage ? 'bg-brand-blue/10 text-brand-blue'
                                                : v.disponible ? 'bg-status-delivered/10 text-status-delivered'
                                                    : 'bg-slate-100 text-slate-700'
                                        }`}>
                                            {v.engage ? 'En mission' : v.disponible ? 'Disponible' : 'Hors service'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                            {vehicules.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan="7">
                                        Aucun véhicule ne correspond à cette recherche.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal show={ouvert !== null} onClose={() => setOuvert(null)} maxWidth="lg">
                {ouvert && <Fiche vehicule={ouvert} peutModifier={peutModifier} onFermer={() => setOuvert(null)} />}
            </Modal>
        </AuthenticatedLayout>
    );
}
