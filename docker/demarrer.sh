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

# Codes postaux : importes en arriere-plan au premier demarrage et apres
# chaque rechargement de la base (quelques minutes ; le site repond deja,
# les adresses se completent en ligne en attendant). Sa progression, pays
# par pays, s'affiche dans les journaux du serveur.
( artisan geo:import-postal-codes --si-absents --no-ansi 2>&1 || true ) &

exec apache2-foreground
