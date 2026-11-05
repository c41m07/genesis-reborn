FROM node:24-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY assets ./assets
COPY webpack.config.mjs ./
RUN npm run build

FROM php:8.4-apache-bookworm@sha256:9e811795a606ca7f8586dee48d32acf444985c19ac252d70d181cd71a4603898 AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev librabbitmq-dev libzip-dev unzip git \
 && docker-php-ext-install intl pdo_mysql zip opcache pcntl \
 && pecl install amqp-2.2.0 && docker-php-ext-enable amqp \
 && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
ENV APP_ENV=prod APP_DEBUG=0
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY bin ./bin
COPY config ./config
COPY migrations ./migrations
COPY src ./src
COPY templates ./templates
COPY public ./public
COPY --from=assets /app/public/build ./public/build
COPY docker/dev/apache.conf /etc/apache2/sites-available/000-default.conf
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
 && touch .env \
 && php bin/console assets:install public --no-interaction \
 && rm -rf var/cache/* \
 && mkdir -p var/cache var/log var/run var/sessions \
 && chown -R www-data:www-data var \
 && printf 'date.timezone=UTC\nexpose_php=Off\ndisplay_errors=Off\nlog_errors=On\n' > /usr/local/etc/php/conf.d/genesis.ini
