import { useEffect, useState } from 'react';

/**
 * Les itineraires routiers d'une liste de trajets, par identifiant.
 *
 * Ils sont demandes un par un apres l'affichage de la page : les demander
 * tous ensemble saturerait le service exterieur. Le serveur les garde
 * trente jours, ils sont donc instantanes ensuite. En attendant chacun
 * d'eux, la liaison directe tient la place sur la carte.
 *
 * Un trajet sans itineraire routier (serveur injoignable) reste en
 * liaison directe.
 */
export function useItineraires(trajets) {
    const [traces, setTraces] = useState({});

    // La liste change d'objet a chaque rendu : seuls les identifiants
    // decident d'une nouvelle serie de demandes.
    const identifiants = trajets.map((trajet) => trajet.id).join(',');

    useEffect(() => {
        let vivant = true;

        (async () => {
            for (const id of identifiants ? identifiants.split(',') : []) {
                try {
                    const reponse = await fetch(route('tracking.itineraire', id), {
                        headers: { Accept: 'application/json' },
                    });

                    if (! vivant) {
                        return;
                    }

                    const donnees = reponse.ok ? await reponse.json() : null;

                    if (vivant && donnees?.geometrie && ! donnees.direct) {
                        setTraces((precedentes) => ({ ...precedentes, [id]: donnees.geometrie }));
                    }
                } catch (erreur) {
                    // Itineraire indisponible : la liaison directe reste.
                }
            }
        })();

        return () => {
            vivant = false;
        };
    }, [identifiants]);

    return traces;
}
