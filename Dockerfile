# MF Avaliacao Fisica — API Laravel em Docker (Render Free: SOMENTE DEMONSTRACAO).
# Imagem web separada do Laravel Sail: nao inicia MySQL e nao usa .env local.
FROM php:8.5-apache-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=10000

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      ca-certificates unzip git libicu-dev libzip-dev libonig-dev \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install -j2 pdo_mysql mbstring intl zip gd bcmath exif opcache \
 && a2enmod rewrite headers \
 && sed -ri 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
 && printf 'StartServers 1\nMinSpareServers 1\nMaxSpareServers 2\nMaxRequestWorkers 3\nMaxConnectionsPerChild 200\n' > /etc/apache2/conf-available/mf-small.conf \
 && a2enconf mf-small \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Composer requer apenas o codigo do repositorio, nunca secrets do laptop.
COPY . /var/www/html
RUN mkdir -p storage/app/private storage/framework/cache/data \
      storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && composer install --no-dev --no-interaction --prefer-dist --no-progress \
       --optimize-autoloader --no-scripts \
 && composer dump-autoload --no-dev --optimize --no-scripts \
 && php artisan package:discover --no-ansi \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache

COPY deploy/render/apache-mf.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/render/php-mf.ini /usr/local/etc/php/conf.d/zzz-mf.ini
COPY deploy/render/start.sh /usr/local/bin/mf-render-start
RUN chmod 755 /usr/local/bin/mf-render-start

EXPOSE 10000
CMD ["/usr/local/bin/mf-render-start"]
