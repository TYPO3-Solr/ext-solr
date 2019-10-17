FROM solr:8.2.0
MAINTAINER Timo Hund <timo.hund@dkd.de>
ENV TERM linux

USER root
RUN rm -fR /opt/solr/server/solr/*
USER solr

COPY Resources/Private/Solr/ /var/solr/data
RUN mkdir -p /var/solr/data/data