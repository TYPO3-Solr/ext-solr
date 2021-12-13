FROM solr:7.7.3
MAINTAINER dkd Internet Services GmbH <solr-eb-support@dkd.de>
ENV TERM linux

RUN rm -fR /opt/solr/server/solr/*

COPY Resources/Private/Solr/ /opt/solr/server/solr

USER root

RUN mkdir -p /opt/solr/server/solr/data && \
    chown -R solr:solr /opt/solr/server/solr/

USER solr

VOLUME ["/opt/solr/server/solr/data"]
