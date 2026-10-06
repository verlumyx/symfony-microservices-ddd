FROM php:8.3-fpm-alpine

ARG UID=1000
ARG GID=1000

# Instalar dependencias del sistema y librerías de desarrollo
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    shadow \
    icu-dev \
    libzip-dev \
    libpq-dev \
    rabbitmq-c-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/include/postgresql/ \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        sockets \
        opcache \
    && pecl install amqp redis \
    && docker-php-ext-enable amqp redis \
    && apk del --no-network $PHPIZE_DEPS \
    && rm -rf /tmp/pear

# Sincronizar UID/GID de www-data con el usuario anfitrión para evitar problemas de permisos
RUN usermod -u ${UID} www-data && groupmod -g ${GID} www-data

# Copiar Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuración personalizada de PHP
COPY ./docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/app.ini

WORKDIR /var/www/html

CMD ["php-fpm"]
