# syntax=docker/dockerfile:1.7

# =========================================================
# Stage 1: Build frontend assets
# =========================================================
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# =========================================================
# Stage 2: PHP dependencies (cacheable composer layer)
# =========================================================
FROM composer:2 AS composer-deps

WORKDIR /app

ARG APP_ENV=production

COPY composer.json composer.lock ./

RUN if [ "$APP_ENV" = "production" ]; then \
        composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction; \
    else \
        composer install --no-scripts --no-autoloader --prefer-dist --no-interaction; \
    fi

# =========================================================
# Stage 3: PHP application runtime
# =========================================================
FROM php:8.2-fpm-bookworm AS app

# OCI label scopes `docker image prune --filter "label=..."` to this project.
LABEL org.opencontainers.image.source="https://github.com/agentunio/peoplemanager"

ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}

# System dependencies + Chromium for Browsershot + fcgi for healthcheck + gosu for privilege drop
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        libcurl4-openssl-dev \
        ca-certificates \
        gnupg \
        gosu \
        zip \
        unzip \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libfcgi-bin \
        chromium \
        fonts-liberation \
        libappindicator3-1 \
        libnss3 \
        libxss1 \
        xdg-utils \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        curl \
        zip \
        gd \
        bcmath \
        intl \
        opcache \
        mbstring \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer binary (for post-build commands if ever needed)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy prebuilt vendor (from composer-deps stage)
COPY --from=composer-deps --chown=www-data:www-data /app/vendor ./vendor

# Copy application source — whitelist only what the runtime needs.
# Anything not listed here is excluded from the image regardless of .dockerignore.
COPY --chown=www-data:www-data app                ./app
COPY --chown=www-data:www-data bootstrap          ./bootstrap
COPY --chown=www-data:www-data config             ./config
COPY --chown=www-data:www-data database           ./database
COPY --chown=www-data:www-data lang               ./lang
COPY --chown=www-data:www-data public             ./public
COPY --chown=www-data:www-data resources          ./resources
COPY --chown=www-data:www-data routes             ./routes
COPY --chown=www-data:www-data storage            ./storage
COPY --chown=www-data:www-data artisan            ./artisan
COPY --chown=www-data:www-data composer.json      ./composer.json
COPY --chown=www-data:www-data composer.lock      ./composer.lock
COPY --chown=www-data:www-data package.json       ./package.json
COPY --chown=www-data:www-data package-lock.json  ./package-lock.json
COPY --chown=www-data:www-data vite.config.js     ./vite.config.js

# Regenerate optimized autoloader with full sources
RUN composer dump-autoload --optimize --classmap-authoritative --no-interaction

# Copy built frontend assets (overrides anything from source public/build)
COPY --from=node-builder --chown=www-data:www-data /app/public/build public/build

# Immutable snapshot of public/ used by entrypoint to (re)populate the shared
# named volume in production where /var/www/html/public is mounted from app_public.
RUN cp -a public /opt/app-public \
    && chown -R www-data:www-data /opt/app-public

# Install Puppeteer (uses system Chromium instead of downloading its own)
ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    CHROME_PATH=/usr/bin/chromium
RUN npm ci --omit=dev --no-audit --no-fund \
    && npm cache clean --force \
    && chown -R www-data:www-data node_modules

# PHP runtime config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini

# Run php-fpm master as www-data (no privileged actions needed)
RUN sed -i 's/^user = .*/user = www-data/; s/^group = .*/group = www-data/' /usr/local/etc/php-fpm.d/www.conf \
    && echo "ping.path = /ping" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "ping.response = pong" >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Tune php-fpm pool. Sized for mem_limit: 1g (≈ 40MB per worker incl. opcache).
# Override-only — keeps other defaults from /usr/local/etc/php-fpm.d/www.conf.
COPY <<EOF /usr/local/etc/php-fpm.d/zz-pool.conf
[www]
pm = dynamic
pm.max_children = 14
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 500
EOF

# Dev: enable OPcache timestamp validation so file changes are picked up immediately
RUN if [ "$APP_ENV" != "production" ]; then \
        echo "opcache.validate_timestamps=1" > /usr/local/etc/php/conf.d/zz-opcache-dev.ini; \
    fi

# Writable dirs for Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
    && find storage -type d -exec chmod 775 {} \; \
    && find bootstrap/cache -type d -exec chmod 775 {} \;

# Entrypoint (still launched as root so it can fix volume perms, then drops to www-data via gosu)
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 > /dev/null || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
