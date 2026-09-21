FROM php:8.3-apache

# Extensions nécessaires à Laravel + MySQL
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif bcmath gd

# Active le mod_rewrite (Laravel en a besoin pour le routing)
RUN a2enmod rewrite

# Le point d'entrée Laravel est dans public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN { \
    echo '<Directory /var/www/html/public>'; \
    echo '    AllowOverride All'; \
    echo '</Directory>'; \
    } > /etc/apache2/conf-available/klap-override.conf \
    && a2enconf klap-override

# Installe Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html
WORKDIR /var/www/html

RUN composer install --no-interaction --optimize-autoloader

# Droits requis par Laravel pour écrire dans storage/ et bootstrap/cache/
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80
