FROM solr:9.2
MAINTAINER dkd Internet Service GmbH <solr-eb-support@dkd.de>
ENV TERM linux

USER root
RUN rm -fR /opt/solr/server/solr/*
USER solr

COPY --chown=solr:solr Resources/Private/Solr/ /var/solr/data
RUN mkdir -p /var/solr/data/data
