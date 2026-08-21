ARG PHP_VERSION=8.4.19
FROM php:${PHP_VERSION}-apache

# Laboratorio de réplica WordPress + Woo + Chilexpress (SR-108688)
# Imagen autosuficiente para Dokploy: copia el árbol de WordPress al contenedor.
LABEL org.opencontainers.image.title="Chilexpress Woo replica lab"
LABEL org.opencontainers.image.authors="Alexander Alejandro Cautivo Ramos <alexander.cautivo@aeolabs.io>"
LABEL org.opencontainers.image.source="https://github.com/alexcautivo/chilexpress-woo-replica"

RUN apt-get update && apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
		libzip-dev \
		libicu-dev \
		libonig-dev \
		libsqlite3-dev \
		unzip \
		less \
		curl \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql pdo_sqlite zip intl opcache \
	&& a2enmod rewrite \
	&& rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/cxp.ini
COPY docker/apache-wordpress.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/cxp-entrypoint.sh
COPY docker/patch-config.php /usr/local/bin/patch-config.php
COPY docs/ /var/www/docs/
COPY incidents/ /var/www/incidents/
COPY incidents/ /var/www/seed-incidents/
COPY chilexpress-oficial/ /var/www/chilexpress-oficial/
COPY baselines/ /var/www/baselines/
COPY wordpress/ /var/www/html/
# Semilla fuera del volumen: el mount de wp_database tapa database/ en Dokploy.
COPY wordpress/wp-content/database/.ht.sqlite /var/www/seed/.ht.sqlite

RUN sed -i 's/\r$//' /usr/local/bin/cxp-entrypoint.sh \
	&& chmod +x /usr/local/bin/cxp-entrypoint.sh \
	&& mkdir -p /var/www/html/wp-content/database /var/www/html/wp-content/uploads /var/www/incidents \
	&& chown -R www-data:www-data /var/www/html /var/www/docs /var/www/incidents /var/www/seed /var/www/seed-incidents

WORKDIR /var/www/html
EXPOSE 80
HEALTHCHECK --interval=15s --timeout=5s --start-period=25s --retries=4 \
	CMD bash -c "echo >/dev/tcp/127.0.0.1/80"
ENTRYPOINT ["/usr/local/bin/cxp-entrypoint.sh"]
CMD ["apache2-foreground"]
