import 'leaflet/dist/leaflet.css';

import L from 'leaflet';
import { useEffect, useRef } from 'react';

const COULEUR = {
    IN_PROGRESS: '#0B61A1',
    PENDING: '#8A9099',
    DELIVERED: '#15803D',
    CANCELLED: '#BA1A1A',
};

// Le trace de l'expedition consultee : un trait noir sur un fond gris
// desature, comme les applications de course. Le fond ne raconte rien, la
// route est le seul element qui ressort.
const TRACE = '#111827';
const HALO = '#FFFFFF';

// Point de depart plein, arrivee carree : deux formes distinctes se lisent
// plus vite que deux couleurs.
const MARQUEUR_DEPART = L.divIcon({
    className: '',
    iconSize: [16, 16],
    iconAnchor: [8, 8],
    html: `<span style="display:block;width:16px;height:16px;border-radius:9999px;background:${TRACE};border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></span>`,
});

const MARQUEUR_ARRIVEE = L.divIcon({
    className: '',
    iconSize: [18, 18],
    iconAnchor: [9, 9],
    html: `<span style="display:block;width:18px;height:18px;border-radius:4px;background:${TRACE};border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></span>`,
});

const ICONE_PEAGE = L.divIcon({
    className: '',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
    html: '<span style="display:flex;width:22px;height:22px;align-items:center;justify-content:center;border-radius:9999px;background:#F59E0B;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35);font:700 12px/1 system-ui,sans-serif;color:#3B2600">€</span>',
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

        // Fond desature : le rendu OpenStreetMap standard charge trop de
        // couleurs pour qu'un trace ressorte.
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
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
            const actif = trajet.id === selection;

            // Des qu'une expedition est ouverte, les autres disparaissent.
            // Leur liaison directe traversait la carte en diagonale et
            // pouvait se lire comme une portion de l'itineraire affiche.
            if (choisi && ! actif) {
                return;
            }

            const [depart, arrivee] = trajet.coordonnees;
            const couleur = COULEUR[trajet.statut] ?? COULEUR.PENDING;
            const etiquette = `${trajet.numero} · ${trajet.depart} → ${trajet.arrivee}`;

            // L'itineraire routier n'arrive qu'apres coup : tant qu'il manque,
            // la liaison directe tient la place.
            const trace = trajet.trace?.length ? trajet.trace : [depart, arrivee];
            const routier = Boolean(trajet.trace?.length);

            if (routier) {
                // Halo blanc sous le trait : il detache la route des voies
                // grises du fond, qui ont parfois la meme epaisseur.
                L.polyline(trace, {
                    color: HALO,
                    weight: actif ? 9 : 6,
                    opacity: 1,
                    lineCap: 'round',
                    lineJoin: 'round',
                }).addTo(couche.current);
            }

            const ligne = L.polyline(trace, {
                color: routier ? TRACE : couleur,
                weight: actif ? 5 : routier ? 3 : 2,
                opacity: actif || routier ? 1 : 0.7,
                lineCap: 'round',
                lineJoin: 'round',
            }).bindTooltip(etiquette, { sticky: true }).addTo(couche.current);

            ligne.on('click', () => clic.current?.(trajet.id));

            if (routier) {
                L.marker(trace[0], { icon: MARQUEUR_DEPART })
                    .bindTooltip(`Enlèvement · ${trajet.depart}`)
                    .addTo(couche.current)
                    .on('click', () => clic.current?.(trajet.id));
                L.marker(trace[trace.length - 1], { icon: MARQUEUR_ARRIVEE })
                    .bindTooltip(`Livraison · ${trajet.arrivee}`)
                    .addTo(couche.current)
                    .on('click', () => clic.current?.(trajet.id));
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
                        fillOpacity: 1,
                    })
                        .bindTooltip(index === 0 ? `Enlèvement · ${trajet.depart}` : `Livraison · ${trajet.arrivee}`)
                        .addTo(couche.current)
                        .on('click', () => clic.current?.(trajet.id));
                });

                cadre.push(depart, arrivee);
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
    }, [trajets, selection, peages]);

    return <div ref={conteneur} className={className} />;
}
