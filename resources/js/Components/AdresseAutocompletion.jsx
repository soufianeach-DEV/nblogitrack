import { useState, useRef } from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

const CODES_EUROPE = ['AT', 'BE', 'BG', 'CH', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

const nomRegion = new Intl.DisplayNames(['fr'], { type: 'region' });

const PAYS = CODES_EUROPE
    .map((code) => ({ code, nom: nomRegion.of(code) }))
    .sort((a, b) => a.nom.localeCompare(b.nom, 'fr'));

const CP_NUMERIQUE = { BE: 4, LU: 4, FR: 5, DE: 5, IT: 5, ES: 5, AT: 4, CH: 4, DK: 4, HU: 4, SI: 4, BG: 4, NO: 4, FI: 5, EE: 5, HR: 5, RO: 6, LT: 5 };

const CP_EXEMPLE = { BE: 'ex. 1000', FR: 'ex. 75001', DE: 'ex. 10115', NL: 'ex. 1012 AB', GB: 'ex. SW1A 1AA', PL: 'ex. 00-950', PT: 'ex. 1000-001', CZ: 'ex. 110 00' };

const GRANDES_VILLES = {
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

const kmEntre = (lat1, lng1, lat2, lng2) => {
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1), dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return 6371 * 2 * Math.asin(Math.sqrt(a));
};

export default function AdresseAutocompletion({ label, onChange, onSelect, error, required = false }) {
    const [pays, setPays] = useState('BE');
    const [ville, setVille] = useState('');
    const [villeCoords, setVilleCoords] = useState(null);
    const [cp, setCp] = useState('');
    const [cpLocalite, setCpLocalite] = useState('');
    const [cpChoisi, setCpChoisi] = useState(false);
    const [cpsDispo, setCpsDispo] = useState([]);
    const [cpsLibres, setCpsLibres] = useState(false);
    const [rue, setRue] = useState('');
    const [rueChoisie, setRueChoisie] = useState(false);
    const [numero, setNumero] = useState('');
    const [numChoisi, setNumChoisi] = useState(false);
    const [numsDispo, setNumsDispo] = useState([]);
    const [numsChargement, setNumsChargement] = useState(false);
    const numeroRef = useRef('');
    const [coords, setCoords] = useState(null);
    const [suggVilles, setSuggVilles] = useState([]);
    const [suggCps, setSuggCps] = useState([]);
    const [suggRues, setSuggRues] = useState([]);
    const [suggNums, setSuggNums] = useState([]);
    const [aucuneVille, setAucuneVille] = useState(false);
    const [aucunCp, setAucunCp] = useState(false);
    const [aucuneRue, setAucuneRue] = useState(false);
    const [aucunNum, setAucunNum] = useState(false);
    const timer = useRef(null);

    const selectCls = 'block w-full rounded-md border-gray-300 shadow-sm focus:border-marine focus:ring-marine';
    const sousLabel = 'mb-1 block text-xs font-medium text-slate-500';
    const nomPays = nomRegion.of(pays);

    const publier = (etat = {}) => {
        const s = { pays, ville, cp, rue, numero, coords, ...etat };
        if (s.coords && s.rue && s.ville && s.cp && s.numero) {
            onSelect({
                address: `${s.rue} ${s.numero}, ${s.cp} ${s.ville}, ${nomRegion.of(s.pays)}`,
                lat: s.coords.lat,
                lng: s.coords.lng,
                pays: s.pays,
            });
        } else {
            onChange('');
        }
    };

    const photon = async (q, extra, paysCode, limit = 6) => {
        const url = `https://photon.komoot.io/api/?q=${encodeURIComponent(q)}&lang=fr&limit=${limit}${extra}`;
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
        return (json.features || []).filter((f) => (f.properties.countrycode || '').toUpperCase() === paysCode);
    };

    const formatCp = (v) => {
        if (pays === 'NL') {
            const s = v.toUpperCase().replace(/[^0-9A-Z]/g, '');
            return s.slice(0, 4) + (s.length > 4 ? ' ' + s.slice(4, 6).replace(/[0-9]/g, '') : '');
        }
        if (CP_NUMERIQUE[pays]) {
            return v.replace(/\D/g, '').slice(0, CP_NUMERIQUE[pays]);
        }
        return v.toUpperCase().replace(/[^0-9A-Z \-]/g, '').slice(0, 8);
    };

    const formatNumero = (v) => v.replace(/[^0-9A-Za-z/-]/g, '').replace(/^[^0-9]+/, '');

    const sansAccents = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    const villesProposees = (v) => (GRANDES_VILLES[pays] ?? [])
        .filter(([n]) => n.toLowerCase().startsWith(v.toLowerCase()))
        .map(([n, lat, lng]) => ({ properties: { name: n }, geometry: { coordinates: [lng, lat] } }));

    const afficherVilles = () => {
        if (villeCoords) return;
        if (ville.length < 2) setSuggVilles(villesProposees(ville));
        else chercherVilles(ville);
    };

    const afficherCps = () => {
        if (!villeCoords || cpsLibres || cpsDispo.length === 0) return;
        const base = cpChoisi || !cp ? cpsDispo : cpsDispo.filter((c) => c.cp.startsWith(cp));
        setSuggCps(base.length > 0 ? base : cpsDispo);
    };

    const afficherNums = () => {
        if (!rueChoisie || numsDispo.length === 0) return;
        const base = numChoisi || !numero ? numsDispo : numsDispo.filter((n) => n.hn.toLowerCase().startsWith(numero.toLowerCase()));
        setSuggNums((base.length > 0 ? base : numsDispo).slice(0, 30));
    };

    const resetCp = () => {
        setCp('');
        setCpLocalite('');
        setCpChoisi(false);
        setCpsDispo([]);
        setCpsLibres(false);
        setSuggCps([]);
        setAucunCp(false);
    };

    const resetNumero = () => {
        setNumero('');
        numeroRef.current = '';
        setNumChoisi(false);
        setNumsDispo([]);
        setNumsChargement(false);
        setSuggNums([]);
        setAucunNum(false);
    };

    const chercherVilles = (brut) => {
        const v = brut.toLowerCase();
        setVille(v);
        setCoords(null);
        setVilleCoords(null);
        setRue('');
        setRueChoisie(false);
        setSuggRues([]);
        setAucuneVille(false);
        setAucuneRue(false);
        resetCp();
        resetNumero();
        publier({ ville: v, coords: null, rue: '', cp: '', numero: '' });
        clearTimeout(timer.current);
        if (v.length < 2) { setSuggVilles(villesProposees(v)); return; }
        timer.current = setTimeout(async () => {
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
                if (res.length === 0) {
                    res = await photon(v, '&layer=city&layer=district', pays);
                }
                setSuggVilles(res);
                setAucuneVille(res.length === 0);
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
        setSuggVilles([]);
        setAucuneVille(false);
        setRue('');
        setRueChoisie(false);
        setCoords(null);
        setSuggRues([]);
        setAucuneRue(false);
        resetNumero();
        publier({ ville: p.name, rue: '', numero: '', coords: null });
        resetCp();
        if (p.postcode) {
            const propre = formatCp(p.postcode);
            setCp(propre);
            setCpChoisi(true);
        }
        (async () => {
            try {
                const liste = [];
                try {
                    const r = await fetch(`/geo/codes-postaux?pays=${pays}&ville=${encodeURIComponent(p.name)}&lat=${lat}&lng=${lng}`);
                    if (r.ok) {
                        (await r.json()).forEach((c) => {
                            const propre = formatCp(String(c.code));
                            if (propre && propre.length >= 3 && !liste.some((x) => x.cp === propre)) {
                                liste.push({ cp: propre, localite: c.ville || '', f: { properties: {}, geometry: { coordinates: [Number(c.lng), Number(c.lat)] } } });
                            }
                        });
                    }
                } catch {
                }
                if (liste.length === 0) {
                    const centre = `&lat=${lat}&lon=${lng}`;
                    const lots = await Promise.all([
                        photon(p.name, centre, pays, 50),
                        photon(p.name, `${centre}&layer=street`, pays, 50),
                        photon(p.name, `${centre}&layer=house`, pays, 50),
                        photon(p.name, `${centre}&layer=district&layer=locality`, pays, 50),
                    ].map((prom) => prom.catch(() => [])));
                    lots.flat().forEach((g) => {
                        const code = (g.properties.postcode || '').split(';')[0].trim();
                        if (!code) return;
                        const [glng, glat] = g.geometry.coordinates;
                        const memeVille = (g.properties.city || '').toLowerCase() === p.name.toLowerCase();
                        if (!memeVille && kmEntre(lat, lng, glat, glng) > 10) return;
                        const propre = formatCp(code);
                        if (propre && propre.length >= 3 && !liste.some((c) => c.cp === propre)) liste.push({ cp: propre, localite: g.properties.city || '', f: g });
                    });
                }
                liste.sort((a, b) => a.cp.localeCompare(b.cp, 'fr', { numeric: true }));
                setCpsDispo(liste);
                setCpsLibres(liste.length === 0);
            } catch {
                setCpsLibres(true);
            }
        })();
    };

    const chercherCps = (brut) => {
        const v = formatCp(brut);
        setCp(v);
        setCpChoisi(false);
        setAucunCp(false);
        if (cpsLibres) {
            publier({ cp: v });
            return;
        }
        publier({ cp: '' });
        const liste = cpsDispo.filter((c) => c.cp.startsWith(v));
        setSuggCps(liste);
        setAucunCp(v.length > 0 && liste.length === 0);
    };

    const choisirCp = ({ cp: code, localite, f }) => {
        const [lng, lat] = f.geometry.coordinates;
        setCp(code);
        setCpLocalite(localite || '');
        setCpChoisi(true);
        setVilleCoords({ lat, lng });
        setSuggCps([]);
        setAucunCp(false);
        publier({ cp: code });
    };

    const chercherRues = (brut) => {
        const v = brut.toLowerCase();
        setRue(v);
        setRueChoisie(false);
        setCoords(null);
        setAucuneRue(false);
        resetNumero();
        publier({ rue: v, coords: null, numero: '' });
        clearTimeout(timer.current);
        if (v.length < 2) { setSuggRues([]); return; }
        timer.current = setTimeout(async () => {
            try {
                const centre = villeCoords ? `&lat=${villeCoords.lat}&lon=${villeCoords.lng}` : '';
                let res = await photon(`${v} ${cpLocalite || ville}`, `&layer=street${centre}`, pays, 30);
                if (res.length === 0) {
                    res = await photon(v, `&layer=street${centre}`, pays, 30);
                }
                if (villeCoords) {
                    res = res.filter((f) => {
                        const [flng, flat] = f.geometry.coordinates;
                        return kmEntre(villeCoords.lat, villeCoords.lng, flat, flng) <= 25;
                    });
                }
                const vus = new Set();
                res = res.filter((f) => {
                    const cle = `${f.properties.name}|${f.properties.postcode || ''}`.toLowerCase();
                    if (vus.has(cle)) return false;
                    vus.add(cle);
                    return true;
                });
                if (cp) {
                    const memeCp = (f) => (f.properties.postcode || '').split(';').some((c) => c.trim() === cp);
                    res = [...res].sort((a, b) => (memeCp(b) ? 1 : 0) - (memeCp(a) ? 1 : 0));
                }
                setSuggRues(res.slice(0, 8));
                setAucuneRue(res.length === 0);
            } catch {
                setSuggRues([]);
            }
        }, 300);
    };

    const choisirRue = (f) => {
        const p = f.properties;
        const [lng, lat] = f.geometry.coordinates;
        setRue(p.name);
        setRueChoisie(true);
        setCoords({ lat, lng });
        setSuggRues([]);
        setAucuneRue(false);
        resetNumero();
        if (p.city) setCpLocalite(p.city);
        let cpRue = cp;
        if (p.postcode) {
            cpRue = formatCp(p.postcode);
            setCp(cpRue);
            setCpChoisi(true);
        }
        publier({ rue: p.name, cp: cpRue, coords: { lat, lng }, numero: '' });
        chargerNumeros(p.name, lat, lng, cpRue);
    };

    const chercherNums = (brut) => {
        const v = formatNumero(brut);
        setNumero(v);
        numeroRef.current = v;
        setNumChoisi(false);
        setAucunNum(false);
        publier({ numero: '' });
        if (numsDispo.length > 0) {
            const liste = numsDispo.filter((n) => n.hn.toLowerCase().startsWith(v.toLowerCase()));
            setSuggNums(liste.slice(0, 30));
            setAucunNum(v.length > 0 && liste.length === 0);
            return;
        }
        setSuggNums([]);
        if (numsChargement || v.length === 0) return;
        clearTimeout(timer.current);
        timer.current = setTimeout(async () => {
            try {
                const centre = coords ? `&lat=${coords.lat}&lon=${coords.lng}` : '';
                const res = await photon(`${rue} ${v} ${cpLocalite || ville}`.replace(/\s+/g, ' '), `&layer=house${centre}`, pays, 30);
                const liste = [];
                res.forEach((f) => {
                    const hn = f.properties.housenumber;
                    if (!hn) return;
                    if (sansAccents(f.properties.street || '') !== sansAccents(rue)) return;
                    if (!hn.toLowerCase().startsWith(v.toLowerCase())) return;
                    const [flng, flat] = f.geometry.coordinates;
                    if (!liste.some((n) => n.hn === hn)) liste.push({ hn, lat: flat, lng: flng, pc: f.properties.postcode || '' });
                });
                liste.sort((a, b) => (parseInt(a.hn) - parseInt(b.hn)) || a.hn.localeCompare(b.hn));
                setSuggNums(liste.slice(0, 30));
                setAucunNum(liste.length === 0);
            } catch {
                setSuggNums([]);
            }
        }, 300);
    };

    const chargerNumeros = async (nomRue, lat, lng, cpActuel) => {
        setNumsChargement(true);
        setNumsDispo([]);
        try {
            const params = new URLSearchParams({ rue: nomRue, lat, lng });
            if (cpActuel) params.set('cp', cpActuel);
            const chrono = new AbortController();
            const minuteur = setTimeout(() => chrono.abort(), 25000);
            const r = await fetch(`/geo/numeros?${params}`, { signal: chrono.signal });
            clearTimeout(minuteur);
            if (!r.ok) throw new Error(String(r.status));
            const liste = (await r.json()).map((n) => ({ hn: n.numero, lat: n.lat, lng: n.lng, pc: n.cp }));
            setNumsDispo(liste);
            const attendu = numeroRef.current;
            if (attendu && liste.length > 0) {
                const filtres = liste.filter((n) => n.hn.toLowerCase().startsWith(attendu.toLowerCase()));
                setSuggNums(filtres.slice(0, 30));
                setAucunNum(filtres.length === 0);
            }
        } catch {
            setNumsDispo([]);
        } finally {
            setNumsChargement(false);
        }
    };

    const choisirNum = ({ hn, lat, lng, pc }) => {
        setNumero(hn);
        numeroRef.current = hn;
        setNumChoisi(true);
        setCoords({ lat, lng });
        setSuggNums([]);
        setAucunNum(false);
        let cpNum = cp;
        if (pc) {
            cpNum = formatCp(pc);
            setCp(cpNum);
            setCpChoisi(true);
        }
        publier({ numero: hn, cp: cpNum, coords: { lat, lng } });
    };

    const changerPays = (code) => {
        setPays(code);
        setVille('');
        setVilleCoords(null);
        setRue('');
        setRueChoisie(false);
        setCoords(null);
        setSuggVilles([]);
        setSuggRues([]);
        setAucuneVille(false);
        setAucuneRue(false);
        resetCp();
        resetNumero();
        onChange('');
    };

    return (
        <div>
            <InputLabel>{label}{required && <span className="text-status-incident"> *</span>}</InputLabel>
            <div className="mt-2 space-y-3">
                <div>
                    <span className={sousLabel}>Pays <span className="text-status-incident">*</span></span>
                    <select value={pays} onChange={(e) => changerPays(e.target.value)} className={selectCls}>
                        {PAYS.map((p) => (
                            <option key={p.code} value={p.code}>{p.nom}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <span className={sousLabel}>Ville <span className="text-status-incident">*</span></span>
                    <div className="relative">
                        <TextInput
                            value={ville}
                            onChange={(e) => chercherVilles(e.target.value)}
                            onFocus={afficherVilles}
                            onClick={afficherVilles}
                            onBlur={() => setTimeout(() => setSuggVilles([]), 150)}
                            placeholder="ex. Bruxelles"
                            className="block w-full pr-9"
                            autoComplete="off"
                        />
                        <button
                            type="button"
                            tabIndex={-1}
                            onMouseDown={(e) => {
                                e.preventDefault();
                                if (suggVilles.length > 0) { setSuggVilles([]); return; }
                                setSuggVilles(villesProposees(''));
                            }}
                            className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-marine"
                        >
                            <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.06l3.71-3.83a.75.75 0 1 1 1.08 1.04l-4.25 4.39a.75.75 0 0 1-1.08 0L5.23 8.27a.75.75 0 0 1 .02-1.06Z" clipRule="evenodd" />
                            </svg>
                        </button>
                        {suggVilles.length > 0 && (
                            <ul className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                                {suggVilles.map((f, i) => (
                                    <li key={i}>
                                        <button type="button" onMouseDown={(e) => { e.preventDefault(); choisirVille(f); }} className="block w-full px-4 py-2 text-left text-sm hover:bg-surface">
                                            {f.properties.name}{f.properties.postcode ? ` — ${f.properties.postcode}` : ''}{f.properties.state ? ` · ${f.properties.state}` : ''}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    {aucuneVille && ville.length >= 2 && (
                        <p className="mt-1 text-xs text-status-incident">Aucune ville trouvée en {nomPays} — vérifie l'orthographe ou le pays.</p>
                    )}
                    {!aucuneVille && ville.length >= 2 && !villeCoords && suggVilles.length === 0 && (
                        <p className="mt-1 text-xs text-slate-400">Choisis la ville dans la liste de suggestions.</p>
                    )}
                </div>
                <div>
                    <span className={sousLabel}>Code postal <span className="text-status-incident">*</span></span>
                    <div className="relative">
                        <TextInput
                            value={cp}
                            onChange={(e) => chercherCps(e.target.value)}
                            onFocus={afficherCps}
                            onClick={afficherCps}
                            onBlur={() => setTimeout(() => setSuggCps([]), 150)}
                            placeholder={CP_EXEMPLE[pays] ?? 'ex. 1000'}
                            className="block w-full pr-9"
                            inputMode={CP_NUMERIQUE[pays] ? 'numeric' : 'text'}
                            autoComplete="off"
                            disabled={!villeCoords}
                        />
                        {villeCoords && !cpsLibres && (
                            <button
                                type="button"
                                tabIndex={-1}
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    if (suggCps.length > 0) { setSuggCps([]); return; }
                                    setSuggCps(cpsDispo);
                                }}
                                className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-marine"
                            >
                                <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.06l3.71-3.83a.75.75 0 1 1 1.08 1.04l-4.25 4.39a.75.75 0 0 1-1.08 0L5.23 8.27a.75.75 0 0 1 .02-1.06Z" clipRule="evenodd" />
                                </svg>
                            </button>
                        )}
                        {suggCps.length > 0 && (
                            <ul className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                                {suggCps.map((c) => (
                                    <li key={c.cp}>
                                        <button type="button" onMouseDown={(e) => { e.preventDefault(); choisirCp(c); }} className="block w-full px-4 py-2 text-left text-sm hover:bg-surface">
                                            {c.cp}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    {aucunCp && (
                        <p className="mt-1 text-xs text-status-incident">Code postal introuvable pour cette ville — choisis-en un dans la liste.</p>
                    )}
                    {villeCoords && !cpsLibres && cp && !cpChoisi && !aucunCp && suggCps.length === 0 && (
                        <p className="mt-1 text-xs text-slate-400">Choisis un code postal dans la liste de suggestions.</p>
                    )}
                    {villeCoords && cpsLibres && (
                        <p className="mt-1 text-xs text-slate-400">Codes postaux non référencés pour cette ville — saisie libre.</p>
                    )}
                </div>
                <div>
                    <div className="flex gap-2">
                        <div className="relative flex-1">
                            <span className={sousLabel}>Rue <span className="text-status-incident">*</span></span>
                            <TextInput
                                value={rue}
                                onChange={(e) => chercherRues(e.target.value)}
                                onFocus={() => { if (rue.length >= 2 && !rueChoisie) chercherRues(rue); }}
                                onBlur={() => setTimeout(() => setSuggRues([]), 150)}
                                placeholder="ex. Rue de la Loi"
                                className="block w-full"
                                autoComplete="off"
                                disabled={!villeCoords}
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
                        <div className="w-24">
                            <span className={sousLabel}>N° <span className="text-status-incident">*</span></span>
                            <div className="relative">
                            <TextInput
                                value={numero}
                                onChange={(e) => chercherNums(e.target.value)}
                                onFocus={afficherNums}
                                onClick={afficherNums}
                                onBlur={() => setTimeout(() => setSuggNums([]), 150)}
                                placeholder="ex. 16"
                                className="block w-full pr-7"
                                autoComplete="off"
                                inputMode="numeric"
                                disabled={!rueChoisie}
                            />
                            {rueChoisie && numsDispo.length > 0 && (
                                <button
                                    type="button"
                                    tabIndex={-1}
                                    onMouseDown={(e) => {
                                        e.preventDefault();
                                        if (suggNums.length > 0) { setSuggNums([]); return; }
                                        setSuggNums(numsDispo.slice(0, 30));
                                    }}
                                    className="absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400 hover:text-marine"
                                >
                                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.06l3.71-3.83a.75.75 0 1 1 1.08 1.04l-4.25 4.39a.75.75 0 0 1-1.08 0L5.23 8.27a.75.75 0 0 1 .02-1.06Z" clipRule="evenodd" />
                                    </svg>
                                </button>
                            )}
                            {suggNums.length > 0 && (
                                <ul className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                                    {suggNums.map((n) => (
                                        <li key={n.hn}>
                                            <button type="button" onMouseDown={(e) => { e.preventDefault(); choisirNum(n); }} className="block w-full px-3 py-2 text-left text-sm hover:bg-surface">
                                                {n.hn}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            </div>
                        </div>
                    </div>
                    {aucuneRue && rue.length >= 2 && (
                        <p className="mt-1 text-xs text-status-incident">Aucune rue trouvée à {cpLocalite || ville || nomPays} — écris le nom complet (ex. « champ de mars ») et vérifie l'orthographe.</p>
                    )}
                    {!aucuneRue && rue.length >= 2 && !rueChoisie && suggRues.length === 0 && (
                        <p className="mt-1 text-xs text-slate-400">Choisis la rue dans la liste de suggestions.</p>
                    )}
                    {numsChargement && (
                        <p className="mt-1 text-xs text-slate-400">Chargement des numéros de la rue…</p>
                    )}
                    {aucunNum && (
                        <p className="mt-1 text-xs text-status-incident">Numéro introuvable dans cette rue — seuls les numéros existants sont proposés.</p>
                    )}
                    {rueChoisie && numero && !numChoisi && !aucunNum && !numsChargement && suggNums.length === 0 && (
                        <p className="mt-1 text-xs text-slate-400">Choisis le numéro dans la liste.</p>
                    )}
                </div>
            </div>
            <InputError message={error} className="mt-2" />
        </div>
    );
}
