FROM php:7.2-cli-alpine

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

RUN mkdir -p /app
COPY . /app
WORKDIR /app
