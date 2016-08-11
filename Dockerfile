FROM solr:6.1.0
MAINTAINER Timo Hund <timo.hund@dkd.de>
ENV TERM linux

USER root

RUN apt-get update && apt-get install -y \
        net-tools \
        && apt-get clean \
        && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN rm -fR /opt/solr/server/solr/*

COPY Resources/Solr/ /opt/solr/server/solr

RUN chown -R solr:solr /opt/solr/server/solr/

USER solr
