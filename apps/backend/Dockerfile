FROM php:8.3-fpm-alpine
#FROM php:8.1.14-fpm-alpine3.16
# Set tehran timezone
RUN rm -rf /etc/localtime && ln -s /usr/share/zoneinfo/Asia/Tehran /etc/localtime

#RUN apk update -y
# Install usabel applications
RUN apk add busybox-extras iputils traceroute mtr curl tmux htop redis git dcron 

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
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN rm -rf /tmp/* /var/cache/apk/*

USER root
COPY . /opt/skylogs-api
WORKDIR /opt/skylogs-api

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
RUN chmod 777 -R /opt/skylogs-api/storage && chmod 777 -R /opt/skylogs-api/bootstrap
COPY ./crontab /etc/cron.d/horizon-cron
RUN chmod 0644 /etc/cron.d/horizon-cron && crontab /etc/cron.d/horizon-cron


EXPOSE 9000
CMD ["/bin/sh", "/opt/skylogs-api/docker-entrypoint.sh"]
