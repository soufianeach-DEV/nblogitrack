import { useEffect, useMemo, useRef, useState } from 'react';
import TextInput from '@/Components/TextInput';
import { useLangue, useTraduction } from '@/traduire';

// Proposees des la premiere lettre, avant toute recherche.
export const GRANDES_VILLES = {
    AT: [['Vienne', 48.2082, 16.3738], ['Graz', 47.0707, 15.4395], ['Linz', 48.3069, 14.2858], ['Salzbourg', 47.8095, 13.0550]],
    BE: [['Bruxelles', 50.8466, 4.3528], ['Anvers', 51.2199, 4.4035], ['Gand', 51.0543, 3.7174], ['Charleroi', 50.4114, 4.4448], ['Liège', 50.6326, 5.5797], ['Bruges', 51.2093, 3.2247], ['Namur', 50.4674, 4.8720], ['Louvain', 50.8796, 4.7009]],
    BG: [['Sofia', 42.6977, 23.3219], ['Plovdiv', 42.1354, 24.7453], ['Varna', 43.2141, 27.9147]],
    CH: [['Zurich', 47.3769, 8.5417], ['Genève', 46.2044, 6.1432], ['Bâle', 47.5596, 7.5886], ['Berne', 46.9480, 7.4474], ['Lausanne', 46.5197, 6.6323]],
    CZ: [['Prague', 50.0755, 14.4378], ['Brno', 49.1951, 16.6068], ['Ostrava', 49.8209, 18.2625]],
    DE: [['Berlin', 52.5200, 13.4050], ['Hambourg', 53.5511, 9.9937], ['Munich', 48.1351, 11.5820], ['Cologne', 50.9375, 6.9603], ['Francfort', 50.1109, 8.6821], ['Düsseldorf', 51.2277, 6.7735], ['Stuttgart', 48.7758, 9.1829]],
    DK: [['Copenhague', 55.6761, 12.5683], ['Aarhus', 56.1629, 10.2039], ['Odense', 55.4038, 10.4024]],
    EE: [['Tallinn', 59.4370, 24.7536], ['Tartu', 58.3776, 26.7290]],
    ES: [['Madrid', 40.4168, -3.7038], ['Barcelone', 41.3874, 2.1686], ['Valence', 39.4699, -0.3763], ['Séville', 37.3891, -5.9845], ['Bilbao', 43.2630, -2.9350]],
    FI: [['Helsinki', 60.1699, 24.9384], ['Tampere', 61.4978, 23.7610], ['Turku', 60.4518, 22.2666]],
    FR: [['Paris', 48.8566, 2.3522], ['Marseille', 43.2965, 5.3698], ['Lyon', 45.7640, 4.8357], ['Toulouse', 43.6047, 1.4442], ['Nice', 43.7102, 7.2620], ['Nantes', 47.2184, -1.5536], ['Strasbourg', 48.5734, 7.7521], ['Lille', 50.6292, 3.0573]],
    GB: [['Londres', 51.5074, -0.1278], ['Manchester', 53.4808, -2.2426], ['Birmingham', 52.4862, -1.8904], ['Leeds', 53.8008, -1.5491], ['Glasgow', 55.8642, -4.2518]],
    GR: [['Athènes', 37.9838, 23.7275], ['Thessalonique', 40.6401, 22.9444], ['Patras', 38.2466, 21.7346]],
    HR: [['Zagreb', 45.8150, 15.9819], ['Split', 43.5081, 16.4402], ['Rijeka', 45.3271, 14.4422]],
    HU: [['Budapest', 47.4979, 19.0402], ['Debrecen', 47.5316, 21.6273], ['Szeged', 46.2530, 20.1414]],
    IE: [['Dublin', 53.3498, -6.2603], ['Cork', 51.8985, -8.4756], ['Limerick', 52.6638, -8.6267]],
    IT: [['Rome', 41.9028, 12.4964], ['Milan', 45.4642, 9.1900], ['Naples', 40.8518, 14.2681], ['Turin', 45.0703, 7.6869], ['Bologne', 44.4949, 11.3426]],
    LT: [['Vilnius', 54.6872, 25.2797], ['Kaunas', 54.8985, 23.9036], ['Klaipeda', 55.7033, 21.1443]],
    LU: [['Luxembourg', 49.6116, 6.1319], ['Esch-sur-Alzette', 49.4958, 5.9806], ['Differdange', 49.5242, 5.8914], ['Dudelange', 49.4786, 6.0876]],
    LV: [['Riga', 56.9496, 24.1052], ['Daugavpils', 55.8747, 26.5363]],
    NL: [['Amsterdam', 52.3676, 4.9041], ['Rotterdam', 51.9244, 4.4777], ['La Haye', 52.0705, 4.3007], ['Utrecht', 52.0907, 5.1214], ['Eindhoven', 51.4416, 5.4697], ['Groningue', 53.2194, 6.5665]],
    NO: [['Oslo', 59.9139, 10.7522], ['Bergen', 60.3913, 5.3221], ['Trondheim', 63.4305, 10.3951]],
    PL: [['Varsovie', 52.2297, 21.0122], ['Cracovie', 50.0647, 19.9450], ['Gdansk', 54.3520, 18.6466], ['Wroclaw', 51.1079, 17.0385], ['Poznan', 52.4064, 16.9252]],
    PT: [['Lisbonne', 38.7223, -9.1393], ['Porto', 41.1579, -8.6291], ['Braga', 41.5454, -8.4265]],
    RO: [['Bucarest', 44.4268, 26.1025], ['Cluj-Napoca', 46.7712, 23.6236], ['Timisoara', 45.7489, 21.2087]],
    SE: [['Stockholm', 59.3293, 18.0686], ['Göteborg', 57.7089, 11.9746], ['Malmö', 55.6050, 13.0038]],
    SI: [['Ljubljana', 46.0569, 14.5058], ['Maribor', 46.5547, 15.6459]],
    SK: [['Bratislava', 48.1486, 17.1077], ['Kosice', 48.7164, 21.2611]],
};

