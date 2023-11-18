#!/bin/bash

set -e

# execute files in /docker-entrypoint-initdb.d/as-sudo/*.sh before starting solr
while read -r f; do
    case "$f" in
        *.sh)     echo "$0: running 'sudo $f'"; sudo "$f" ;;
        *)        echo "$0: ignoring $f" ;;
    esac
    echo
done < <(find /docker-entrypoint-initdb.d/as-sudo/ -mindepth 1 -type f | sort -n)
