FROM php:8.3-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Bug (10/30/2026): this image shipped with no php.ini at all, so PHP ran on
# its compiled-in defaults - in particular memory_limit was effectively
# unbounded, which let a single heavy request (e.g. parsing a several-
# thousand-row masterlist with PhpSpreadsheet) consume all of the
# container's actual RAM and crash the whole Apache/PHP process, taking
# down every other in-flight request with it until Render restarted the
# container (what a 502 + "hibernate-wake-error" on unrelated endpoints
# right after an import attempt actually means). Explicit limits here mean
# an oversized request hits a clean PHP error instead of killing the
# container for everyone.
RUN { \
    echo 'memory_limit = 256M'; \
    echo 'upload_max_filesize = 20M'; \
    echo 'post_max_size = 20M'; \
    echo 'max_execution_time = 120'; \
    echo 'max_input_time = 120'; \
    } > /usr/local/etc/php/conf.d/icare-limits.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Configure Apache
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80

# Run any pending migrations on every start (the Render Shell is paid-only),
# then start Apache. ";" so a migration problem never keeps the site down.
CMD ["sh", "-c", "php artisan migrate --force; apache2-foreground"]