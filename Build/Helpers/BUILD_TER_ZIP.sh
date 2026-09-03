#!/usr/bin/env bash

#
# Builds the flat TER archive from a temporary export of HEAD, so the working
# tree stays untouched and the archive always matches a commit. Every input
# comes from the committed composer.json. Needs no typo3/tailor, because
# non-public repositories have none.
#

set -euo pipefail

# Resolved before the cd, because add-ons run this from their own repository:
# the helper ships next to this script, the template belongs to the caller.
readonly INPUTS="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)/GET_TER_BUILD_INPUTS.sh"

cd "$(git rev-parse --show-toplevel)"

readonly COMPOSER="${COMPOSER_BINARY:-composer}"
readonly EMCONF_TEMPLATE='Build/TER.tmp/ext_emconf.php'
readonly ARCHIVE_DIR="${EXT_ARCHIVE_DIR:-.Build/archive}"

if ! git diff --quiet HEAD; then
  echo >&2 'Working tree differs from HEAD, refusing to build.'
  echo >&2 'The archive would not correspond to any commit. Commit or restore first:'
  git --no-pager diff --stat HEAD >&2
  exit 1
fi

key=''
version=''
bundles=()
provides=()
while IFS= read -r line; do
  case "${line}" in
    key=*) key="${line#key=}" ;;
    version=*) version="${line#version=}" ;;
    bundle=*) bundles+=("${line#bundle=}") ;;
    provides=*) provides+=("${line#provides=}") ;;
  esac
done < <(git show HEAD:composer.json | "${INPUTS}")

# A failure inside the process substitution above does not trip set -e, so an
# unusable manifest would otherwise be archived under an empty key.
if [ -z "${key}" ] || [ -z "${version}" ]; then
  echo >&2 'Could not read the build inputs from composer.json at HEAD, see above.'
  exit 1
fi


# Tag wins over the manifest, which stays on a dev version between releases.
version="${EXT_VERSION:-${version}}"

tmp="$(mktemp -d)"
trap 'rm -rf "${tmp}"' EXIT
extensionDir="${tmp}/${key}"

git archive --prefix="${key}/" HEAD | tar -x -C "${tmp}"

for bundle in ${bundles[@]+"${bundles[@]}"}; do
  IFS=$'\t' read -r package constraint vendorPath <<< "${bundle}"
  "${COMPOSER}" require --no-interaction --no-progress \
    --working-dir="${extensionDir}/$(dirname "${vendorPath}")" \
    "${package}:${constraint}"
done

for entry in ${provides[@]+"${provides[@]}"}; do
  IFS=$'\t' read -r package vendorPath <<< "${entry}"
  if [ ! -d "${extensionDir}/${vendorPath}/${package}" ]; then
    echo >&2 "Declared in providesPackages but not installed: ${package}"
    echo >&2 'TYPO3 would assume it is provided in classic mode. Fix composer.json.'
    exit 1
  fi
done

# Classic mode reads this key as the extension version; Composer mode ignores it.
"${COMPOSER}" config extra.typo3/cms.version "${version}" --working-dir="${extensionDir}"

# --- TEMPORARY: ext_emconf.php for the TER -------------------------------
# Only the TER requires it, so the template is optional: extensions that are
# never published there simply ship none. Drop this block once the TER does not
# require it either.
if [ -f "${EMCONF_TEMPLATE}" ]; then
  sed "s/'version' => ''/'version' => '${version}'/" "${EMCONF_TEMPLATE}" \
    > "${extensionDir}/ext_emconf.php"
fi
# --- /TEMPORARY ----------------------------------------------------------

mkdir -p "${ARCHIVE_DIR}"
archive="$(cd "${ARCHIVE_DIR}" && pwd)/${key}_${version}.zip"
rm -f "${archive}"
# Flat so the backend upload finds the manifest at the root; -D so entry 0 is a
# file, which stops Tailor reading a leading directory entry as a wrapper.
(cd "${extensionDir}" && zip -q -r -D "${archive}" .)

echo "Created ${ARCHIVE_DIR}/${key}_${version}.zip"
