# We use the travis php container since the we have php, java & mysql later we could use single containers for that for ci
# https://docs.travis-ci.com/user/common-build-problems/#Running-a-Container-Based-Docker-Image-Locally
FROM travisci/ci-garnet:packer-1512502276-986baf0
MAINTAINER Timo Hund <timo.hund@dkd.de>
ENV TERM linux

ARG PHP_VERSION
ENV PHP_VERSION=$PHP_VERSION

RUN cd /home/travis/ && \
    wget  https://s3.amazonaws.com/travis-php-archives/binaries/ubuntu/14.04/x86_64/php-${PHP_VERSION}.tar.bz2  && \
    tar xjf php-${PHP_VERSION}.tar.bz2 --directory /  && \
    /home/travis/.phpenv/bin/phpenv global $PHP_VERSION

COPY . /app/

RUN chown -R travis:travis /app

USER root