import BarreFiltres from '@/Components/BarreFiltres';
import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

function Fiche({ chauffeur, peutModifier, onFermer }) {
    const { data, setData, patch, processing, errors } = useForm({
        is_available: chauffeur.disponible,
        adr_certified: chauffeur.adr,
        medical_exam_date: chauffeur.visite ?? '',
        license_expiry: chauffeur.permis_echeance
            ? chauffeur.permis_echeance.split('/').reverse().join('-')
            : '',
    });

    const enregistrer = (e) => {
        e.preventDefault();
        patch(route('drivers.update', chauffeur.id), {
            preserveScroll: true,
            onSuccess: onFermer,
        });
    };

    const initiales = chauffeur.nom.split(' ').map((m) => m[0]).slice(0, 2).join('');

    return (
        <form onSubmit={enregistrer} className="p-6">
            <div className="flex items-center gap-3">
                <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-blue/10 text-sm font-bold text-brand-blue">
                    {initiales}
                </span>
                <div className="min-w-0">
                    <p className="truncate text-lg font-bold text-marine">{chauffeur.nom}</p>
                    <p className="truncate text-sm text-slate-600">{chauffeur.email}</p>
                </div>
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 text-sm">
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Permis</dt><dd className="font-semibold text-marine">{chauffeur.permis}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Numéro</dt><dd className="font-mono text-xs text-marine">{chauffeur.numero_permis}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Téléphone</dt><dd className="font-semibold text-marine">{chauffeur.telephone ?? '—'}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Missions confiées</dt><dd className="font-semibold text-marine">{chauffeur.missions}</dd></div>
            </dl>

            {! peutModifier ? (
                <p className="mt-5 rounded-lg bg-surface px-3 py-2 text-sm text-slate-600">
                    Consultation seule — la modification des profils est réservée à l'administrateur.
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

                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.adr_certified}
                            onChange={(e) => setData('adr_certified', e.target.checked)}
                            className="rounded border-slate-300 text-marine focus:ring-marine"
                        />
                        <span className="text-sm font-semibold text-marine">Certifié ADR — matières dangereuses</span>
                    </label>

                    {chauffeur.engage && (
                        <p className="rounded-lg bg-action/10 px-3 py-2 text-xs text-action-dark">
                            Ce chauffeur porte une mission en cours : il ne peut pas être retiré du service.
                        </p>
                    )}

                    <div>
                        <label htmlFor="visite" className="text-xs uppercase tracking-wide text-slate-600">
                            Dernière visite médicale
                        </label>
                        <input
                            id="visite"
                            type="date"
                            value={data.medical_exam_date ?? ''}
                            onChange={(e) => setData('medical_exam_date', e.target.value)}
                            className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                        {errors.medical_exam_date && <p className="mt-1 text-xs text-status-incident">{errors.medical_exam_date}</p>}
                    </div>

                    <div>
                        <label htmlFor="echeance" className="text-xs uppercase tracking-wide text-slate-600">
                            Échéance du permis
                        </label>
                        <input
                            id="echeance"
                            type="date"
                            value={data.license_expiry ?? ''}
                            onChange={(e) => setData('license_expiry', e.target.value)}
                            className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                        />
                        {errors.license_expiry && <p className="mt-1 text-xs text-status-incident">{errors.license_expiry}</p>}
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

export default function Chauffeurs({ chauffeurs = [], permis = [], compteurs, filtres = {}, peutModifier = false }) {
    const [ouvert, setOuvert] = useState(null);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold text-marine">Chauffeurs</h1>
                    <p className="text-sm text-slate-600">
                        {compteurs.total} chauffeurs, {compteurs.disponibles} disponibles, {compteurs.adr} certifiés ADR
                    </p>
                </div>
            }
        >
            <Head title="Chauffeurs" />

            <BarreFiltres
                adresse={route('drivers.index')}
                filtres={filtres}
                placeholder="Nom, adresse électronique, numéro de permis…"
                listes={[{ champ: 'permis', intitule: 'Tous les permis', options: permis }]}
                compteurs={[
                    { libelle: 'Tous', valeur: null, nombre: compteurs.total },
                    { libelle: 'Disponibles', valeur: 'disponibles', nombre: compteurs.disponibles },
                    { libelle: 'Indisponibles', valeur: 'indisponibles', nombre: compteurs.total - compteurs.disponibles },
                    { libelle: 'Certifiés ADR', valeur: 'adr', nombre: compteurs.adr },
                    { libelle: 'Visite à renouveler', valeur: 'visite', nombre: compteurs.visite, alerte: true },
                ]}
            />

            <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Chauffeur</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Permis</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">ADR</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">Visite médicale</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right font-semibold">Missions</th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 font-semibold">État</th>
                            </tr>
                        </thead>
                        <tbody>
                            {chauffeurs.map((c) => (
                                <tr
                                    key={c.id}
                                    onClick={() => setOuvert(c)}
                                    className="cursor-pointer border-b border-slate-50 transition last:border-0 hover:bg-surface"
                                >
                                    <td className="px-4 py-3">
                                        <span className="block max-w-[14rem] truncate font-semibold text-marine">{c.nom}</span>
                                        <span className="block max-w-[14rem] truncate text-xs text-slate-600">{c.email}</span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className="font-semibold text-marine">{c.permis}</span>
                                        <span className="ml-2 font-mono text-xs text-slate-600">{c.numero_permis}</span>
                                        <span className={`block text-xs ${c.permis_bientot ? 'font-semibold text-status-incident' : 'text-slate-600'}`}>
                                            {c.permis_echeance ? `expire le ${c.permis_echeance}` : 'échéance inconnue'}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        {c.adr ? (
                                            <span className="inline-flex rounded-full bg-status-incident/10 px-2.5 py-1 text-xs font-bold text-status-incident">ADR</span>
                                        ) : (
                                            <span className="text-slate-600">—</span>
                                        )}
                                    </td>
                                    <td className={`whitespace-nowrap px-4 py-3 ${c.visite_perimee ? 'font-semibold text-status-incident' : 'text-slate-600'}`}>
                                        {c.visite_affichee ?? '—'}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{c.missions}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                                            c.engage ? 'bg-brand-blue/10 text-brand-blue'
                                                : c.disponible ? 'bg-status-delivered/10 text-status-delivered'
                                                    : 'bg-slate-100 text-slate-700'
                                        }`}>
                                            {c.engage ? 'En mission' : c.disponible ? 'Disponible' : 'Indisponible'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                            {chauffeurs.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-slate-600" colSpan="6">
                                        Aucun chauffeur ne correspond à cette recherche.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal show={ouvert !== null} onClose={() => setOuvert(null)} maxWidth="lg">
                {ouvert && <Fiche chauffeur={ouvert} peutModifier={peutModifier} onFermer={() => setOuvert(null)} />}
            </Modal>
        </AuthenticatedLayout>
    );
}
