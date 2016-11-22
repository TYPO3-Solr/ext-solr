FROM solr:6.3.0
MAINTAINER Timo Hund <timo.hund@dkd.de>
ENV TERM linux

RUN rm -fR /opt/solr/server/solr/*

COPY Resources/Private/Solr/configsets /opt/solr/server/solr/configsets
COPY Resources/Private/Solr/solr.xml /opt/solr/server/solr/solr.xml

USER root

RUN chown -R solr:solr /opt/solr/server/solr/

USER solr
