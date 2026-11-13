#!/usr/bin/env bash
# Starts Solr in the foreground in the mode selected by SOLR_MODE:
#   user-managed (default) - standalone Solr, cores are discovered from /var/solr/data/cores
#   cloud                  - SolrCloud with embedded ZooKeeper, cores are created as collections
#                            (see docker-entrypoint-initdb.d/zz_solrcloud-collections.sh)

set -e

case "${SOLR_MODE:-user-managed}" in
  user-managed)
    exec solr-foreground --user-managed "$@"
    ;;
  cloud)
    exec solr-foreground "$@"
    ;;
  *)
    echo "ERROR: Unsupported SOLR_MODE '${SOLR_MODE}', use 'user-managed' or 'cloud'." >&2
    exit 1
    ;;
esac
