import 'leaflet/dist/leaflet.css';

import L from 'leaflet';
import { useEffect, useRef } from 'react';

const COULEUR = {
    IN_PROGRESS: '#0B61A1',
    PENDING: '#8A9099',
    DELIVERED: '#15803D',
    CANCELLED: '#BA1A1A',
};

const TRACE = '#111827';
const HALO = '#FFFFFF';

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

const ICONE_POSITION = L.divIcon({
    className: '',
    iconSize: [26, 26],
    iconAnchor: [13, 13],
    html: '<span style="display:block;width:26px;height:26px;border-radius:9999px;background:rgba(37,99,235,.25);border:2px solid #2563EB">'
        + '<span style="display:block;width:10px;height:10px;margin:6px;border-radius:9999px;background:#2563EB"></span></span>',
});

export default function CarteTrajets({
    trajets = [],
    selection = null,
    onSelection,
    peages = [],

    jalons = [],
    position = null,
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

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 19,
        }).addTo(c);

        couche.current = L.layerGroup().addTo(c);
        carte.current = c;

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

            if (choisi && ! actif) {
                return;
            }

            const [depart, arrivee] = trajet.coordonnees;
            const couleur = COULEUR[trajet.statut] ?? COULEUR.PENDING;
            const etiquette = `${trajet.numero} · ${trajet.depart} → ${trajet.arrivee}`;

            const trace = trajet.trace?.length ? trajet.trace : [depart, arrivee];
            const routier = Boolean(trajet.trace?.length);

            if (routier) {

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

        jalons.forEach((jalon) => {
            L.circleMarker(jalon.coordonnees, {
                radius: 7,
                color: HALO,
                weight: 2,
                fillColor: jalon.evenement === 'DELIVERED' ? '#15803D' : '#2563EB',
                fillOpacity: 1,
            })
                .bindTooltip(`<strong>${jalon.libelle}</strong><br>${jalon.localite} — ${jalon.horodatage}`)
                .addTo(couche.current);

            cadre.push(jalon.coordonnees);
        });

        if (position) {
            L.marker(position.coordonnees, { icon: ICONE_POSITION, zIndexOffset: 800 })
                .bindTooltip(`<strong>En route</strong><br>${position.horodatage}`)
                .addTo(couche.current);

            cadre.push(position.coordonnees);
        }

        if (cadre.length > 0) {
            c.fitBounds(L.latLngBounds(cadre), {
                padding: [40, 40],
                maxZoom: choisi ? 12 : 8,
            });
        }
    }, [trajets, selection, peages, jalons, position]);

    return <div ref={conteneur} className={`isolate ${className ?? ''}`} />;
}
