FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install -j"$(nproc)" curl mbstring mysqli pdo_mysql \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/production.ini
COPY . /var/www/html/
COPY docker/render-entrypoint.sh /usr/local/bin/render-entrypoint

RUN chmod +x /usr/local/bin/render-entrypoint \
    && mkdir -p /var/www/html/assets/uploads/products /var/www/html/assets/uploads/vip \
    && chown -R www-data:www-data /var/www/html/assets/uploads \
    && chmod -R 0775 /var/www/html/assets/uploads

WORKDIR /var/www/html
EXPOSE 10000

CMD ["render-entrypoint"]
