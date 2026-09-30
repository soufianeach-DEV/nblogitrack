<?php

// Adresses des proxys de confiance (repartiteur de charge, CDN), separees
// par des virgules, ou * derriere un hebergeur dont les adresses changent.
// Vide : on ne fait confiance a personne, le bon defaut quand
// l'application repond directement.
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
