FROM php:7.3-fpm

WORKDIR /src

ADD . ./

RUN apt-get update && apt-get -y install libzip-dev unzip

RUN docker-php-ext-install sockets zip

RUN echo "Installing Composer" && rm -rf vendor && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer clearcache && \
    composer install