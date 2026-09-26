import 'leaflet/dist/leaflet.css';

import L from 'leaflet';
import { useEffect, useRef } from 'react';
import { useLangue, useTraduction } from '@/traduire';
import { TEINTES } from './carte/styleWaze';

const COULEUR = {
    IN_PROGRESS: '#1A7FE0',
    PENDING: '#8C94A3',
    ASSIGNED: '#F09A12',
    DELIVERED: '#1FA95B',
    CANCELLED: '#E5484D',
};

// L'itineraire a la Waze : un ruban bleu vif borde de bleu nuit.
const ITINERAIRE = { coeur: '#3DB2FF', bord: '#0F6BC7' };

// Leaflet ecrit une infobulle donnee sous forme de texte avec innerHTML.
// Les noms de peages viennent d'OpenStreetMap, que n'importe qui peut
// modifier : sans echappement, un nom de portique devenait du balisage
// dans la page. Toute valeur venue des donnees passe par ici.
const echapper = (valeur) => String(valeur ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
const HALO = '#FFFFFF';

const DRAPEAU = '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="#fff" d="M5 3h1.6v18H5z"/>'
    + '<path fill="#fff" d="M7.2 4h12.3l-2.6 4.2 2.6 4.3H7.2z"/><path fill="#0F6BC7" d="M9.5 4h2.3v2.1H9.5zm4.6 0h2.3v2.1h-2.3zM11.8 6.1h2.3v2.1h-2.3zm-2.3 2.1h2.3v2.1H9.5zm4.6 0h2.3v2.1h-2.3zm-2.3 2.1h2.3v2.2h-2.3z"/></svg>';

const CAMION = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="#0F6BC7" d="M2 6.5A1.5 1.5 0 0 1 3.5 5h10A1.5 1.5 0 0 1 15 6.5V8h3.2a1.5 1.5 0 0 1 1.2.6l2.3 3.1c.2.3.3.6.3.9v3.4a1 1 0 0 1-1 1h-1.1a2.9 2.9 0 0 1-5.7 0H9.7a2.9 2.9 0 0 1-5.7 0H3a1 1 0 0 1-1-1z"/>'
    + '<circle cx="6.9" cy="17" r="1.6" fill="#fff"/><circle cx="17.1" cy="17" r="1.6" fill="#fff"/><path fill="#fff" d="M16 9.5h2l1.8 2.5H16z"/></svg>';

// Le depart : une pastille blanche cerclee de bleu, comme le point de
// depart d'un trajet Waze.
const MARQUEUR_DEPART = L.divIcon({
    className: '',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
    html: `<span class="carte-pastille" style="width:22px;height:22px;border:6px solid ${ITINERAIRE.bord}"></span>`,
});

// L'arrivee : une epingle bleue au drapeau a damier.
const MARQUEUR_ARRIVEE = L.divIcon({
    className: '',
    iconSize: [34, 42],
    iconAnchor: [17, 40],
    tooltipAnchor: [0, -30],
    html: `<span class="carte-epingle" style="background:${ITINERAIRE.bord}"><span>${DRAPEAU}</span></span>`,
});

const ICONE_PEAGE = L.divIcon({
    className: '',
    iconSize: [26, 26],
    iconAnchor: [13, 13],
    html: '<span class="carte-bulle" style="background:#FFB020;color:#3B2600">€</span>',
});

// Le camion en route : une bulle blanche qui pulse doucement.
const ICONE_POSITION = L.divIcon({
    className: '',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    html: `<span class="carte-camion"><span class="carte-camion-onde"></span><span class="carte-camion-bulle">${CAMION}</span></span>`,
});

const webgl2 = () => {
    try {
        return Boolean(document.createElement('canvas').getContext('webgl2'));
    } catch {
        return false;
    }
};

// Carte de secours sans WebGL 2, ou si le fond vectoriel ne repond pas.
const coucheRaster = () => L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
});

