FROM solr:6.1.0
MAINTAINER Timo Hund <timo.hund@dkd.de>
ENV TERM linux

RUN rm -fR /opt/solr/server/solr/*

COPY Resources/Solr/ /opt/solr/server/solr

USER root

RUN chown -R solr:solr /opt/solr/server/solr/

USER solr
