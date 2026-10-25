FROM node:22 AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

FROM php:8.4-fpm

RUN set -eux; \
    sed -i -E 's#http://(deb\.debian\.org|security\.debian\.org)#https://\1#g' \
        /etc/apt/sources.list.d/*.sources /etc/apt/sources.list 2>/dev/null || true

RUN apt-get update && apt-get install -y \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libicu-dev \
        unzip curl nginx supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql bcmath intl exif pcntl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN printf '%s\n' \
      'upload_max_filesize=20M' \
      'post_max_size=20M' \
      'memory_limit=-1' \
      'max_execution_time=0' \
      > /usr/local/etc/php/conf.d/zz-board.ini

WORKDIR /var/www
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi

RUN mkdir -p storage/framework/cache/data storage/framework/views storage/framework/sessions \
             storage/app/private storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/scheme.conf /etc/nginx/conf.d/scheme.conf

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
