#!/usr/bin/env bash
# Creates per-worker Solr core sets for paratest parallel integration testing.
# Activated by PARATEST=on environment variable. Requires TYPO3_SOLR_ENABLED_CORES.
# Idempotent.

set -e

if [ "${PARATEST}" == "on" ]; then
  # When PARATEST=on, TYPO3_SOLR_ENABLED_CORES must be set
  if [ -z "${TYPO3_SOLR_ENABLED_CORES}" ]; then
    echo "ERROR: PARATEST is 'on' but TYPO3_SOLR_ENABLED_CORES is not set"
    exit 1
  fi

  NUM_WORKERS="${PARATEST_NUM_WORKERS:-$(nproc)}"
  CORES_DIR="/var/solr/data/cores"
  DATA_DIR="/var/solr/data/data"

  echo "Worker core setup: ${NUM_WORKERS} workers, enabled cores: ${TYPO3_SOLR_ENABLED_CORES}"

  # Worker 0 uses the base cores without suffix, so only create copies for worker 1+
  for TOKEN in $(seq 1 $((NUM_WORKERS - 1))); do
    for BASE_CORE_DIR_NAME in ${TYPO3_SOLR_ENABLED_CORES}; do
      # Read the Solr core name from core.properties (e.g., "core_en", "core_de", "core_da")
      BASE_CORE_DIR="${CORES_DIR}/${BASE_CORE_DIR_NAME}"
      if [ ! -d "${BASE_CORE_DIR}" ]; then
        echo "ERROR: Base core directory not found: ${BASE_CORE_DIR}"
        exit 1
      fi

      # Read configSet, schema, and SOLR CORE NAME from base core
      BASE_CONFIGSET=$(grep "^configSet=" "${BASE_CORE_DIR}/core.properties" | cut -d'=' -f2)
      if [ -z "${BASE_CONFIGSET}" ]; then
        echo "ERROR: Could not read configSet from ${BASE_CORE_DIR}/core.properties"
        exit 1
      fi

      BASE_SCHEMA=$(grep "^schema=" "${BASE_CORE_DIR}/core.properties" | cut -d'=' -f2)
      if [ -z "${BASE_SCHEMA}" ]; then
        echo "ERROR: Could not read schema from ${BASE_CORE_DIR}/core.properties"
        exit 1
      fi

      # Extract the Solr core name from "name=" property (e.g., "core_en" not "english")
      BASE_CORE_NAME=$(grep "^name=" "${BASE_CORE_DIR}/core.properties" | cut -d'=' -f2)
      if [ -z "${BASE_CORE_NAME}" ]; then
        echo "ERROR: Could not read core name from ${BASE_CORE_DIR}/core.properties"
        exit 1
      fi

      WORKER_CORE="${BASE_CORE_NAME}_${TOKEN}"
      WORKER_CORE_DIR="${CORES_DIR}/${BASE_CORE_DIR_NAME}_${TOKEN}"
      WORKER_DATA_DIR="${DATA_DIR}/${BASE_CORE_DIR_NAME}_${TOKEN}"

      if [ -d "${WORKER_CORE_DIR}" ]; then
        continue  # idempotent: skip if already created
      fi

      mkdir -p "${WORKER_CORE_DIR}"
      mkdir -p "${WORKER_DATA_DIR}"
      cat > "${WORKER_CORE_DIR}/core.properties" <<EOF
configSet=${BASE_CONFIGSET}
schema=${BASE_SCHEMA}
name=${WORKER_CORE}
dataDir=../../data/${BASE_CORE_DIR_NAME}_${TOKEN}
EOF

      # Copy managed resource files (stopwords, synonyms) from base core configset to worker cores
      CONFIGSET_DIR="/var/solr/data/configsets/${BASE_CONFIGSET}"
      if [ -d "${CONFIGSET_DIR}/conf" ]; then
        # Copy managed stopwords file for this language core
        MANAGED_STOPWORDS_FILE="${CONFIGSET_DIR}/conf/_schema_analysis_stopwords_${BASE_CORE_NAME}.json"
        if [ -f "${MANAGED_STOPWORDS_FILE}" ]; then
          cp "${MANAGED_STOPWORDS_FILE}" "${CONFIGSET_DIR}/conf/_schema_analysis_stopwords_${WORKER_CORE}.json"
        fi
        # Copy any other managed resource files (synonyms, etc.) if they exist
        MANAGED_SYNONYMS_FILE="${CONFIGSET_DIR}/conf/_schema_analysis_synonyms_${BASE_CORE_NAME}.json"
        if [ -f "${MANAGED_SYNONYMS_FILE}" ]; then
          cp "${MANAGED_SYNONYMS_FILE}" "${CONFIGSET_DIR}/conf/_schema_analysis_synonyms_${WORKER_CORE}.json"
        fi
      fi

      echo "Created: ${WORKER_CORE}"
    done
  done

  # Enable worker cores in case they were disabled by disable-cores.sh
  echo "Enabling worker cores..."
  for TOKEN in $(seq 1 $((NUM_WORKERS - 1))); do
    for BASE_CORE_DIR_NAME in ${TYPO3_SOLR_ENABLED_CORES}; do
      WORKER_CORE_DIR="${CORES_DIR}/${BASE_CORE_DIR_NAME}_${TOKEN}"
      if [ -f "${WORKER_CORE_DIR}/core.properties_disabled" ]; then
        mv "${WORKER_CORE_DIR}/core.properties_disabled" "${WORKER_CORE_DIR}/core.properties"
        echo "Enabled: ${WORKER_CORE_DIR}"
      fi
    done
  done

  TOTAL_CORES=$((NUM_WORKERS * $(echo ${TYPO3_SOLR_ENABLED_CORES} | wc -w)))
  echo "Done: ${TOTAL_CORES} worker cores ready."
  ######################################################################################################################
else
  echo "PARATEST is not 'on', skipping worker core setup."
fi
