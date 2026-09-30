# Image de production de NBLogiTrack (Render ou tout hebergeur Docker).
#
# 1. L'interface React est compilee avec Node.
# 2. L'application tourne sous Apache et PHP 8.4, avec les extensions
#    exigees par Laravel, PostgreSQL, les PDF et les montants localises.

FROM node:22-alpine AS interface
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js postcss.config.js tailwind.config.js jsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4-apache

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl gd zip bcmath opcache pcntl @composer

# Apache sert le dossier public et ecoute le port impose par l'hebergeur.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=10000
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
 && sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && a2enmod rewrite headers deflate expires \
 && echo 'ServerName localhost' > /etc/apache2/conf-enabled/nom-serveur.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/nblogitrack.ini

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=interface /app/public/build ./public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private \
 && composer dump-autoload --optimize --no-dev --no-interaction \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/demarrer.sh /usr/local/bin/demarrer
RUN chmod +x /usr/local/bin/demarrer

CMD ["demarrer"]