export default function CarteTrajets({
    trajets = [],
    selection = null,
    onSelection,
    peages = [],

    jalons = [],
    position = null,
    positionZoom = 'topleft',
    className = '',
}) {
    const conteneur = useRef(null);
    const carte = useRef(null);
    const couche = useRef(null);
    const clic = useRef(onSelection);
    const t = useTraduction();
    const langue = useLangue();

    clic.current = onSelection;

    // Les infobulles sont du HTML construit hors de React : les libelles
    // se traduisent ici et figurent dans les dependances de l'effet.
    const enlevement = t('demandes.enlevement', 'Enlèvement');
    const livraison = t('demandes.livraison', 'Livraison');
    const portique = t('carte.portique', 'Portique');
    const libellePeage = t('carte.peage', 'Péage');
    const enRoute = t('suivi.en_route', 'En route');

    useEffect(() => {
        const c = L.map(conteneur.current, {
            scrollWheelZoom: false,
            zoomControl: false,
        }).setView([50.5, 4.5], 7);

        L.control.zoom({ position: positionZoom }).addTo(c);

        couche.current = L.layerGroup().addTo(c);
        carte.current = c;

        let fond = null;
        const secours = () => {
            if (carte.current !== c) {
                return;
            }

            if (fond) {
                c.removeLayer(fond);
            }

            fond = coucheRaster().addTo(c);
        };

        // Le fond facon Waze est vectoriel : il lui faut WebGL 2. Sans lui,
        // ou si les tuiles d'OpenFreeMap ne repondent pas, la carte standard
        // d'OpenStreetMap prend le relais.
        if (webgl2()) {
            import('./carte/fondWaze')
                .then(({ coucheWaze }) => {
                    if (carte.current !== c) {
                        return;
                    }

                    fond = coucheWaze(langue).addTo(c);

                    const gl = fond.getMaplibreMap();
                    let recu = false;

                    gl.on('sourcedata', (e) => {
                        if (e.sourceId === 'openmaptiles' && e.sourceDataType === 'metadata') {
                            recu = true;
                        }
                    });
                    // Seul un echec de la source elle-meme (description des
                    // tuiles injoignable) bascule vers le secours : une tuile
                    // ou une police manquante ne vide pas la carte.
                    gl.on('error', (e) => {
                        if (! recu && e.sourceId === 'openmaptiles') {
                            secours();
                        }
                    });
                })
                .catch(secours);
        } else {
            secours();
        }

        const observateur = new ResizeObserver(() => c.invalidateSize());
        observateur.observe(conteneur.current);

        return () => {
            observateur.disconnect();
            c.remove();
            carte.current = null;
        };
    }, [langue, positionZoom]);

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
            const etiquette = `${echapper(trajet.numero)} · ${echapper(trajet.depart)} → ${echapper(trajet.arrivee)}`;

            const trace = trajet.trace?.length ? trajet.trace : [depart, arrivee];
            const routier = Boolean(trajet.trace?.length);

            if (routier) {
                L.polyline(trace, {
                    color: HALO,
                    weight: actif ? 13 : 9,
                    opacity: 0.9,
                    lineCap: 'round',
                    lineJoin: 'round',
                }).addTo(couche.current);
                L.polyline(trace, {
                    color: ITINERAIRE.bord,
                    weight: actif ? 10 : 7,
                    lineCap: 'round',
                    lineJoin: 'round',
                }).addTo(couche.current);
            } else {
                L.polyline(trace, {
                    color: HALO,
                    weight: actif ? 7 : 5,
                    opacity: 0.85,
                    lineCap: 'round',
                }).addTo(couche.current);
            }

            // A vol d'oiseau, le trait reste en pointilles : ce n'est pas
            // la route suivie.
            const ligne = L.polyline(trace, {
                color: routier ? ITINERAIRE.coeur : couleur,
                weight: routier ? (actif ? 6 : 4) : (actif ? 4 : 3),
                opacity: 1,
                dashArray: routier ? null : '1 7',
                lineCap: 'round',
                lineJoin: 'round',
            }).bindTooltip(etiquette, { sticky: true }).addTo(couche.current);

            ligne.on('click', () => clic.current?.(trajet.id));

            if (routier) {
                L.marker(trace[0], { icon: MARQUEUR_DEPART })
                    .bindTooltip(`${echapper(enlevement)} · ${echapper(trajet.depart)}`)
                    .addTo(couche.current)
                    .on('click', () => clic.current?.(trajet.id));
                L.marker(trace[trace.length - 1], { icon: MARQUEUR_ARRIVEE })
                    .bindTooltip(`${echapper(livraison)} · ${echapper(trajet.arrivee)}`)
                    .addTo(couche.current)
                    .on('click', () => clic.current?.(trajet.id));
                trace.forEach((point) => cadre.push(point));
            } else {

                [depart, arrivee].forEach((point, index) => {
                    L.circleMarker(point, {
                        radius: index === 0 ? 5 : 6,
                        color: index === 0 ? HALO : couleur,
                        weight: 3,
                        fillColor: index === 0 ? couleur : HALO,
                        fillOpacity: 1,
                    })
                        .bindTooltip(index === 0 ? `${echapper(enlevement)} · ${echapper(trajet.depart)}` : `${echapper(livraison)} · ${echapper(trajet.arrivee)}`)
                        .addTo(couche.current)
                        .on('click', () => clic.current?.(trajet.id));
                });

                cadre.push(depart, arrivee);
            }
        });

        peages.forEach((peage) => {
            L.marker([peage.lat, peage.lng], { icon: ICONE_PEAGE, zIndexOffset: 500 })
                .bindTooltip(
                    `<strong>${echapper(peage.portique ? portique : libellePeage)} ${echapper(peage.nom)}</strong>${peage.route ? '<br>' + echapper(peage.route) : ''}`,
                )
                .addTo(couche.current);
        });

        jalons.forEach((jalon) => {
            L.circleMarker(jalon.coordonnees, {
                radius: 7,
                color: HALO,
                weight: 3,
                fillColor: jalon.evenement === 'DELIVERED' ? COULEUR.DELIVERED : ITINERAIRE.bord,
                fillOpacity: 1,
            })
                .bindTooltip(`<strong>${echapper(jalon.libelle)}</strong><br>${echapper(jalon.localite)} — ${echapper(jalon.horodatage)}`)
                .addTo(couche.current);

            cadre.push(jalon.coordonnees);
        });

        if (position) {
            L.marker(position.coordonnees, { icon: ICONE_POSITION, zIndexOffset: 800 })
                .bindTooltip(`<strong>${echapper(enRoute)}</strong><br>${echapper(position.horodatage)}`)
                .addTo(couche.current);

            cadre.push(position.coordonnees);
        }

        if (cadre.length > 0) {
            c.fitBounds(L.latLngBounds(cadre), {
                padding: [40, 40],
                maxZoom: choisi ? 12 : 8,
            });
        }
    }, [trajets, selection, peages, jalons, position, enlevement, livraison, portique, libellePeage, enRoute, langue, positionZoom]);

    return <div ref={conteneur} className={`carte-waze isolate ${className ?? ''}`} style={{ background: TEINTES.terre }} />;
}
