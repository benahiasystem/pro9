# ########## INICIO CAMBIO POSTGRESQL 17
FROM php:8.2.35-fpm-bookworm@sha256:f15b9649b74a4af4621c960b2d9861a998e6eaf652a28089ceb4b3f238b371d4 AS runtime
USER root
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl gnupg git unzip zip supervisor cron ghostscript imagemagick \
    tesseract-ocr poppler-utils libpq-dev libicu-dev libzip-dev libxml2-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libavif-dev \
    libonig-dev libmagickwand-dev libsqlite3-dev \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
    && echo 'deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main' > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update && apt-get install -y --no-install-recommends postgresql-client-17 \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-avif --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 pdo_pgsql pgsql pdo_sqlite bcmath intl zip gd mbstring pcntl exif sockets \
    && pecl install imagick-3.8.1 && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/* /tmp/pear
COPY --from=composer:2.8@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c /usr/bin/composer /usr/local/bin/composer
RUN useradd --create-home --uid 1000 ubuntu
COPY docker/php-entrypoint.sh /usr/local/bin/sab-entrypoint
ENTRYPOINT ["sab-entrypoint"]
WORKDIR /var/www/sab
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
CMD ["php-fpm"]

# The PHP/JavaScript parity tests invoke Node from PHP. Keep it in the test
# target, sharing the same Debian ABI, without adding it to production.
FROM node:22-bookworm-slim@sha256:e9f565d32859f0cbfb52125eafe8be39390f24dac302dc3cdf566e8ba3ab21ee AS test-node
FROM runtime AS testing
COPY --from=test-node /usr/local/bin/node /usr/local/bin/node
RUN node --version && node -e "if (process.versions.node.split('.')[0] !== '22' || typeof BigInt(1) !== 'bigint') process.exit(1)"

# Targets used by the immutable production release.
# ######### FIN CAMBIO POSTGRESQL 17

FROM test-node AS release-assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

FROM runtime AS release-php
COPY --chown=root:root . .
COPY --from=release-assets /build/public/build ./public/build
RUN ln -s ../storage/app/public public/storage
COPY docker/custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader --no-scripts \
    && mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs storage/app \
    && chown -R www-data:www-data storage bootstrap/cache vendor/mpdf/mpdf/tmp
USER www-data
ENTRYPOINT ["docker-php-entrypoint"]
CMD ["php-fpm"]

FROM nginx:stable-alpine AS release-nginx
COPY docker/production/default.conf.template /etc/nginx/templates/default.conf.template
COPY --from=release-php /var/www/sab/public /var/www/sab/public
COPY --from=release-php /var/www/sab/storage/app/public/skins /var/www/sab/storage/app/public/skins

FROM runtime AS production
