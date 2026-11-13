#!/usr/bin/env bash
# Activated by SOLR_MODE=cloud.
#
# Turns the enabled cores (including the paratest worker cores created by the previous init scripts)
# into SolrCloud collections with the same name, config set and schema.
# The core.properties files are renamed to core.properties_cloud, so that Solr's core discovery
# does not pick them up, and are used as manifest for creating the collections.
#
# This file is sourced by run-initdb before Solr starts. The collections are created in a
# background job as soon as Solr is up. Progress is logged to the container output and the
# result is reported by healthcheck.sh.

if [ "${SOLR_MODE:-user-managed}" == "cloud" ]; then
  for CORE_PROPERTIES in /var/solr/data/cores/*/core.properties; do
    [ -f "${CORE_PROPERTIES}" ] || continue
    mv "${CORE_PROPERTIES}" "${CORE_PROPERTIES}_cloud"
  done

  (
    set -e
    SOLR_URL="http://localhost:${SOLR_PORT_LISTEN:-8983}/solr"
    ZK_HOST="localhost:$(( ${SOLR_PORT_LISTEN:-8983} + 1000 ))"

    wait-for-solr.sh --max-attempts 120 --wait-seconds 1 --solr-url "http://localhost:${SOLR_PORT_LISTEN:-8983}"

    EXISTING_COLLECTIONS=$(curl -sf "${SOLR_URL}/admin/collections?action=LIST&wt=json")
    UPLOADED_CONFIGSETS=" "

    for MANIFEST in /var/solr/data/cores/*/core.properties_cloud; do
      [ -f "${MANIFEST}" ] || continue
      NAME=$(grep "^name=" "${MANIFEST}" | cut -d'=' -f2)
      CONFIGSET=$(grep "^configSet=" "${MANIFEST}" | cut -d'=' -f2)
      SCHEMA=$(grep "^schema=" "${MANIFEST}" | cut -d'=' -f2)

      if [[ "${EXISTING_COLLECTIONS}" == *"\"${NAME}\""* ]]; then
        echo "SolrCloud: collection ${NAME} exists"
        continue
      fi

      if [[ "${UPLOADED_CONFIGSETS}" != *" ${CONFIGSET} "* ]]; then
        # The schemas name the managed stopwords/synonyms after ${solr.core.name}. In SolrCloud this is
        # the replica name (e.g. core_en_shard1_replica_n1), so every replica would get its own lists
        # and the shipped defaults (_schema_analysis_*_core_en.json) would not be found.
        # Name them after the collection instead, which is the core name in user-managed mode.
        # ${solr.core.collection} is not defined in user-managed mode, so only the uploaded copy is changed.
        CLOUD_CONFIGSET_DIR="$(mktemp -d)/${CONFIGSET}"
        cp -r "/var/solr/data/configsets/${CONFIGSET}" "${CLOUD_CONFIGSET_DIR}"
        find "${CLOUD_CONFIGSET_DIR}/conf" -name schema.xml \
          -exec sed -i 's/managed="${solr.core.name}"/managed="${solr.core.collection}"/g' {} +

        echo "SolrCloud: uploading config set ${CONFIGSET}"
        solr zk upconfig --conf-name "${CONFIGSET}" --conf-dir "${CLOUD_CONFIGSET_DIR}" --zk-host "${ZK_HOST}"
        rm -rf "${CLOUD_CONFIGSET_DIR}"
        UPLOADED_CONFIGSETS="${UPLOADED_CONFIGSETS}${CONFIGSET} "
      fi

      echo "SolrCloud: creating collection ${NAME} (${CONFIGSET}, ${SCHEMA})"
      curl -sf "${SOLR_URL}/admin/collections?action=CREATE&wt=json&name=${NAME}&numShards=1&replicationFactor=1&collection.configName=${CONFIGSET}&property.schema=${SCHEMA}" > /dev/null
    done
    echo "SolrCloud: all collections are ready"
  ) &
fi
