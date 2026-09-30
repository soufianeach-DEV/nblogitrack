// Fond de carte vectoriel aux couleurs de Waze : terre creme, parcs
// menthe, eau bleu clair, autoroutes orangees, rues blanches bordees de
// gris. Les tuiles viennent d'OpenFreeMap (libre, sans cle ni quota) au
// schema OpenMapTiles.

export const TUILES = 'https://tiles.openfreemap.org';

export const ATTRIBUTION = '<a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> '
    + '&copy; <a href="https://www.openmaptiles.org/" target="_blank" rel="noopener">OpenMapTiles</a> '
    + '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';

export const TEINTES = {
    terre: '#F3F0E8',
    ville: '#ECE7DC',
    industrie: '#E8E4E9',
    parc: '#CFEBC3',
    bois: '#C3E3B5',
    herbe: '#DCEFD1',
    eau: '#A7D8F7',
    eauTexte: '#3C7FB8',
    batiment: '#E2DCD0',
    batimentBord: '#D4CDBF',
    frontiere: '#B8A9CF',
    rail: '#CBC6BD',
    texte: '#3F4650',
    texteDoux: '#6B7280',
    halo: '#FFFFFF',
};

// [trait, bordure] par categorie de route.
const ROUTES = {
    motorway: ['#FFC04D', '#E39A2D'],
    trunk: ['#FFD978', '#E2B24C'],
    primary: ['#FFEBAD', '#DDC172'],
    secondary: ['#FFFFFF', '#D2CAB8'],
    tertiary: ['#FFFFFF', '#D2CAB8'],
    minor: ['#FFFFFF', '#DAD4C8'],
    service: ['#FFFFFF', '#DFDAD0'],
};

// Largeur du trait selon le zoom, pour chaque categorie.
const LARGEURS = {
    motorway: [[5, 0.8], [8, 1.8], [12, 4], [16, 14], [20, 40]],
    trunk: [[5, 0.6], [8, 1.4], [12, 3.4], [16, 12], [20, 34]],
    primary: [[7, 0.6], [10, 1.4], [12, 2.8], [16, 10], [20, 30]],
    secondary: [[9, 0.6], [12, 2.2], [16, 9], [20, 26]],
    tertiary: [[10, 0.5], [12, 1.8], [16, 8], [20, 24]],
    minor: [[12, 0.6], [14, 2.2], [16, 6], [20, 20]],
    service: [[14, 0.6], [16, 3], [20, 12]],
};

const ZOOM_MIN = { motorway: 4, trunk: 5, primary: 7, secondary: 9, tertiary: 10, minor: 12, service: 14 };

const largeur = (paliers, facteur = 1, ajout = 0) => [
    'interpolate', ['exponential', 1.4], ['zoom'],
    ...paliers.flatMap(([z, l]) => [z, l * facteur + ajout]),
];

// Classes OpenMapTiles couvertes par chaque categorie.
const CLASSES = {
    motorway: ['motorway'],
    trunk: ['trunk'],
    primary: ['primary'],
    secondary: ['secondary'],
    tertiary: ['tertiary'],
    minor: ['minor'],
    service: ['service', 'track'],
};

const POLICE = ['Noto Sans Regular'];
const POLICE_GRASSE = ['Noto Sans Bold'];
const POLICE_ITALIQUE = ['Noto Sans Italic'];

// Nom dans la langue de l'interface quand OpenStreetMap le connait :
// « Bruxelles » en francais, « Brussel » en neerlandais.
const nom = (langue) => ['coalesce', ['get', `name:${langue}`], ['get', 'name_int'], ['get', 'name']];

function routes(tunnel) {
    const filtre = (cle) => [
        'all',
        ['match', ['get', 'class'], CLASSES[cle], true, false],
        tunnel ? ['==', ['get', 'brunnel'], 'tunnel'] : ['!=', ['get', 'brunnel'], 'tunnel'],
    ];
    const ordre = ['service', 'minor', 'tertiary', 'secondary', 'primary', 'trunk', 'motorway'];
    const suffixe = tunnel ? '-tunnel' : '';

    // Toutes les bordures d'abord, puis tous les traits : les carrefours
    // se fondent au lieu d'etre coupes par la bordure d'une autre route.
    const bordures = ordre.map((cle) => ({
        id: `route-${cle}-bord${suffixe}`,
        type: 'line',
        source: 'openmaptiles',
        'source-layer': 'transportation',
        minzoom: ZOOM_MIN[cle],
        filter: filtre(cle),
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: {
            'line-color': ROUTES[cle][1],
            'line-width': largeur(LARGEURS[cle], 1, 1.6),
            'line-opacity': tunnel ? 0.45 : 1,
        },
    }));

    const traits = ordre.map((cle) => ({
        id: `route-${cle}${suffixe}`,
        type: 'line',
        source: 'openmaptiles',
        'source-layer': 'transportation',
        minzoom: ZOOM_MIN[cle],
        filter: filtre(cle),
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: {
            'line-color': ROUTES[cle][0],
            'line-width': largeur(LARGEURS[cle]),
            'line-opacity': tunnel ? 0.6 : 1,
        },
    }));

    return [...bordures, ...traits];
}

