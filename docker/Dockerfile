ARG PHP_VERSION=8.4.19
FROM php:${PHP_VERSION}-apache

# Réplica SR-108688 — PHP 8.4.19 + Apache. WordPress se monta desde ./wordpress
RUN apt-get update && apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
		libzip-dev \
		libicu-dev \
		libonig-dev \
		unzip \
		less \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql pdo_sqlite zip intl opcache \
	&& a2enmod rewrite \
	&& rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/cxp.ini
COPY docker/apache-wordpress.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/cxp-entrypoint.sh
COPY docker/patch-config.php /usr/local/bin/patch-config.php
RUN chmod +x /usr/local/bin/cxp-entrypoint.sh

WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/cxp-entrypoint.sh"]
CMD ["apache2-foreground"]
