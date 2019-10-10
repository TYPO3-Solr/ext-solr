FROM solr:8.2.0
MAINTAINER Timo Hund <timo.hund@dkd.de>
ENV TERM linux

USER root

RUN rm -fR /opt/solr/server/solr/*

COPY Resources/Private/Solr/ /var/solr/data

RUN mkdir -p /var/solr/data/data && \
    chown -R solr:solr /var/solr/data

USER solr

VOLUME ["/var/solr/data/data"]
