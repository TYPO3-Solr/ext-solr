FROM solr:8.11.4
MAINTAINER dkd Internet Service GmbH <solr-eb-support@dkd.de>
ENV TERM linux

USER root
RUN rm -fR /opt/solr/server/solr/* \
  && echo '# EXT:solr relevant changes: ' >> /etc/default/solr.in.sh \
    && echo 'SOLR_OPTS="$SOLR_OPTS -Dsolr.enableRemoteStreaming=true -Dsolr.enableStreamBody=true"' >> /etc/default/solr.in.sh \
    && echo '# END: EXT:solr' >> /etc/default/solr.in.sh

USER solr

COPY --chown=solr:solr Resources/Private/Solr/ /var/solr/data
RUN mkdir -p /var/solr/data/data
