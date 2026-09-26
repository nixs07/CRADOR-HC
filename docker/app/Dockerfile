# Imagen de la aplicacion: PHP 8.2 + Apache + pdo_mysql
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql

# Apache sirve solo la carpeta public/ (src/, sql/, .env quedan fuera del alcance web)
ENV APACHE_DOCUMENT_ROOT=/var/www/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
    && echo "ServerTokens Prod"   >> /etc/apache2/conf-available/security.conf \
    && echo "ServerSignature Off" >> /etc/apache2/conf-available/security.conf \
    && echo "ServerName localhost" >  /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Configuracion de PHP
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'date.timezone = America/Bogota'; \
        echo 'default_charset = "UTF-8"'; \
        echo 'expose_php = Off'; \
        echo 'memory_limit = 512M'; \
        echo 'max_execution_time = 60'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.cookie_samesite = Lax'; \
        echo 'session.gc_maxlifetime = 43200'; \
    } > "$PHP_INI_DIR/conf.d/crador.ini"

WORKDIR /var/www/app
