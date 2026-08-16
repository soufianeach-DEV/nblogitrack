import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTraduction } from '@/traduire';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const CHAMPS = [
    ['finalite', 'registre.finalite', 'Finalité'],
    ['base_legale', 'registre.base', 'Base légale'],
    ['personnes', 'registre.personnes', 'Personnes concernées'],
    ['donnees', 'registre.donnees', 'Catégories de données'],
    ['destinataires', 'registre.destinataires', 'Destinataires'],
    ['transferts', 'registre.transferts', 'Transferts hors UE'],
    ['conservation', 'registre.conservation', 'Durée de conservation'],
    ['mesures', 'registre.mesures', 'Mesures de sécurité'],
];

export default function Index({ traitements, bases, responsable, information }) {
    const t = useTraduction();
    const flash = usePage().props.flash ?? {};
    const [edition, setEdition] = useState(null);

    const { data, setData, patch, processing, errors } = useForm({});

    const ouvrir = (entree) => {
        setData({ ...entree });
        setEdition(entree);
    };

    const enregistrer = (e) => {
        e.preventDefault();
        patch(route('registre.update', edition.id), {
            preserveScroll: true,
            onSuccess: () => setEdition(null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-marine">{t('registre.titre', 'Registre des traitements')}</h1>
                        <p className="text-sm text-slate-600">
                            {t('registre.sous_titre', 'Article 30 du RGPD — le document que l\'Autorité demande en premier.')}
                        </p>
                    </div>
                    {/* Le registre se présente à l'Autorité : il doit pouvoir
                        sortir sur papier sans passer par une exportation. */}
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-marine transition hover:bg-surface print:hidden"
                    >
                        {t('registre.imprimer', 'Imprimer')}
                    </button>
                </div>
            }
        >
            <Head title={t('registre.titre', 'Registre des traitements')} />

            {flash.success && (
                <div className="mb-4 rounded-lg bg-status-delivered/10 px-4 py-3 text-sm font-medium text-status-delivered print:hidden">
                    {flash.success}
                </div>
            )}

            <section className="rounded-2xl bg-white p-5 shadow-sm">
                <h2 className="text-xs font-semibold uppercase tracking-wider text-slate-600">
                    {t('registre.responsable', 'Responsable du traitement')}
                </h2>
                <p className="mt-1 font-bold text-marine">{responsable.nom}</p>
                <p className="text-sm text-slate-600">{responsable.adresse}</p>
                <p className="text-sm text-slate-600">
                    {t('compte.numero_tva', 'Numéro de TVA')} {responsable.entreprise} · {responsable.contact}
                </p>
            </section>

            {/* L'information des conducteurs conditionne le relevé de position :
                son état a sa place dans le registre, pas dans un autre écran. */}
            <section className="mt-4 rounded-2xl bg-white p-5 shadow-sm">
                <h2 className="text-xs font-semibold uppercase tracking-wider text-slate-600">
                    {t('registre.information', 'Information des conducteurs')}
                </h2>
                {information.note_existe ? (
                    <>
                        <p className="mt-1 text-sm text-marine">
                            <span className="font-bold">{information.informes}</span>
                            <span className="text-slate-600"> / {information.conducteurs} </span>
                            {t('registre.informes', 'ont pris connaissance de la version du :date', { date: information.version })}
                        </p>
                        {information.informes < information.conducteurs && (
                            <p className="mt-1 text-xs text-slate-600">
                                {t('registre.non_informes_aide', 'Aucune position n\'est relevée pour les conducteurs qui n\'ont pas encore pris connaissance de la note.')}
                            </p>
                        )}
                    </>
                ) : (
                    <p className="mt-1 text-sm text-status-incident">
                        {t('registre.sans_note', 'Aucune note d\'information n\'est rédigée : le suivi de position ne peut pas fonctionner.')}
                    </p>
                )}
            </section>

            <div className="mt-6 space-y-4">
                {traitements.map((entree, i) => (
                    <article key={entree.id} className="break-inside-avoid rounded-2xl bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <h2 className="text-lg font-bold text-marine">
                                <span className="mr-2 text-slate-500">{i + 1}.</span>
                                {entree.nom}
                            </h2>
                            <button
                                type="button"
                                onClick={() => ouvrir(entree)}
                                className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-marine transition hover:bg-surface print:hidden"
                            >
                                {t('action.modifier', 'Modifier')}
                            </button>
                        </div>

                        <dl className="mt-3 grid gap-x-6 gap-y-3 border-t border-slate-100 pt-3 sm:grid-cols-2">
                            {CHAMPS.map(([cle, tcle, defaut]) => (
                                <div key={cle} className={cle === 'finalite' || cle === 'donnees' || cle === 'mesures' ? 'sm:col-span-2' : ''}>
                                    <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                        {t(tcle, defaut)}
                                    </dt>
                                    <dd className="text-sm text-slate-700">{entree[cle] || '—'}</dd>
                                </div>
                            ))}
                        </dl>

                        {entree.modifie_le && (
                            <p className="mt-3 text-[11px] text-slate-500">
                                {t('pages.modifiee', 'modifiée le :date par :qui', { date: entree.modifie_le, qui: entree.modifie_par || '—' })}
                            </p>
                        )}
                    </article>
                ))}
            </div>

            <Modal show={edition !== null} onClose={() => setEdition(null)} maxWidth="2xl">
                <form onSubmit={enregistrer} className="p-6">
                    <h2 className="text-lg font-bold text-marine">{edition?.nom}</h2>

                    <div className="mt-4 space-y-3">
                        <div>
                            <label htmlFor="nom" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('api.nom', 'Nom')}
                            </label>
                            <input
                                id="nom"
                                value={data.nom ?? ''}
                                onChange={(e) => setData('nom', e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            {errors.nom && <p className="mt-1 text-sm text-status-incident">{errors.nom}</p>}
                        </div>

                        <div>
                            <label htmlFor="base" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {t('registre.base', 'Base légale')}
                            </label>
                            <input
                                id="base"
                                list="bases-legales"
                                value={data.base_legale ?? ''}
                                onChange={(e) => setData('base_legale', e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                            />
                            <datalist id="bases-legales">
                                {Object.values(bases).map((b) => <option key={b} value={b} />)}
                            </datalist>
                            {errors.base_legale && <p className="mt-1 text-sm text-status-incident">{errors.base_legale}</p>}
                        </div>

                        {CHAMPS.filter(([cle]) => cle !== 'base_legale').map(([cle, tcle, defaut]) => (
                            <div key={cle}>
                                <label htmlFor={cle} className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    {t(tcle, defaut)}
                                </label>
                                <textarea
                                    id={cle}
                                    rows={cle === 'finalite' || cle === 'mesures' || cle === 'donnees' ? 3 : 2}
                                    value={data[cle] ?? ''}
                                    onChange={(e) => setData(cle, e.target.value)}
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-marine focus:ring-marine"
                                />
                                {errors[cle] && <p className="mt-1 text-sm text-status-incident">{errors[cle]}</p>}
                            </div>
                        ))}
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setEdition(null)} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-marine">
                            {t('action.annuler', 'Annuler')}
                        </button>
                        <button disabled={processing} className="rounded-lg bg-marine px-4 py-2 text-sm font-semibold text-white transition hover:bg-marine-deep disabled:opacity-50">
                            {t('action.enregistrer', 'Enregistrer')}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