const photon = async (q, pays) => {
    const url = `https://photon.komoot.io/api/?q=${encodeURIComponent(q)}&lang=fr&limit=6&layer=city&layer=district`;
    let res;
    try {
        res = await fetch(url);
    } catch {
        res = null;
    }
    if (!res || !res.ok) {
        await new Promise((attendre) => setTimeout(attendre, 700));
        res = await fetch(url);
    }
    const json = await res.json();
    return (json.features || []).filter((f) => (f.properties.countrycode || '').toUpperCase() === pays);
};

/**
 * Le champ Ville de tous les formulaires : les grandes villes du pays des
 * la premiere lettre, puis les localites du referentiel des codes postaux.
 * Une ville choisie arrive a onChoix sous la forme d'un point GeoJSON.
 *
 * photon : chercher aussi chez Photon quand le referentiel ne connait pas
 * la saisie. Le simulateur de tarif ne le fait que pour les pays sans
 * codes postaux, les seuls dont le serveur sait verifier ces noms.
 */
export default function ChampVille({ id, pays, valeur, choisie, onSaisie, onChoix, placeholder, photon: avecPhoton = true, className = 'block w-full pr-9' }) {
    const t = useTraduction();
    const langue = useLangue();
    const nomPays = useMemo(() => new Intl.DisplayNames([langue], { type: 'region' }).of(pays) ?? pays, [langue, pays]);
    const [suggestions, setSuggestions] = useState([]);
    const [aucune, setAucune] = useState(false);
    const minuteur = useRef(null);
    // Une reponse qui arrive apres que le champ a perdu le focus ne doit
    // pas rouvrir la liste par-dessus le reste du formulaire.
    const actif = useRef(false);

    useEffect(() => {
        clearTimeout(minuteur.current);
        setSuggestions([]);
        setAucune(false);
    }, [pays]);

    const grandesVilles = (v) => (GRANDES_VILLES[pays] ?? [])
        .filter(([n]) => n.toLowerCase().startsWith(v.toLowerCase()))
        .map(([n, lat, lng]) => ({ properties: { name: n }, geometry: { coordinates: [lng, lat] } }));

    const chercher = (v) => {
        clearTimeout(minuteur.current);
        setAucune(false);
        if (v.length < 2) { setSuggestions(grandesVilles(v)); return; }
        minuteur.current = setTimeout(async () => {
            try {
                let res = [];
                try {
                    const r = await fetch(`/geo/villes?pays=${pays}&q=${encodeURIComponent(v)}`);
                    if (r.ok) {
                        res = (await r.json()).map((c) => ({
                            properties: { name: c.ville, postcode: c.code || undefined, state: c.region || undefined },
                            geometry: { coordinates: [Number(c.lng), Number(c.lat)] },
                        }));
                    }
                } catch {
                }
                if (res.length === 0 && avecPhoton) {
                    res = await photon(v, pays);
                }
                if (actif.current) {
                    setSuggestions(res);
                    setAucune(res.length === 0);
                }
            } catch {
                setSuggestions([]);
            }
        }, 150);
    };

    const afficher = () => {
        actif.current = true;
        if (choisie) return;
        chercher(valeur);
    };

    const choisir = (f) => {
        clearTimeout(minuteur.current);
        setSuggestions([]);
        setAucune(false);
        onChoix(f);
    };

    return (
        <div>
            <div className="relative">
                <TextInput
                    id={id}
                    value={valeur}
                    aria-label={t('adresse.ville', 'Ville')}
                    onChange={(e) => {
                        const v = e.target.value.toLowerCase();
                        onSaisie(v);
                        chercher(v);
                    }}
                    onFocus={afficher}
                    onClick={afficher}
                    onBlur={() => {
                        actif.current = false;
                        setTimeout(() => setSuggestions([]), 150);
                    }}
                    placeholder={placeholder ?? t('adresse.ville_ex', 'ex. Bruxelles')}
                    className={className}
                    autoComplete="off"
                />
                <button
                    type="button"
                    tabIndex={-1}
                    aria-label={t('adresse.afficher_liste', 'Afficher les propositions')}
                    onMouseDown={(e) => {
                        e.preventDefault();
                        if (suggestions.length > 0) { setSuggestions([]); return; }
                        setSuggestions(grandesVilles(''));
                    }}
                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-600 hover:text-marine"
                >
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.06l3.71-3.83a.75.75 0 1 1 1.08 1.04l-4.25 4.39a.75.75 0 0 1-1.08 0L5.23 8.27a.75.75 0 0 1 .02-1.06Z" clipRule="evenodd" />
                    </svg>
                </button>
                {suggestions.length > 0 && (
                    <ul className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                        {suggestions.map((f, i) => (
                            <li key={i}>
                                <button type="button" onMouseDown={(e) => { e.preventDefault(); choisir(f); }} className="block w-full px-4 py-2 text-left text-sm hover:bg-surface">
                                    {f.properties.name}{f.properties.postcode ? ` — ${f.properties.postcode}` : ''}{f.properties.state ? ` · ${f.properties.state}` : ''}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            {aucune && valeur.length >= 2 && (
                <p className="mt-1 text-xs text-status-incident">{t('adresse.aucune_ville', 'Aucune ville trouvée en :pays — vérifiez l\'orthographe ou le pays.', { pays: nomPays })}</p>
            )}
            {!aucune && valeur.length >= 2 && !choisie && suggestions.length === 0 && (
                <p className="mt-1 text-xs text-slate-600">{t('adresse.choisir_ville', 'Choisissez la ville dans la liste de suggestions.')}</p>
            )}
        </div>
    );
}
