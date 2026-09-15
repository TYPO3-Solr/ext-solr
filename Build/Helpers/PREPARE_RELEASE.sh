#!/usr/bin/env bash

#
# Sets the release version in the tracked files carrying it. Everything else
# derives the version from the tag at build time.
#
# Usage: PREPARE_RELEASE.sh 14.0.1
#        EXT_VERSION=14.0.1 PREPARE_RELEASE.sh
#

set -euo pipefail

# Resolved before the cd: the helper ships next to this script, not in the caller.
readonly INPUTS="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)/GET_TER_BUILD_INPUTS.sh"

cd "$(git rev-parse --show-toplevel)"

readonly GUIDES='Documentation/guides.xml'
readonly BUG_REPORT='.github/ISSUE_TEMPLATE/bug_report.md'
version="${1:-${EXT_VERSION:-}}"

if [ -z "${version}" ]; then
  echo >&2 'No version given. Pass it as argument or in EXT_VERSION:'
  echo >&2 "  $(basename "$0") 14.0.1"
  exit 1
fi

# Anchored on this extension's own line: an add-on's template carries the
# sibling extensions too, and those move for their own reasons.
# Own statement, because `readonly VAR="$(...)"` would hide a failure from set -e.
key="$("${INPUTS}" < composer.json | sed -n 's/^key=//p')"
readonly LABEL="EXT:${key}"

# Checked before the first write, so a failure leaves nothing half-stamped.
if ! grep -q "${LABEL} Version: \[e\.g\. " "${BUG_REPORT}"; then
  echo >&2 "${BUG_REPORT} carries no \"${LABEL} Version: [e.g. ...]\" line."
  echo >&2 'Add one, so the template names the version this release is about.'
  exit 1
fi

previous="$(sed -n 's/.*release="\([^"]*\)".*/\1/p' "${GUIDES}")"
# Only the release attribute moves. The version attribute holds the minor
# series and changes when a new one is branched off.
sed -i "s/release=\"[^\"]*\"/release=\"${version}\"/" "${GUIDES}"
echo "${GUIDES}: ${previous} -> ${version}"

previous="$(sed -n "s/.*${LABEL} Version: \[e\.g\. \([^]]*\)\].*/\1/p" "${BUG_REPORT}")"
sed -i "s/\(${LABEL} Version: \[e\.g\. \)[^]]*\]/\1${version}]/" "${BUG_REPORT}"
echo "${BUG_REPORT}: ${previous} -> ${version}"
echo 'Commit this as "[Release] '"${version}"'", then tag it on the GitHub release page.'
