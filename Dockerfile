FROM richarvey/nginx-php-fpm:3.1.6

# Install Node.js (v22) using the official musl binary
RUN apk add --no-cache curl && \
    curl -fsSL https://nodejs.org/dist/v22.4.0/node-v22.4.0-linux-x64-musl.tar.xz | tar -xJ -C /usr/local --strip-components=1 && \
    apk del curl

# Verify Node version (optional)
RUN node -v && npm -v

# Copy application source
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Install NPM dependencies and build Vite assets
RUN npm ci && npm run build

# Image config
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr

CMD ["/start.sh"]