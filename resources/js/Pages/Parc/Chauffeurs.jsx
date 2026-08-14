import BarreFiltres from '@/Components/BarreFiltres';
import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

const jjmmaaaaVersIso = (valeur) => (valeur ? valeur.split('/').reverse().join('-') : '');

function Fiche({ chauffeur, statuts, motifsSortie, peutModifier, onFermer }) {
    const [sortie, setSortie] = useState(Boolean(chauffeur.sorti_le));

    const { data, setData, patch, processing, errors } = useForm({
        is_available: chauffeur.disponible,
        adr_certified: chauffeur.adr,
        medical_exam_date: chauffeur.visite ?? '',
        license_expiry: jjmmaaaaVersIso(chauffeur.permis_echeance),
        cpc_expiry: chauffeur.code95 ?? '',
        tacho_card_expiry: chauffeur.tacho ?? '',
        employment_status: chauffeur.statut_code ?? 'OUVRIER',
        hired_on: jjmmaaaaVersIso(chauffeur.embauche),
        birth_date: chauffeur.naissance ?? '',
        retirement_planned_on: chauffeur.retraite_prevue ?? '',
        left_on: jjmmaaaaVersIso(chauffeur.sorti_le),
        departure_reason: chauffeur.motif_sortie_code ?? '',
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

            {chauffeur.empechements.length > 0 && (
                <div className="mt-4 rounded-lg bg-status-incident/10 px-3 py-2 text-xs text-status-incident">
                    <p className="font-semibold">Ne peut pas prendre la route</p>
                    <ul className="mt-1 list-inside list-disc">
                        {chauffeur.empechements.map((motif) => <li key={motif}>{motif}</li>)}
                    </ul>
                </div>
            )}

            <dl className="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 text-sm">
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Permis</dt><dd className="font-semibold text-marine">{chauffeur.permis}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Numéro</dt><dd className="font-mono text-xs text-marine">{chauffeur.numero_permis}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Statut</dt><dd className="font-semibold text-marine">{chauffeur.statut}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Entrée en service</dt><dd className="font-semibold text-marine">{chauffeur.embauche ?? '—'}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Téléphone</dt><dd className="font-semibold text-marine">{chauffeur.telephone ?? '—'}</dd></div>
                <div><dt className="text-xs uppercase tracking-wide text-slate-600">Missions confiées</dt><dd className="font-semibold text-marine">{chauffeur.missions}</dd></div>
                <div>
                    <dt className="text-xs uppercase tracking-wide text-slate-600">Âge</dt>
                    <dd className="font-semibold text-marine">{chauffeur.age !== null ? chauffeur.age + ' ans' : '—'}</dd>
                </div>
                <div>
                    <dt className="text-xs uppercase tracking-wide text-slate-600">Retraite</dt>
                    <dd className="font-semibold text-marine">{chauffeur.retraite_affichee ?? '—'}</dd>
                </div>
            </dl>

            {chauffeur.sorti_le && (
                <p className="mt-3 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-700">
                    Parti le {chauffeur.sorti_le} — {chauffeur.motif_sortie}. La fiche est conservée
                    pour que les missions passées gardent un nom.
                </p>
            )}

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

                    {errors.is_available && <p className="text-xs text-status-incident">{errors.is_available}</p>}
                    {errors.adr_certified && <p className="text-xs text-status-incident">{errors.adr_certified}</p>}

                    {chauffeur.engage && (
                        <p className="rounded-lg bg-action/10 px-3 py-2 text-xs text-action-dark">
                            Ce chauffeur porte une mission en cours : il ne peut pas être retiré du service.
                        </p>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label htmlFor="statut" className="text-xs uppercase tracking-wide text-slate-600">
                                Statut
                            </label>
                            <select
                                id="statut"
                                value={data.employment_status}
                                onChange={(e) => setData('employment_status', e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            >
                                {Object.entries(statuts).map(([valeur, libelle]) => (
                                    <option key={valeur} value={valeur}>{libelle}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="embauche" className="text-xs uppercase tracking-wide text-slate-600">
                                Entrée en service
                            </label>
                            <input
                                id="embauche"
                                type="date"
                                value={data.hired_on ?? ''}
                                onChange={(e) => setData('hired_on', e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            {errors.hired_on && <p className="mt-1 text-xs text-status-incident">{errors.hired_on}</p>}
                        </div>
                        <div>
                            <label htmlFor="naissance" className="text-xs uppercase tracking-wide text-slate-600">
                                Date de naissance
                            </label>
                            <input
                                id="naissance"
                                type="date"
                                value={data.birth_date ?? ''}
                                onChange={(e) => setData('birth_date', e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            {errors.birth_date && <p className="mt-1 text-xs text-status-incident">{errors.birth_date}</p>}
                        </div>
                        <div>
                            <label htmlFor="retraite" className="text-xs uppercase tracking-wide text-slate-600">
                                Retraite prévue
                            </label>
                            <input
                                id="retraite"
                                type="date"
                                value={data.retirement_planned_on ?? ''}
                                onChange={(e) => setData('retirement_planned_on', e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                        </div>
                    </div>

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

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label htmlFor="code95" className="text-xs uppercase tracking-wide text-slate-600">
                                Code 95 valable jusqu'au
                            </label>
                            <input
                                id="code95"
                                type="date"
                                value={data.cpc_expiry ?? ''}
                                onChange={(e) => setData('cpc_expiry', e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                        </div>
                        <div>
                            <label htmlFor="tacho" className="text-xs uppercase tracking-wide text-slate-600">
                                Carte tachygraphe jusqu'au
                            </label>
                            <input
                                id="tacho"
                                type="date"
                                value={data.tacho_card_expiry ?? ''}
                                onChange={(e) => setData('tacho_card_expiry', e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                        </div>
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

            {peutModifier && (
                <div className="mt-5 border-t border-slate-100 pt-4">
                    {! sortie ? (
                        <button
                            type="button"
                            onClick={() => setSortie(true)}
                            className="text-sm font-semibold text-status-incident transition hover:underline"
                        >
                            Enregistrer un départ
                        </button>
                    ) : (
                        <div className="space-y-3">
                            <p className="text-xs uppercase tracking-wide text-slate-600">Départ de l'entreprise</p>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label htmlFor="sortie" className="text-xs text-slate-600">Date</label>
                                    <input
                                        id="sortie"
                                        type="date"
                                        value={data.left_on ?? ''}
                                        onChange={(e) => setData('left_on', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                                    />
                                    {errors.left_on && <p className="mt-1 text-xs text-status-incident">{errors.left_on}</p>}
                                </div>
                                <div>
                                    <label htmlFor="motif" className="text-xs text-slate-600">Motif</label>
                                    <select
                                        id="motif"
                                        value={data.departure_reason ?? ''}
                                        onChange={(e) => setData('departure_reason', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                                    >
                                        <option value="">— Choisir —</option>
                                        {Object.entries(motifsSortie).map(([valeur, libelle]) => (
                                            <option key={valeur} value={valeur}>{libelle}</option>
                                        ))}
                                    </select>
                                    {errors.departure_reason && <p className="mt-1 text-xs text-status-incident">{errors.departure_reason}</p>}
                                </div>
                            </div>
                            <p className="rounded-lg bg-surface px-3 py-2 text-xs text-slate-600">
                                La fiche n'est jamais supprimée : le compte est fermé, les missions
                                passées gardent leur conducteur.
                            </p>
                            {! chauffeur.sorti_le && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSortie(false);
                                        setData((p) => ({ ...p, left_on: '', departure_reason: '' }));
                                    }}
                                    className="text-xs font-semibold text-slate-600 transition hover:text-marine"
                                >
                                    Annuler le départ
                                </button>
                            )}
                        </div>
                    )}
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

export default function Chauffeurs({ chauffeurs = [], permis = [], statuts = {}, motifsSortie = {}, compteurs, filtres = {}, peutModifier = false }) {
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
                    { libelle: 'Aptes', valeur: 'disponibles', nombre: compteurs.disponibles },
                    { libelle: 'Certifiés ADR', valeur: 'adr', nombre: compteurs.adr },
                    { libelle: 'Ne peuvent pas rouler', valeur: 'inaptes', nombre: compteurs.inaptes, alerte: true },
                    { libelle: 'Visite à renouveler', valeur: 'visite', nombre: compteurs.visite, alerte: true },
                    { libelle: 'Sortis', valeur: 'sortis', nombre: compteurs.sortis },
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
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                                            c.sorti_le ? 'bg-slate-100 text-slate-700'
                                                : c.empechements.length > 0 ? 'bg-status-incident/10 text-status-incident'
                                                    : c.engage ? 'bg-brand-blue/10 text-brand-blue'
                                                        : c.disponible ? 'bg-status-delivered/10 text-status-delivered'
                                                            : 'bg-slate-100 text-slate-700'
                                        }`}>
                                            {c.sorti_le ? 'Parti'
                                                : c.empechements.length > 0 ? 'Ne peut pas rouler'
                                                    : c.engage ? 'En mission'
                                                        : c.disponible ? 'Disponible' : 'Indisponible'}
                                        </span>
                                        {c.empechements.length > 0 && ! c.sorti_le && (
                                            <span className="mt-1 block max-w-[16rem] truncate text-xs text-slate-600" title={c.empechements.join(' · ')}>
                                                {c.empechements[0]}
                                            </span>
                                        )}
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
                {ouvert && (
                    <Fiche
                        chauffeur={ouvert}
                        statuts={statuts}
                        motifsSortie={motifsSortie}
                        peutModifier={peutModifier}
                        onFermer={() => setOuvert(null)}
                    />
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
