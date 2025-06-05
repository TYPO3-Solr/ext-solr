#!/bin/bash

set -e

copy_to_volume() {
  echo "[INFO] Copying EXT:solr to ${SOLR_HOME}"
  cp -rv /var/solr/data/* "${SOLR_HOME}/"
  exit 0
}

if [ "${SOLR_HOME}" != "/var/solr/data" ] && [ ! -f "${SOLR_HOME}/solr.xml" ]; then
  _upstream_ext_solr_version="$(find /var/solr/data/configsets/ -maxdepth 1 -mindepth 1 -type d -name 'ext_solr_*')"
  _upstream_ext_solr_version="${_upstream_ext_solr_version##*/}"
  _solr_home_ext_solr_version="$(find "${SOLR_HOME}"/configsets/ -maxdepth 1 -mindepth 1 -type d -name 'ext_solr_*' >/dev/null 2>&1)"
  _solr_home_ext_solr_version="${_solr_home_ext_solr_version##*/}"
  if [[ -z "${_solr_home_ext_solr_version}" ]]; then
    copy_to_volume
  fi
  if [ "${_upstream_ext_solr_version}" != "${_solr_home_ext_solr_version}" ]; then
    echo "[Info] New EXT:solr version ${_upstream_ext_solr_version} detected."
    if [[ -n "${ALLOW_SOLR_HOME_EXT_SOLR_VERSION_UPGRADE}" ]]; then
      echo "[Error] ALLOW_SOLR_HOME_EXT_SOLR_VERSION_UPGRADE not set, exiting"
      exit 1
    else
      copy_to_volume
    fi
  fi
fi
