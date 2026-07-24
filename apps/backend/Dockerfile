FROM php:8.4-fpm-alpine
# Set tehran timezone
RUN rm -rf /etc/localtime && ln -s /usr/share/zoneinfo/Asia/Tehran /etc/localtime

#RUN apk update -y
# Install usable applications
RUN apk add --no-cache busybox-extras iputils traceroute mtr curl tmux htop redis git dcron unzip 

# Install php extensions
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
                           zip
RUN rm /root/install-php-extensions

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN rm -rf /tmp/* /var/cache/apk/*

USER root
COPY . /opt/skylogs-api
WORKDIR /opt/skylogs-api

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts \
    && composer dump-autoload --optimize --no-interaction
RUN chmod -R 777 /opt/skylogs-api/storage /opt/skylogs-api/bootstrap
COPY ./crontab /etc/cron.d/horizon-cron
RUN chmod 0644 /etc/cron.d/horizon-cron && crontab /etc/cron.d/horizon-cron


EXPOSE 9000
CMD ["/bin/sh", "/opt/skylogs-api/docker-entrypoint.sh"]
