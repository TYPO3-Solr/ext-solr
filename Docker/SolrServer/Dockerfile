FROM solr:8.5.1
MAINTAINER Timo Hund <timo.hund@dkd.de>
ENV TERM linux

USER root
RUN rm -fR /opt/solr/server/solr/*
USER solr

COPY --chown=$SOLR_USER:$SOLR_GROUP Resources/Private/Solr/ /var/solr/data
RUN mkdir -p /var/solr/data/data