FROM php:8.4-fpm

ARG USER_UID=1000
ARG USER_GID=1000

# Node 22 from NodeSource (Debian's nodejs is v20; Claude Code requires >=22)
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip curl default-mysql-client \
        libzip-dev libicu-dev libonig-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli zip intl mbstring gd bcmath opcache pcntl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/99-dev.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Non-root user (UID/GID default to 1000 to match typical host user)
RUN groupadd --gid ${USER_GID} vscode \
    && useradd --uid ${USER_UID} --gid ${USER_GID} -m -s /bin/bash vscode

USER vscode

# User-owned global npm prefix so `npm install -g` works without root
ENV NPM_CONFIG_PREFIX=/home/vscode/.npm-global
ENV PATH=/home/vscode/.npm-global/bin:$PATH
RUN echo 'export PATH="$HOME/.npm-global/bin:$PATH"' >> /home/vscode/.bashrc
WORKDIR /workspaces/kai
