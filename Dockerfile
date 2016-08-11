FROM solr:6.1.0
MAINTAINER Timo Hund <timo.hund@dkd.de>
ENV TERM linux

RUN rm -fR /opt/solr/server/solr/*

COPY Resources/Solr/ /opt/solr/server/solr

USER root

RUN chown solr:solr -R /opt/solr/server/solr/*

#RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
# apt-utils \
# && rm -rf /var/lib/apt/lists/* RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
# ca-certificates \
# curl \
# && rm -rf /var/lib/apt/lists/*

#RUN apt-get update && \
#      DEBIAN_FRONTEND=noninteractive apt-get -y install sudo -y --no-install-recommends

#RUN adduser solr sudo

USER solr