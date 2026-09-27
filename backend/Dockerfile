# Image de production du backend (Render, offre gratuite : 512 Mo de RAM,
# 0,1 CPU). Guide : DEPLOIEMENT.md à la racine du dépôt.
#
# Serveur web : nginx + PHP-FPM, plutôt que FrankenPHP ou Apache (mod_php) :
# - le couple le plus éprouvé pour Laravel, avec une mémoire prévisible :
#   un nombre fixe de processus PHP (pm = static, 4), ~40 Mo chacun, plus
#   ~10 Mo pour nginx : ~200 Mo au repos, de la marge sous 512 Mo pour les
#   pics (génération des PDF) ;
# - PHP-FPM fournit fastcgi_finish_request() : la réponse part avant l'envoi
#   des emails (App\Support\DeferredMail). Apache mod_php ne l'a pas ;
# - FrankenPHP (PHP « thread-safe » + Caddy) marche aussi, mais son nombre
#   de threads suit les CPU de la machine hôte et Caddy veut gérer HTTPS
#   lui-même (Render le fait déjà) : plus de réglages pour moins de recul.
# Les deux processus sont lancés par docker/start.sh ; si l'un s'arrête, le
# conteneur s'arrête et Render le redémarre.

FROM docker.io/library/php:8.3-fpm-bookworm

# Extensions : pdo_pgsql (Supabase), intl (recherche insensible aux accents),
# gd (images des PDF dompdf), zip (Composer), opcache. Les autres
# (mbstring, dom, fileinfo…) sont déjà dans l'image officielle.
COPY --from=docker.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx gettext-base \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default \
    && install-php-extensions pdo_pgsql intl gd zip opcache

COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dépendances d'abord (couche mise en cache tant que composer.lock ne change pas).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-production.conf
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

# Render fournit le port dans PORT (10000 par défaut).
ENV PORT=10000
EXPOSE 10000

CMD ["start"]
