#!/bin/bash

if [ "${SOLR_HOME}" != "/var/solr/data" ] && [ ! -f "${SOLR_HOME}/solr.xml" ]; then
  cp -rv /var/solr/data/* "${SOLR_HOME}/"
fi
