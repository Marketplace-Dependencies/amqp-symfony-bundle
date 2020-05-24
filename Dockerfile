FROM php:7.4-fpm

WORKDIR /src

ADD . ./

RUN apt-get update && apt-get -y install libzip-dev unzip

RUN docker-php-ext-install sockets zip