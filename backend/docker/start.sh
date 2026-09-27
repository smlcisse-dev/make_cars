#!/bin/bash
# Démarrage du conteneur de production (Dockerfile).
# AUCUNE migration ici : la base de production se migre à la main
# (PASSATION.md, « Toutes les migrations en attente… »).
set -euo pipefail

cd /var/www/html

# Port fourni par Render.
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Configuration, routes, vues et événements mis en cache, avec les
# variables d'environnement de Render (connues seulement au démarrage).
php artisan optimize --no-interaction
chown -R www-data:www-data storage bootstrap/cache

php-fpm --nodaemonize &
nginx -g 'daemon off;' &

# Arrêt demandé par Render (redéploiement) : transmis à PHP-FPM et nginx,
# qui terminent les requêtes en cours.
trap 'kill -QUIT $(jobs -p) 2>/dev/null; wait' TERM INT QUIT

# Si l'un des deux s'arrête, le conteneur s'arrête : Render le relance.
wait -n
exit $?
