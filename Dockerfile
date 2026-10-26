FROM php:8.2-apache

# Instalar dependencias del sistema y extensiones de PHP requeridas
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Configurar variables de entorno en php.ini (EGPCS), charset UTF-8 y límites de subida de archivos
RUN echo "variables_order = \"EGPCS\"" > /usr/local/etc/php/conf.d/docker-php-vars.ini \
    && echo "default_charset = \"UTF-8\"" >> /usr/local/etc/php/conf.d/docker-php-vars.ini \
    && echo "upload_max_filesize = 25M" >> /usr/local/etc/php/conf.d/docker-php-vars.ini \
    && echo "post_max_size = 30M" >> /usr/local/etc/php/conf.d/docker-php-vars.ini

# Configurar VirtualHost de Apache con DocumentRoot en /public
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . /var/www/html/

# Asegurar permisos correctos para el servidor web y uploads
RUN mkdir -p /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/public/uploads

EXPOSE 80

CMD ["apache2-foreground"]
