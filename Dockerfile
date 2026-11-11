FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM php:8.2-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libicu-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_pgsql pdo_mysql bcmath gd zip mbstring intl opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=assets /app/public/build ./public/build
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN chmod +x docker/start.sh
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 10000
CMD ["/var/www/html/docker/start.sh"]
