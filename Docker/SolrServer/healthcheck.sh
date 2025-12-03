#!/bin/bash
# Health check script for TYPO3 Solr Docker container
# Checks if Solr is running and if all existing cores are accessible

set -e

# Use SOLR_PORT environment variable from Apache Solr (defaults to 8983)
SOLR_PORT="${SOLR_PORT:-8983}"
SOLR_URL="http://localhost:${SOLR_PORT}/solr"

# Check if Solr is responding at all
if ! curl -sf "${SOLR_URL}/admin/cores?action=STATUS" > /dev/null; then
    echo "ERROR: Solr is not responding"
    exit 1
fi

# Find all cores by checking for core.properties files
ENABLED_CORE_PROP_FILES="/var/solr/data/cores/*/core.properties"
FOUND_CORES=false

for core_properties in $ENABLED_CORE_PROP_FILES; do
    # Check if the glob matched any files
    if [ ! -f "$core_properties" ]; then
        continue
    fi

    FOUND_CORES=true

    # Extract core name from core.properties file contents
    CORE_NAME=$(grep "^name=" "$core_properties" | cut -d'=' -f2)

    if [ -z "$CORE_NAME" ]; then
        echo "ERROR: Could not read core name from $core_properties"
        exit 1
    fi

    # Check if this core is healthy
    if ! curl -sf "${SOLR_URL}/${CORE_NAME}/admin/ping" > /dev/null; then
        echo "ERROR: Core '${CORE_NAME}' is not healthy"
        exit 1
    fi
done

if [ "$FOUND_CORES" = false ]; then
    echo "ERROR: No cores found in /var/solr/data/cores/"
    exit 1
fi

echo "OK: Solr and all cores are healthy"
exit 0
