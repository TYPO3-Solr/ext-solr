#!/bin/bash

if [ "${SOLR_HOME}" != "/var/solr/data" ]; then
    cp -rv /var/solr/data/* "${SOLR_HOME}/"
fi
