#!/usr/bin/env bash

shopt -s extglob

if [ -n "${TYPO3_SOLR_ENABLED_CORES}" ]
then
  for CORE_TO_DISABLE in /var/solr/data/cores/*/core.properties
  do
    mv "${CORE_TO_DISABLE}" "${CORE_TO_DISABLE}_disabled";
  done

  for CORE_to_ENABLE in ${TYPO3_SOLR_ENABLED_CORES}
  do
    # Already converted into a SolrCloud collection manifest on a previous start (SOLR_MODE=cloud)
    if [ -f "/var/solr/data/cores/${CORE_to_ENABLE}/core.properties_cloud" ]; then
      continue
    fi
    echo "Enable core ${CORE_to_ENABLE}"
    mv "/var/solr/data/cores/${CORE_to_ENABLE}/core.properties_disabled" "/var/solr/data/cores/${CORE_to_ENABLE}/core.properties"
  done
fi
