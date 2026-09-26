<?php

return [

    // Pays ou l'on enleve en ligne : Union europeenne, sans douane, par la
    // route. Les six derniers ont un fuseau different de Bruxelles : les
    // heures s'y saisissent et s'y affichent a l'heure locale.
    'pays_enlevement' => [
        'BE', 'AT', 'CZ', 'DE', 'DK', 'ES', 'FR', 'HR', 'HU', 'IT', 'LU', 'NL', 'PL', 'SE', 'SI', 'SK',
        'BG', 'EE', 'LT', 'LV', 'RO', 'PT',
    ],

    // Pays desservis a l'enlevement, mais sur devis : douane (Suisse,
    // Royaume-Uni, Norvege), ferry (Irlande, Finlande), iles et codes
    // postaux absents (Grece).
    'pays_enlevement_devis' => ['CH', 'GB', 'NO', 'IE', 'FI', 'GR'],

    // Fuseau de chaque pays d'enlevement (partie continentale).
    'fuseaux' => [
        'BE' => 'Europe/Brussels', 'AT' => 'Europe/Vienna', 'CZ' => 'Europe/Prague', 'DE' => 'Europe/Berlin',
        'DK' => 'Europe/Copenhagen', 'ES' => 'Europe/Madrid', 'FR' => 'Europe/Paris', 'HR' => 'Europe/Zagreb',
        'HU' => 'Europe/Budapest', 'IT' => 'Europe/Rome', 'LU' => 'Europe/Luxembourg', 'NL' => 'Europe/Amsterdam',
        'PL' => 'Europe/Warsaw', 'SE' => 'Europe/Stockholm', 'SI' => 'Europe/Ljubljana', 'SK' => 'Europe/Bratislava',
        'BG' => 'Europe/Sofia', 'EE' => 'Europe/Tallinn', 'LT' => 'Europe/Vilnius', 'LV' => 'Europe/Riga',
        'RO' => 'Europe/Bucharest', 'PT' => 'Europe/Lisbon',
    ],

    // Iles et territoires hors TVA ou hors union douaniere, par debut de
    // code postal : Baleares, Canaries, Ceuta, Melilla ; Corse ; Sardaigne,
    // Sicile, Livigno, Campione ; Heligoland, Busingen ; Bornholm ;
    // Gotland ; Madere et Acores. Ils passent par un devis.
    'cp_exclus' => [
        'ES' => ['07', '35', '38', '51', '52'],
        'FR' => ['20'],
        'IT' => ['07', '08', '09', '90', '91', '92', '93', '94', '95', '96', '97', '98', '23041', '22061'],
        'DE' => ['27498', '78266'],
        'DK' => ['37'],
        'SE' => ['62'],
        'PT' => ['9'],
    ],

    // Distance routiere estimee a partir du vol d'oiseau.
    'facteur_route' => 1.3,

    // Depot d'ou partent les camions (Avenue du Port, Bruxelles).
    'depot' => ['lat' => 50.8667, 'lng' => 4.3471, 'ville' => 'Bruxelles'],

    'retour' => [
        // Remise fret retour, bornee entre 0 et 0,25 (verifiee au demarrage).
        'remise' => (float) env('FRET_RETOUR_REMISE', 0.15),
        // Distance maximale entre la livraison du camion et le chargement.
        'approche_max_km' => 150,
        // Au plus tard deux jours apres la livraison du camion.
        'attente_max_jours' => 2,
    ],

    // Premier enlevement possible hors de Belgique : route depuis le depot
    // et repos imposes par le reglement 561/2006. Ces heures sont des choix
    // d'entreprise, pas des obligations legales.
    'chrono' => [
        // 'si_necessaire' : repos seulement si la journee de conduite ou de
        // service est epuisee ; 'toujours' : 11 h apres chaque arrivee.
        'repos_apres_arrivee' => 'si_necessaire',
        'prise_de_service' => '06:00',
        'heure_limite' => '16:00',
        'jours_depart' => [1, 2, 3, 4, 5],
        'jours_enlevement' => [1, 2, 3, 4, 5],
        'chargement_h' => 1.0,
        'dechargement_h' => 1.0,
        'quai' => ['07:00', '17:00'],
        'arrondi_min' => 15,
    ],

];
