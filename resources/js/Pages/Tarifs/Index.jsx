import Icone from '@/Components/Icone';
import VitrineLayout from '@/Layouts/VitrineLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const euros = (montant) => Number(montant).toLocaleString('fr-FR', {
    style: 'currency',
    currency: 'EUR',
});

/**
 * Champ de localite qui puise dans les codes postaux importes localement.
 * La frappe attend deux dixiemes de seconde avant d'interroger le serveur.
 */
function ChoixVille({ id, pays, valeur, onChange, placeholder }) {
    const [suggestions, setSuggestions] = useState([]);
    const [minuteur, setMinuteur] = useState(null);

    const saisir = (texte) => {
        onChange(texte);
        clearTimeout(minuteur);

        if (texte.trim().length < 2) {
            setSuggestions([]);

            return;
        }

        setMinuteur(setTimeout(() => {
            fetch(route('geo.villes', { pays, q: texte.trim() }), { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : []))
                .then((villes) => setSuggestions(Array.isArray(villes) ? villes.slice(0, 6) : []))
                .catch(() => setSuggestions([]));
        }, 200));
    };

    return (
        <div className="relative">
            <input
                id={id}
                value={valeur}
                onChange={(e) => saisir(e.target.value)}
                onBlur={() => setTimeout(() => setSuggestions([]), 150)}
                placeholder={placeholder}
                autoComplete="off"
                className="w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm focus:border-marine focus:ring-marine"
            />
            {suggestions.length > 0 && (
                <ul className="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                    {suggestions.map((ville) => (
                        <li key={ville.ville + ville.code}>
                            <button
                                type="button"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => { onChange(ville.ville); setSuggestions([]); }}
                                className="flex w-full items-baseline gap-2 px-3 py-2 text-left text-sm transition hover:bg-surface"
                            >
                                <span className="font-medium text-marine">{ville.ville}</span>
                                {ville.region && <span className="text-xs text-slate-600">{ville.region}</span>}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function Index({ destinations = [], formules = [] }) {
    const [depart, setDepart] = useState('');
    const [destination, setDestination] = useState('');
    const [pays, setPays] = useState('BE');
    const [poids, setPoids] = useState('500');
    const [adr, setAdr] = useState(false);
    const [resultat, setResultat] = useState(null);
    const [erreur, setErreur] = useState(null);
    const [encours, setEncours] = useState(false);

    const simuler = async (e) => {
        e.preventDefault();
        setEncours(true);
        setErreur(null);

        try {
            const reponse = await fetch(route('tarifs.simuler'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
                    ),
                },
                body: JSON.stringify({ depart, destination, pays, poids, adr }),
            });

            const donnees = await reponse.json();

            if (! reponse.ok) {
                setErreur(donnees.erreur ?? 'La simulation a échoué. Vérifiez les localités saisies.');
                setResultat(null);
            } else {
                setResultat(donnees);
            }
        } catch (probleme) {
            setErreur('Le service est momentanément indisponible.');
        } finally {
            setEncours(false);
        }
    };

    return (
        <VitrineLayout>
            <Head title="Tarifs" />

            <section className="bg-marine py-16 text-white">
                <div className="mx-auto max-w-4xl px-4 text-center">
                    <h1 className="text-3xl font-bold sm:text-4xl">Calculez votre tarif</h1>
                    <p className="mx-auto mt-3 max-w-2xl text-slate-300">
                        Un prix indicatif en quelques secondes, sans compte et sans engagement.
                        Départ de Belgique vers {destinations.length} destinations européennes.
                    </p>
                </div>
            </section>

            <section className="bg-surface py-12">
                <div className="mx-auto max-w-4xl px-4">
                    <form onSubmit={simuler} className="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label htmlFor="depart" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Enlèvement en Belgique
                                </label>
                                <ChoixVille
                                    id="depart"
                                    pays="BE"
                                    valeur={depart}
                                    onChange={setDepart}
                                    placeholder="Bruxelles, Anvers, Liège…"
                                />
                            </div>

                            <div>
                                <label htmlFor="pays" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Pays de destination
                                </label>
                                <select
                                    id="pays"
                                    value={pays}
                                    onChange={(e) => { setPays(e.target.value); setDestination(''); }}
                                    className="w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm focus:border-marine focus:ring-marine"
                                >
                                    {destinations.map((d) => (
                                        <option key={d.code} value={d.code}>{d.nom}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label htmlFor="destination" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Localité de livraison
                                </label>
                                <ChoixVille
                                    id="destination"
                                    pays={pays}
                                    valeur={destination}
                                    onChange={setDestination}
                                    placeholder="Commencez à taper…"
                                />
                            </div>

                            <div>
                                <label htmlFor="poids" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Poids de la marchandise
                                </label>
                                <div className="relative">
                                    <input
                                        id="poids"
                                        type="number"
                                        min="1"
                                        max="44000"
                                        value={poids}
                                        onChange={(e) => setPoids(e.target.value)}
                                        className="w-full rounded-lg border-slate-300 py-2.5 pr-12 text-sm shadow-sm focus:border-marine focus:ring-marine"
                                        required
                                    />
                                    <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-600">kg</span>
                                </div>
                            </div>
                        </div>

                        <label className="mt-5 flex items-center gap-3">
                            <input
                                type="checkbox"
                                checked={adr}
                                onChange={(e) => setAdr(e.target.checked)}
                                className="rounded border-slate-300 text-marine focus:ring-marine"
                            />
                            <span className="text-sm text-marine">
                                Matière dangereuse — transport soumis à l'accord ADR
                            </span>
                        </label>

                        <button
                            type="submit"
                            disabled={encours || ! depart || ! destination}
                            className="mt-6 w-full rounded-lg bg-action px-6 py-3.5 text-base font-bold text-marine-deep transition hover:bg-action-dark disabled:opacity-50 sm:w-auto sm:px-10"
                        >
                            {encours ? 'Calcul en cours…' : 'Calculer mon tarif'}
                        </button>

                        {erreur && (
                            <p className="mt-4 rounded-lg bg-status-incident/10 px-4 py-3 text-sm text-status-incident">
                                {erreur}
                            </p>
                        )}
                    </form>

                    {resultat && (
                        <div className="mt-6 rounded-2xl bg-white p-6 shadow-sm sm:p-8">
                            <div className="flex flex-wrap items-baseline justify-between gap-3 border-b border-slate-100 pb-4">
                                <h2 className="text-lg font-bold text-marine">
                                    {resultat.depart} <span className="text-slate-600">→</span> {resultat.arrivee}
                                    <span className="ml-2 text-sm font-normal text-slate-600">{resultat.pays}</span>
                                </h2>
                                <p className="text-sm text-slate-600">
                                    {resultat.distance.toLocaleString('fr-FR')} km par la route ·{' '}
                                    {resultat.poids.toLocaleString('fr-FR')} kg
                                    {resultat.adr && ' · ADR'}
                                </p>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-3">
                                {resultat.formules.map((f) => (
                                    <div key={f.formule} className="rounded-xl border border-slate-200 p-5 text-center">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                            {f.formule}
                                        </p>
                                        <p className="mt-2 text-2xl font-bold text-marine">{euros(f.prix)}</p>
                                        <p className="mt-1 text-sm text-slate-600">
                                            livré en {f.delai} jour{f.delai > 1 ? 's' : ''}
                                        </p>
                                        {f.dedie && (
                                            <p className="mt-2 text-xs text-action-dark">Véhicule dédié</p>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <p className="mt-5 text-xs text-slate-600">
                                Prix hors TVA, à titre indicatif. Le tarif définitif tient compte de l'adresse exacte,
                                de la date d'enlèvement et des contraintes de chargement.
                            </p>

                            <div className="mt-5 flex flex-wrap gap-3">
                                <Link
                                    href={route('devis.create')}
                                    className="rounded-lg bg-marine px-6 py-3 text-sm font-bold text-white transition hover:bg-marine-deep"
                                >
                                    Demander un devis ferme
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-lg border border-slate-300 px-6 py-3 text-sm font-bold text-marine transition hover:bg-surface"
                                >
                                    Ouvrir un compte
                                </Link>
                            </div>
                        </div>
                    )}

                    <div className="mt-6 grid gap-4 sm:grid-cols-3">
                        {formules.map((formule) => (
                            <div key={formule} className="flex items-start gap-3 rounded-xl bg-white p-4 shadow-sm">
                                <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue">
                                    <Icone nom="camion" className="h-5 w-5" />
                                </span>
                                <p className="text-sm text-slate-600">
                                    <span className="block font-semibold text-marine">{formule}</span>
                                    {formule === 'Express'
                                        ? 'Un véhicule pour vous seul, au plus court.'
                                        : formule === 'Standard'
                                            ? 'Le meilleur compromis entre délai et prix.'
                                            : 'Groupage avec d\'autres envois, au tarif le plus bas.'}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </VitrineLayout>
    );
}
