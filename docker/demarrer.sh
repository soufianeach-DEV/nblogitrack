#!/bin/sh
# Demarrage du conteneur : base a jour, caches, taches planifiees, serveur.
set -e

artisan() { runuser -u www-data -- php /var/www/html/artisan "$@"; }

# Les migrations synchronisent aussi le dictionnaire des traductions.
artisan migrate --force --no-interaction
artisan optimize

# L'ordonnanceur tourne chaque minute dans le conteneur : factures du mois,
# purges RGPD, file d'attente des courriels (voir routes/console.php).
( while true; do artisan schedule:run --no-interaction > /dev/null 2>&1 || true; sleep 60; done ) &

exec apache2-foreground
