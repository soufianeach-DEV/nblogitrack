/**
 * La position du navigateur, en meilleur effort.
 *
 * Cette fonction ne rejette jamais. Un refus de l'utilisateur, un
 * sous-sol, un telephone sans signal, un navigateur sans API : tous
 * rendent null, et l'appelant continue sans position.
 *
 * C'est volontaire. La position accompagne une declaration de livraison,
 * elle ne la conditionne pas : un chauffeur ne doit jamais rester bloque
 * devant un quai parce que le GPS n'accroche pas.
 */
export function positionActuelle({ delai = 8000, ageMax = 30000 } = {}) {
    if (typeof navigator === 'undefined' || ! navigator.geolocation) {
        return Promise.resolve(null);
    }

    return new Promise((resoudre) => {
        // Une securite en plus du delai natif : certains navigateurs ne
        // rappellent ni le succes ni l'echec quand la permission reste
        // ouverte sans reponse.
        const minuteur = setTimeout(() => resoudre(null), delai + 1000);

        const terminer = (valeur) => {
            clearTimeout(minuteur);
            resoudre(valeur);
        };

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => terminer({
                lat: coords.latitude,
                lng: coords.longitude,
                precision_m: coords.accuracy != null ? Math.round(coords.accuracy) : null,
            }),
            () => terminer(null),
            { enableHighAccuracy: true, timeout: delai, maximumAge: ageMax },
        );
    });
}
