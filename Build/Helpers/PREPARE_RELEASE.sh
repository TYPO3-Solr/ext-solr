#!/usr/bin/env bash

#
# Sets the release version in the tracked files carrying it: ext_emconf.php is
# what "composer ext:version" / "ext:archive:path" read to name the TER zip;
# guides.xml is what the rendered docs advertise as the current release.
#
# Usage: PREPARE_RELEASE.sh 12.1.5
#        EXT_VERSION=12.1.5 PREPARE_RELEASE.sh
#

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

readonly EMCONF='ext_emconf.php'
readonly GUIDES='Documentation/guides.xml'
version="${1:-${EXT_VERSION:-}}"

if [ -z "${version}" ]; then
  echo >&2 'No version given. Pass it as argument or in EXT_VERSION:'
  echo >&2 "  $(basename "$0") 12.1.5"
  exit 1
fi

previous="$(sed -n "s/.*'version' => '\([^']*\)'.*/\1/p" "${EMCONF}")"
sed -i "s/'version' => '[^']*'/'version' => '${version}'/" "${EMCONF}"
echo "${EMCONF}: ${previous} -> ${version}"

previous="$(sed -n 's/.*release="\([^"]*\)".*/\1/p' "${GUIDES}")"
sed -i "s/release=\"[^\"]*\"/release=\"${version}\"/" "${GUIDES}"
echo "${GUIDES}: ${previous} -> ${version}"

echo 'Commit this as "[Release] '"${version}"'", then tag it on the GitHub release page.'
