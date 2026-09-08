#!/usr/bin/env bash

#
# Sets the release version in the tracked files carrying it. Everything else
# derives the version from the tag at build time.
#
# Usage: PREPARE_RELEASE.sh 14.0.1
#        EXT_VERSION=14.0.1 PREPARE_RELEASE.sh
#

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

readonly GUIDES='Documentation/guides.xml'
readonly BUG_REPORT='.github/ISSUE_TEMPLATE/bug_report.md'
version="${1:-${EXT_VERSION:-}}"

if [ -z "${version}" ]; then
  echo >&2 'No version given. Pass it as argument or in EXT_VERSION:'
  echo >&2 "  $(basename "$0") 14.0.1"
  exit 1
fi

previous="$(sed -n 's/.*release="\([^"]*\)".*/\1/p' "${GUIDES}")"
# Only the release attribute moves. The version attribute holds the minor
# series and changes when a new one is branched off.
sed -i "s/release=\"[^\"]*\"/release=\"${version}\"/" "${GUIDES}"
echo "${GUIDES}: ${previous} -> ${version}"

# Anchored on the EXT:solr line: the template lists TYPO3, Apache Solr, PHP and
# MySQL example versions too, and those move for their own reasons.
previous="$(sed -n 's/.*EXT:solr Version: \[e\.g\. \([^]]*\)\].*/\1/p' "${BUG_REPORT}")"
sed -i "s/\(EXT:solr Version: \[e\.g\. \)[^]]*\]/\1${version}]/" "${BUG_REPORT}"
echo "${BUG_REPORT}: ${previous} -> ${version}"
echo 'Commit this as "[Release] '"${version}"'", then tag it on the GitHub release page.'
