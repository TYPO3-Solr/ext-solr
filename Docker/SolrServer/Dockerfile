FROM solr:9.2.1
MAINTAINER dkd Internet Service GmbH <solr-eb-support@dkd.de>
ENV TERM linux

USER root
RUN rm -fR /opt/solr/server/solr/*
COPY Docker/SolrServer/docker-entrypoint-initdb.d/ /docker-entrypoint-initdb.d
USER solr

COPY --chown=solr:solr Resources/Private/Solr/ /var/solr/data
RUN mkdir -p /var/solr/data/data