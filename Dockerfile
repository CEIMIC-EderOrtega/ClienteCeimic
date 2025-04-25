# Dockerfile

# 1. Base Image: PHP 8.2 FPM sobre Alpine Linux (ligero)
FROM php:8.2-fpm-alpine AS base

# Argumentos para IDs de usuario/grupo (opcional)
ARG UID=1000
ARG GID=1000

# Variables de entorno útiles
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/composer \
    PATH=$PATH:/composer/vendor/bin

# Directorio de trabajo en el contenedor
WORKDIR /var/www/html

# 2. Instalar dependencias del sistema y extensiones PHP
#    oniguruma-dev para mbstring, unixodbc-dev para ODBC y gnupg para GPG
RUN apk update && apk add --no-cache \
    bash \
    nginx \
    build-base \
    shadow \
    linux-headers \
    $PHPIZE_DEPS \
    curl \
    git \
    zip \
    unzip \
    libzip-dev \
    postgresql-dev \
    libxml2-dev \
    oniguruma-dev \
    nodejs \
    npm \
    unixodbc-dev \
    gnupg

# 3. Instalar Microsoft ODBC Driver for SQL Server
#    Usamos 'rm -f' para evitar errores si los archivos .apk/.sig son limpiados por apk add
RUN curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/msodbcsql17_17.10.5.1-1_amd64.apk \
 && curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/mssql-tools_17.10.1.1-1_amd64.apk \
 && curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/msodbcsql17_17.10.5.1-1_amd64.sig \
 && curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/mssql-tools_17.10.1.1-1_amd64.sig \
 && curl https://packages.microsoft.com/keys/microsoft.asc | gpg --import \
 && gpg --verify msodbcsql17_17.10.5.1-1_amd64.sig msodbcsql17_17.10.5.1-1_amd64.apk \
 && gpg --verify mssql-tools_17.10.1.1-1_amd64.sig mssql-tools_17.10.1.1-1_amd64.apk \
 && apk add --allow-untrusted msodbcsql17_17.10.5.1-1_amd64.apk mssql-tools_17.10.1.1-1_amd64.apk \
 && rm -f msodbcsql17_17.10.5.1-1_amd64.apk mssql-tools_17.10.1.1-1_amd64.apk \
        msodbcsql17_17.10.5.1-1_amd64.sig mssql-tools_17.10.1.1-1_amd64.sig

# 4. Instalar extensiones PHP nativas
RUN docker-php-ext-install -j$(nproc) \
    pdo pdo_pgsql pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    sockets \
    zip \
    xml \
    dom

# 5. Instalar extensiones SQL Server vía PECL
RUN echo "ACCEPT_EULA=Y" > /etc/environment \
 && apk add --no-cache --virtual .pecl-deps $PHPIZE_DEPS \
 && pecl install sqlsrv pdo_sqlsrv \
 && docker-php-ext-enable sqlsrv pdo_sqlsrv \
 && apk del .pecl-deps \
 && rm -rf /var/cache/apk/*

# 6. Instalar Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# --- Etapa de Dependencias Backend ---
FROM base AS backend_deps
WORKDIR /var/www/html

# Copiar archivos de Composer primero para aprovechar el caché
COPY composer.json composer.lock ./

# SOLUCIÓN 1: Copiar todo el código fuente ANTES de composer install
# Esto asegura que los scripts de Composer tengan acceso a los archivos de la aplicación (como routes).
COPY . .

# Ejecutar composer install
RUN composer install --no-interaction --no-dev --optimize-autoloader --prefer-dist

# --- Etapa de Dependencias Frontend ---
FROM base AS frontend_deps
WORKDIR /var/www/html
COPY package.json package-lock.json* ./
RUN npm install

# --- Etapa Final de Construcción ---
FROM base AS final_app
WORKDIR /var/www/html

# Copiar Composer y dependencias instaladas de las etapas anteriores
COPY --from=base /usr/local/bin/composer /usr/local/bin/composer
COPY --from=backend_deps /var/www/html/vendor ./vendor
COPY --from=frontend_deps /var/www/html/node_modules ./node_modules

# Copiar todo el código fuente de la aplicación
COPY . .

# 7. Construir assets de frontend con Vite
RUN npm run build

# 8. Optimizar Laravel para producción
RUN php artisan config:cache \
 && php artisan route:cache \
 && php artisan view:cache

# 9. Establecer permisos para www-data
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
 && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 10. Exponer puerto de PHP-FPM y comando por defecto
EXPOSE 9000
CMD ["php-fpm"]