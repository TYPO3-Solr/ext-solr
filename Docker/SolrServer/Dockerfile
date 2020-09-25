FROM solr:8.5
MAINTAINER Timo Hund <timo.hund@dkd.de>
ENV TERM linux

USER root
RUN rm -fR /opt/solr/server/solr/*
USER solr

COPY --chown=solr:solr Resources/Private/Solr/ /var/solr/data
RUN mkdir -p /var/solr/data/data