export function styleWaze(langue = 'fr') {
    const n = nom(langue);

    return {
        version: 8,
        name: 'Waze',
        glyphs: `${TUILES}/fonts/{fontstack}/{range}.pbf`,
        sources: {
            openmaptiles: { type: 'vector', url: `${TUILES}/planet` },
        },
        layers: [
            { id: 'fond', type: 'background', paint: { 'background-color': TEINTES.terre } },
            {
                id: 'ville',
                type: 'fill',
                source: 'openmaptiles',
                'source-layer': 'landuse',
                filter: ['match', ['get', 'class'], ['residential', 'suburb', 'neighbourhood', 'commercial', 'retail'], true, false],
                paint: { 'fill-color': TEINTES.ville, 'fill-opacity': ['interpolate', ['linear'], ['zoom'], 6, 0.6, 12, 1] },
            },
            {
                id: 'industrie',
                type: 'fill',
                source: 'openmaptiles',
                'source-layer': 'landuse',
                filter: ['match', ['get', 'class'], ['industrial', 'railway', 'quarry'], true, false],
                paint: { 'fill-color': TEINTES.industrie },
            },
            {
                id: 'bois',
                type: 'fill',
                source: 'openmaptiles',
                'source-layer': 'landcover',
                filter: ['match', ['get', 'class'], ['wood', 'forest'], true, false],
                paint: { 'fill-color': TEINTES.bois, 'fill-opacity': 0.8 },
            },
            {
                id: 'herbe',
                type: 'fill',
                source: 'openmaptiles',
                'source-layer': 'landcover',
                filter: ['match', ['get', 'class'], ['grass', 'wetland'], true, false],
                paint: { 'fill-color': TEINTES.herbe, 'fill-opacity': 0.7 },
            },
            {
                id: 'parc',
                type: 'fill',
                source: 'openmaptiles',
                'source-layer': 'park',
                paint: { 'fill-color': TEINTES.parc, 'fill-opacity': 0.85 },
            },
            {
                id: 'eau',
                type: 'fill',
                source: 'openmaptiles',
                'source-layer': 'water',
                filter: ['!=', ['get', 'brunnel'], 'tunnel'],
                paint: { 'fill-color': TEINTES.eau },
            },
            {
                id: 'cours-d-eau',
                type: 'line',
                source: 'openmaptiles',
                'source-layer': 'waterway',
                minzoom: 8,
                filter: ['!=', ['get', 'brunnel'], 'tunnel'],
                layout: { 'line-cap': 'round' },
                paint: {
                    'line-color': TEINTES.eau,
                    'line-width': ['interpolate', ['exponential', 1.4], ['zoom'], 8, 0.6, 14, 2.5, 20, 10],
                },
            },
            {
                id: 'aeroport',
                type: 'fill',
                source: 'openmaptiles',
                'source-layer': 'aeroway',
                minzoom: 11,
                filter: ['==', ['geometry-type'], 'Polygon'],
                paint: { 'fill-color': '#E6E3EC' },
            },
            {
                id: 'batiment',
                type: 'fill',
                source: 'openmaptiles',
                'source-layer': 'building',
                minzoom: 14,
                paint: {
                    'fill-color': TEINTES.batiment,
                    'fill-outline-color': TEINTES.batimentBord,
                    'fill-opacity': ['interpolate', ['linear'], ['zoom'], 14, 0, 15.5, 1],
                },
            },
            {
                id: 'frontiere',
                type: 'line',
                source: 'openmaptiles',
                'source-layer': 'boundary',
                filter: ['all', ['==', ['get', 'admin_level'], 2], ['!=', ['get', 'maritime'], 1]],
                layout: { 'line-join': 'round' },
                paint: {
                    'line-color': TEINTES.frontiere,
                    'line-width': ['interpolate', ['linear'], ['zoom'], 3, 0.8, 10, 2],
                    'line-dasharray': [3, 2],
                },
            },
            ...routes(true),
            {
                id: 'rail',
                type: 'line',
                source: 'openmaptiles',
                'source-layer': 'transportation',
                minzoom: 11,
                filter: ['all', ['match', ['get', 'class'], ['rail', 'transit'], true, false], ['!=', ['get', 'brunnel'], 'tunnel']],
                paint: {
                    'line-color': TEINTES.rail,
                    'line-width': ['interpolate', ['linear'], ['zoom'], 11, 0.8, 18, 2.5],
                    'line-dasharray': [4, 3],
                },
            },
            {
                id: 'ferry',
                type: 'line',
                source: 'openmaptiles',
                'source-layer': 'transportation',
                minzoom: 6,
                filter: ['==', ['get', 'class'], 'ferry'],
                paint: { 'line-color': '#6FB1E8', 'line-width': 1.2, 'line-dasharray': [3, 3] },
            },
            ...routes(false),
            {
                id: 'eau-nom',
                type: 'symbol',
                source: 'openmaptiles',
                'source-layer': 'water_name',
                filter: ['==', ['geometry-type'], 'Point'],
                layout: {
                    'text-field': n,
                    'text-font': POLICE_ITALIQUE,
                    'text-size': 12,
                },
                paint: { 'text-color': TEINTES.eauTexte, 'text-halo-color': 'rgba(255,255,255,0.7)', 'text-halo-width': 1 },
            },
            {
                id: 'route-nom',
                type: 'symbol',
                source: 'openmaptiles',
                'source-layer': 'transportation_name',
                minzoom: 13,
                filter: ['match', ['get', 'class'], ['primary', 'secondary', 'tertiary', 'minor'], true, false],
                layout: {
                    'text-field': n,
                    'text-font': POLICE,
                    'text-size': ['interpolate', ['linear'], ['zoom'], 13, 10, 18, 13],
                    'symbol-placement': 'line',
                    'text-rotation-alignment': 'map',
                },
                paint: { 'text-color': TEINTES.texteDoux, 'text-halo-color': TEINTES.halo, 'text-halo-width': 1.5 },
            },
            {
                id: 'route-numero',
                type: 'symbol',
                source: 'openmaptiles',
                'source-layer': 'transportation_name',
                minzoom: 7,
                filter: ['all', ['match', ['get', 'class'], ['motorway', 'trunk'], true, false], ['has', 'ref'], ['<=', ['get', 'ref_length'], 6]],
                layout: {
                    'text-field': ['get', 'ref'],
                    'text-font': POLICE_GRASSE,
                    'text-size': 10,
                    'symbol-placement': 'line',
                    'symbol-spacing': 400,
                    'text-rotation-alignment': 'viewport',
                },
                paint: { 'text-color': '#8A5A00', 'text-halo-color': '#FFF4D6', 'text-halo-width': 2.5 },
            },
            {
                id: 'lieu-quartier',
                type: 'symbol',
                source: 'openmaptiles',
                'source-layer': 'place',
                minzoom: 12,
                filter: ['match', ['get', 'class'], ['suburb', 'neighbourhood', 'quarter'], true, false],
                layout: {
                    'text-field': n,
                    'text-font': POLICE,
                    'text-size': 11,
                    'text-transform': 'uppercase',
                    'text-letter-spacing': 0.08,
                },
                paint: { 'text-color': TEINTES.texteDoux, 'text-halo-color': TEINTES.halo, 'text-halo-width': 1.2 },
            },
            {
                id: 'lieu-village',
                type: 'symbol',
                source: 'openmaptiles',
                'source-layer': 'place',
                minzoom: 10,
                filter: ['match', ['get', 'class'], ['village', 'hamlet'], true, false],
                layout: { 'text-field': n, 'text-font': POLICE, 'text-size': 11 },
                paint: { 'text-color': TEINTES.texte, 'text-halo-color': TEINTES.halo, 'text-halo-width': 1.4 },
            },
            {
                id: 'lieu-ville',
                type: 'symbol',
                source: 'openmaptiles',
                'source-layer': 'place',
                minzoom: 5,
                filter: ['match', ['get', 'class'], ['city', 'town'], true, false],
                layout: {
                    'text-field': n,
                    'text-font': POLICE_GRASSE,
                    'text-size': ['interpolate', ['linear'], ['zoom'], 5, ['match', ['get', 'class'], 'city', 12, 10], 12, ['match', ['get', 'class'], 'city', 18, 14]],
                    'symbol-sort-key': ['get', 'rank'],
                },
                paint: { 'text-color': TEINTES.texte, 'text-halo-color': TEINTES.halo, 'text-halo-width': 1.6 },
            },
            {
                id: 'lieu-pays',
                type: 'symbol',
                source: 'openmaptiles',
                'source-layer': 'place',
                maxzoom: 8,
                filter: ['==', ['get', 'class'], 'country'],
                layout: {
                    'text-field': n,
                    'text-font': POLICE_GRASSE,
                    'text-size': ['interpolate', ['linear'], ['zoom'], 3, 11, 7, 15],
                    'text-transform': 'uppercase',
                    'text-letter-spacing': 0.12,
                },
                paint: { 'text-color': '#8B8FA3', 'text-halo-color': TEINTES.halo, 'text-halo-width': 1.5 },
            },
        ],
    };
}
