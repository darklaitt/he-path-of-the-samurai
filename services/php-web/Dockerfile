FROM php:8.3-fpm-alpine

# пакеты и расширения
RUN apk add --no-cache bash curl git unzip rsync postgresql-dev libpng-dev libjpeg-turbo-dev freetype-dev \
 && docker-php-ext-install pdo pdo_pgsql \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install gd

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# каталоги
RUN mkdir -p /var/www/html /opt/laravel-patches
WORKDIR /var/www/html

# entrypoint
COPY entrypoint.sh /usr/local/bin/php_entrypoint.sh
RUN chmod +x /usr/local/bin/php_entrypoint.sh

CMD ["bash", "/usr/local/bin/php_entrypoint.sh"]
