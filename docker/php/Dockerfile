# ------------------------------------------------------------
# TUTOLAR · imagen de PHP-FPM
# Se usa tanto en desarrollo (con el codigo montado) como para
# construir una imagen autocontenida.
# ------------------------------------------------------------
FROM php:8.3-fpm-bookworm

# Extensiones que necesita Laravel 12 + TUTOLAR.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
        libfreetype6-dev libonig-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring intl zip gd bcmath exif opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer, copiado de su imagen oficial.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/tutolar.ini

# Usuario no root con el UID tipico del host, para que los archivos
# generados dentro del contenedor no queden como root en tu disco.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data \
    && usermod  -o -u "${UID}" -g "${GID}" www-data

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
