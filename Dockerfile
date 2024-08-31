# Usa una imagen base de PHP con Apache
FROM php:8.3-apache

# Instala las extensiones de PHP necesarias y el cliente MySQL
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install gd mysqli pdo pdo_mysql

# Instala Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copia los archivos de Drupal al contenedor
COPY . /var/www/html

# Establece permisos correctos
RUN chown -R www-data:www-data /var/www/html

# Expon el puerto 80
EXPOSE 80