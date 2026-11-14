FROM php:8.4-apache-bookworm@sha256:9e811795a606ca7f8586dee48d32acf444985c19ac252d70d181cd71a4603898
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev librabbitmq-dev libzip-dev libjpeg62-turbo-dev libpng-dev libwebp-dev unzip git \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install intl pdo_mysql zip opcache pcntl gd \
 && pecl install amqp-2.2.0 && docker-php-ext-enable amqp \
 && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/local/bin/composer
COPY docker/dev/apache.conf /etc/apache2/sites-available/000-default.conf
RUN printf 'date.timezone=UTC\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/genesis.ini
WORKDIR /app
ARG LOCAL_UID=1000
ARG LOCAL_GID=1000
RUN groupmod -g ${LOCAL_GID} www-data && usermod -u ${LOCAL_UID} -g ${LOCAL_GID} www-data
RUN mkdir -p var/guild-media && chown www-data:www-data var/guild-media && chmod 0700 var/guild-media
