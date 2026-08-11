import 'leaflet/dist/leaflet.css';

import L from 'leaflet';
import { useEffect, useRef } from 'react';

const COULEUR = {
    IN_PROGRESS: '#0B61A1',
    PENDING: '#43474D',
    DELIVERED: '#15803D',
    CANCELLED: '#BA1A1A',
};

// Le trace de l'expedition consultee, dessine comme sur un GPS : un liseré
// sombre sous un ruban clair, bouts arrondis.
const LISERE = '#0A2E52';
const RUBAN = '#2D9CFF';

function marqueurDepart(couleur) {
    return L.divIcon({
        className: '',
        iconSize: [18, 18],
        iconAnchor: [9, 9],
        html: `<span style="display:block;width:18px;height:18px;border-radius:9999px;background:#fff;border:4px solid ${couleur};box-shadow:0 1px 4px rgba(0,0,0,.4)"></span>`,
    });
}

function marqueurArrivee(couleur) {
    return L.divIcon({
        className: '',
        iconSize: [26, 34],
        iconAnchor: [13, 34],
        html: `<span style="display:block;width:26px;height:26px;border-radius:9999px 9999px 9999px 2px;transform:rotate(45deg);background:${couleur};border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.45)"></span>`,
    });
}

const ICONE_PEAGE = L.divIcon({
    className: '',
    iconSize: [24, 24],
    iconAnchor: [12, 12],
    html: '<span style="display:flex;width:24px;height:24px;align-items:center;justify-content:center;border-radius:9999px;background:#F59E0B;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);font:700 13px/1 system-ui,sans-serif;color:#3B2600">€</span>',
});

/**
 * Carte des expeditions.
 *
 * Leaflet gere son propre DOM : React ne fournit que le conteneur, tout le
 * reste passe par les couches Leaflet. Les expeditions non consultees sont
 * reduites a une liaison directe entre leurs deux points ; celle qu'on
 * consulte recoit son itineraire routier reel et ses peages.
 */
export default function CarteTrajets({
    trajets = [],
    selection = null,
    onSelection,
    itineraire = null,
    peages = [],
    className = '',
}) {
    const conteneur = useRef(null);
    const carte = useRef(null);
    const couche = useRef(null);
    const clic = useRef(onSelection);

    clic.current = onSelection;

    useEffect(() => {
        const c = L.map(conteneur.current, {
            scrollWheelZoom: false,
            zoomControl: true,
        }).setView([50.5, 4.5], 7);

        // Fond epure : le rendu OpenStreetMap standard charge trop de details
        // pour qu'un trace ressorte.
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 19,
        }).addTo(c);

        couche.current = L.layerGroup().addTo(c);
        carte.current = c;

        // Le panneau de gauche fait varier la largeur disponible : sans ce
        // recalcul, Leaflet garde l'ancienne taille et les tuiles se decalent.
        const observateur = new ResizeObserver(() => c.invalidateSize());
        observateur.observe(conteneur.current);

        return () => {
            observateur.disconnect();
            c.remove();
            carte.current = null;
        };
    }, []);

    useEffect(() => {
        const c = carte.current;

        if (! c) {
            return;
        }

        couche.current.clearLayers();

        const choisi = trajets.some((t) => t.id === selection);
        const cadre = [];

        trajets.forEach((trajet) => {
            const [depart, arrivee] = trajet.coordonnees;
            const actif = trajet.id === selection;
            const vise = ! choisi || actif;
            const couleur = COULEUR[trajet.statut] ?? COULEUR.PENDING;
            const etiquette = `${trajet.numero} · ${trajet.depart} → ${trajet.arrivee}`;

            // L'itineraire routier n'arrive qu'apres coup : tant qu'il manque,
            // la liaison directe tient la place.
            const trace = actif && itineraire?.geometrie ? itineraire.geometrie : [depart, arrivee];

            if (actif) {
                L.polyline(trace, {
                    color: LISERE,
                    weight: 10,
                    opacity: 1,
                    lineCap: 'round',
                    lineJoin: 'round',
                }).addTo(couche.current);
            }

            const ligne = L.polyline(trace, {
                color: actif ? RUBAN : couleur,
                weight: actif ? 6 : 2,
                opacity: vise ? (actif ? 1 : 0.75) : 0.15,
                lineCap: 'round',
                lineJoin: 'round',
                interactive: vise,
            }).bindTooltip(etiquette, { sticky: true }).addTo(couche.current);

            ligne.on('click', () => clic.current?.(trajet.id));

            if (actif) {
                L.marker(trace[0], { icon: marqueurDepart(RUBAN) })
                    .bindTooltip(`Enlèvement · ${trajet.depart}`)
                    .addTo(couche.current);
                L.marker(trace[trace.length - 1], { icon: marqueurArrivee(LISERE) })
                    .bindTooltip(`Livraison · ${trajet.arrivee}`)
                    .addTo(couche.current);
                trace.forEach((point) => cadre.push(point));
            } else {
                // Cercles plutot que les marqueurs par defaut : ceux-ci
                // chargent leurs images par une URL relative que Vite ne
                // resout pas.
                [depart, arrivee].forEach((point, index) => {
                    L.circleMarker(point, {
                        radius: index === 0 ? 4 : 5,
                        color: index === 0 ? '#ffffff' : couleur,
                        weight: index === 0 ? 2 : 3,
                        fillColor: index === 0 ? couleur : '#ffffff',
                        fillOpacity: vise ? 1 : 0.2,
                        interactive: vise,
                    })
                        .bindTooltip(index === 0 ? `Enlèvement · ${trajet.depart}` : `Livraison · ${trajet.arrivee}`)
                        .addTo(couche.current)
                        .on('click', () => clic.current?.(trajet.id));
                });

                if (vise) {
                    cadre.push(depart, arrivee);
                }
            }
        });

        peages.forEach((peage) => {
            L.marker([peage.lat, peage.lng], { icon: ICONE_PEAGE, zIndexOffset: 500 })
                .bindTooltip(
                    `<strong>${peage.portique ? 'Portique' : 'Péage'} ${peage.nom}</strong>${peage.route ? '<br>' + peage.route : ''}`,
                )
                .addTo(couche.current);
        });

        if (cadre.length > 0) {
            c.fitBounds(L.latLngBounds(cadre), {
                padding: [40, 40],
                maxZoom: choisi ? 12 : 8,
            });
        }
    }, [trajets, selection, itineraire, peages]);

    return <div ref={conteneur} className={className} />;
}
