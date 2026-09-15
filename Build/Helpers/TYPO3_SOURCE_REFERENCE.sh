#!/usr/bin/env bash

if ! jq --version > /dev/null 2>&1
then
  1>&2 echo -e "Error:
  jq is not installed in your system. Please install jq in your distribution first.
  See: https://stedolan.github.io/jq/"
  exit 1
fi

TYPO3_VERSION=$("${BASH_SOURCE%/*}/TYPO3_GET_LATEST_VERSION.sh" "$1")

if ! TYPO3_CMS_DATA=$(curl "https://packagist.org/packages/typo3/cms.json" -sSf); then
  1>&2 echo -e "Error:
    Something went wrong by fetching the data for \"typo3/cms\" package from packagist.org
    See: https://packagist.org/packages/typo3/cms.json"
  exit 2;
fi

TYPO3_SOURCE_REFERENCE=$(echo "$TYPO3_CMS_DATA" | jq --raw-output '.package.versions."v'"$TYPO3_VERSION"'".source.reference')
if [[ $TYPO3_SOURCE_REFERENCE == "null"  ]] ; then
  TYPO3_SOURCE_REFERENCE=$(echo "$TYPO3_CMS_DATA" | jq --raw-output '.package.versions."'"$TYPO3_VERSION"'".source.reference')
fi

if [[ $TYPO3_SOURCE_REFERENCE == "null"  ]] ; then
  1>&2 echo -e "Error:
    The env var \"TYPO3_SOURCE_REFERENCE\" could not be created.
    Something went wrong by fetching the Git reference ID from TYPO3/Packagist/GitHub APIs."
  exit 3;
fi

if [[ $* == *--short* ]]; then
  echo "$TYPO3_SOURCE_REFERENCE" | cut -c 1-6
  exit 0;
fi

echo $TYPO3_SOURCE_REFERENCE
