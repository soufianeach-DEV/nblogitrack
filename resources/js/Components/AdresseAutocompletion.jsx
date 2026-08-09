import { useState, useRef } from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

const PAYS = [
    { code: 'BE', nom: 'Belgique' },
    { code: 'FR', nom: 'France' },
    { code: 'NL', nom: 'Pays-Bas' },
    { code: 'DE', nom: 'Allemagne' },
    { code: 'LU', nom: 'Luxembourg' },
];

export default function AdresseAutocompletion({ label, onChange, onSelect, error }) {
    const [pays, setPays] = useState('BE');
    const [ville, setVille] = useState('');
    const [villeCoords, setVilleCoords] = useState(null);
    const [cp, setCp] = useState('');
    const [rue, setRue] = useState('');
    const [numero, setNumero] = useState('');
    const [coords, setCoords] = useState(null);
    const [suggVilles, setSuggVilles] = useState([]);
    const [suggRues, setSuggRues] = useState([]);
    const timer = useRef(null);

    const selectCls = 'block w-full rounded-md border-gray-300 shadow-sm focus:border-marine focus:ring-marine';

    const publier = (etat = {}) => {
        const s = { pays, ville, cp, rue, numero, coords, ...etat };
        if (s.coords && s.rue && s.ville) {
            const nomPays = PAYS.find((p) => p.code === s.pays).nom;
            onSelect({
                address: `${s.rue}${s.numero ? ' ' + s.numero : ''}, ${s.cp ? s.cp + ' ' : ''}${s.ville}, ${nomPays}`,
                lat: s.coords.lat,
                lng: s.coords.lng,
            });
        } else {
            onChange('');
        }
    };

    const photon = async (q, extra, paysCode) => {
        const res = await fetch(`https://photon.komoot.io/api/?q=${encodeURIComponent(q)}&lang=fr&limit=6${extra}`);
        const json = await res.json();
        return (json.features || []).filter((f) => (f.properties.countrycode || '').toUpperCase() === paysCode);
    };

    const chercherVilles = (v) => {
        setVille(v);
        setCoords(null);
        setRue('');
        setSuggRues([]);
        publier({ ville: v, coords: null, rue: '' });
        clearTimeout(timer.current);
        if (v.length < 2) { setSuggVilles([]); return; }
        timer.current = setTimeout(async () => {
            try {
                setSuggVilles(await photon(v, '&layer=city&layer=district&layer=locality', pays));
            } catch {
                setSuggVilles([]);
            }
        }, 300);
    };

    const choisirVille = (f) => {
        const p = f.properties;
        const [lng, lat] = f.geometry.coordinates;
        setVille(p.name);
        setVilleCoords({ lat, lng });
        if (p.postcode) setCp(p.postcode);
        setSuggVilles([]);
    };

    const chercherRues = (v) => {
        setRue(v);
        setCoords(null);
        publier({ rue: v, coords: null });
        clearTimeout(timer.current);
        if (v.length < 2) { setSuggRues([]); return; }
        timer.current = setTimeout(async () => {
            try {
                const centre = villeCoords ? `&lat=${villeCoords.lat}&lon=${villeCoords.lng}` : '';
                setSuggRues(await photon(`${v} ${ville}`, `&layer=street${centre}`, pays));
            } catch {
                setSuggRues([]);
            }
        }, 300);
    };

    const choisirRue = (f) => {
        const p = f.properties;
        const [lng, lat] = f.geometry.coordinates;
        const nouveauCp = p.postcode || cp;
        setRue(p.name);
        setCp(nouveauCp);
        setCoords({ lat, lng });
        setSuggRues([]);
        publier({ rue: p.name, cp: nouveauCp, coords: { lat, lng } });
    };

    const changerPays = (code) => {
        setPays(code);
        setVille('');
        setVilleCoords(null);
        setCp('');
        setRue('');
        setNumero('');
        setCoords(null);
        setSuggVilles([]);
        setSuggRues([]);
        onChange('');
    };

    return (
        <div>
            <InputLabel value={label} />
            <div className="mt-1 space-y-2">
                <select value={pays} onChange={(e) => changerPays(e.target.value)} className={selectCls}>
                    {PAYS.map((p) => (
                        <option key={p.code} value={p.code}>{p.nom}</option>
                    ))}
                </select>
                <div className="relative">
                    <TextInput
                        value={ville}
                        onChange={(e) => chercherVilles(e.target.value)}
                        onBlur={() => setTimeout(() => setSuggVilles([]), 150)}
                        placeholder="Ville"
                        className="block w-full"
                        autoComplete="off"
                    />
                    {suggVilles.length > 0 && (
                        <ul className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                            {suggVilles.map((f, i) => (
                                <li key={i}>
                                    <button type="button" onMouseDown={(e) => { e.preventDefault(); choisirVille(f); }} className="block w-full px-4 py-2 text-left text-sm hover:bg-surface">
                                        {f.properties.name}{f.properties.postcode ? ` — ${f.properties.postcode}` : f.properties.state ? ` — ${f.properties.state}` : ''}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <TextInput
                    value={cp}
                    onChange={(e) => { setCp(e.target.value); publier({ cp: e.target.value }); }}
                    placeholder="Code postal"
                    className="block w-full"
                    disabled={!ville}
                />
                <div className="flex gap-2">
                    <div className="relative flex-1">
                        <TextInput
                            value={rue}
                            onChange={(e) => chercherRues(e.target.value)}
                            onBlur={() => setTimeout(() => setSuggRues([]), 150)}
                            placeholder="Rue"
                            className="block w-full"
                            autoComplete="off"
                            disabled={!ville}
                        />
                        {suggRues.length > 0 && (
                            <ul className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                                {suggRues.map((f, i) => (
                                    <li key={i}>
                                        <button type="button" onMouseDown={(e) => { e.preventDefault(); choisirRue(f); }} className="block w-full px-4 py-2 text-left text-sm hover:bg-surface">
                                            {f.properties.name}{f.properties.postcode ? ` — ${f.properties.postcode}` : ''}{f.properties.city ? ` ${f.properties.city}` : ''}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <TextInput
                        value={numero}
                        onChange={(e) => { setNumero(e.target.value); publier({ numero: e.target.value }); }}
                        placeholder="N°"
                        className="block w-24"
                        disabled={!rue}
                    />
                </div>
            </div>
            <InputError message={error} className="mt-2" />
        </div>
    );
}