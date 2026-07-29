FROM richarvey/nginx-php-fpm:3.1.6

# Install Node.js (current version, v22+)
RUN apk add --no-cache nodejs-current npm

# Copy application source
COPY . .

# Install PHP dependencies (production)
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Install NPM dependencies and build Vite assets
RUN npm ci && npm run build

# Image config
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

# Laravel config
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr

CMD ["/start.sh"]