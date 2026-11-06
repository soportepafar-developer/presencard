# Simula un hosting cPanel: Apache con .htaccess activos y mod_rewrite/headers, PHP con pdo_mysql.
ARG PHP_TAG=7.4-apache
FROM php:${PHP_TAG}

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers expires \
    && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername
