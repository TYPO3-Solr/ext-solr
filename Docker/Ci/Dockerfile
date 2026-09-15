ARG PHP_VERSION
FROM php:$PHP_VERSION-cli
LABEL maintainer="info@dkd.de"
ENV TERM linux

# create man directory to fix jre dependencies issue
RUN mkdir -p /usr/share/man/man1

# install required packages and modules
RUN apt-get update && apt-get install -yq \
        git \
        zip \
        wget \
        libzip-dev \
        default-mysql-client \
        openjdk-11-jre \
    && docker-php-ext-install \
        zip \
        mysqli
COPY --from=composer:1 /usr/bin/composer /usr/local/bin/composer

COPY . /ext-solr/

# add and switch user
RUN adduser --disabled-password --gecos "" solr \
    && chown -R solr:solr /ext-solr
USER solr
