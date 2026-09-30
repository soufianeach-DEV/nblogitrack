import 'maplibre-gl/dist/maplibre-gl.css';

import { maplibreGL } from '@maplibre/maplibre-gl-leaflet';
import { setWorkerUrl } from 'maplibre-gl';
// Le travailleur de MapLibre est servi par l'application elle-meme : la
// politique de securite n'a pas a autoriser de script venu d'ailleurs.
import urlTravailleur from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import { ATTRIBUTION, styleWaze } from './styleWaze';

setWorkerUrl(urlTravailleur);

/**
 * Couche Leaflet dessinant le fond vectoriel facon Waze. Charge a la
 * demande : les navigateurs sans WebGL 2 ne telechargent pas MapLibre.
 */
export function coucheWaze(langue) {
    return maplibreGL({
        style: styleWaze(langue),
        attributionControl: { customAttribution: ATTRIBUTION },
        interactive: false,
    });
}
