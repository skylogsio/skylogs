FROM php:8.4-fpm-bookworm

# Debian cron, not Alpine's crond or dcron: those refuse to start schedule:run
# while the previous sub-minute run is still finishing, so every other minute
# is skipped.
ENV TZ=Asia/Tehran
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        cron \
        curl \
        git \
        netcat-openbsd \
        tzdata \
        unzip \
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone \
    && rm -rf /var/lib/apt/lists/*

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /root
RUN chmod +x /root/install-php-extensions && /root/install-php-extensions curl \
                           fileinfo \
                           gd \
                           intl \
                           json \
                           mbstring \
                           bcmath \
                           mysqli \
#                          opcache \
                           openssl \
                           pcntl \
                           pdo_mysql \
                           redis \
                           soap \
                           mongodb \
                           sockets \
                           xml \
                           zip \
    && rm /root/install-php-extensions

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /opt/skylogs-api

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-autoloader --no-interaction --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-interaction \
    && chmod -R 777 storage bootstrap/cache \
    && crontab crontab

EXPOSE 9000
ENTRYPOINT ["/bin/sh", "/opt/skylogs-api/docker-entrypoint.sh"]
