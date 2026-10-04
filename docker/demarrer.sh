#!/bin/sh
# Demarrage du conteneur : base a jour, caches, taches planifiees, serveur.
set -e

artisan() { runuser -u www-data -- php /var/www/html/artisan "$@"; }

# Les migrations synchronisent le dictionnaire des traductions, mais
# seulement quand l'une d'elles tourne : un deploiement sans migration
# laissait les nouveaux textes en francais dans les autres langues.
artisan migrate --force --no-interaction
artisan traductions:synchroniser
artisan optimize

# L'ordonnanceur tourne chaque minute dans le conteneur : factures du mois,
# purges RGPD, file d'attente des courriels (voir routes/console.php).
( while true; do artisan schedule:run --no-interaction > /dev/null 2>&1 || true; sleep 60; done ) &

# Sur l'offre gratuite, le service s'endort apres quinze minutes sans visite,
# et l'ordonnanceur ne rattrape pas une tache dont l'heure est passee pendant
# le sommeil : purges RGPD et facturation du 1er ne tournaient alors jamais.
# Ces taches ne font rien quand tout est a jour : elles tournent aussi a
# chaque demarrage du conteneur, donc a chaque reveil.
( for tache in 'chauffeurs:cloturer-departs' 'positions:purger --jours=7' 'journaux:purger --mois=12' \
        'pieces:purger' 'factures:generer --tout' 'factures:envoyer-brouillons'; do
    artisan $tache --no-interaction > /dev/null 2>&1 || true
done ) &

# Codes postaux : importes en arriere-plan au premier demarrage et apres
# chaque rechargement de la base (quelques minutes ; le site repond deja,
# les adresses se completent en ligne en attendant). Sa progression, pays
# par pays, s'affiche dans les journaux du serveur.
( artisan geo:import-postal-codes --si-absents --sans-listes-completes --no-ansi 2>&1 || true ) &

exec apache2-foreground
