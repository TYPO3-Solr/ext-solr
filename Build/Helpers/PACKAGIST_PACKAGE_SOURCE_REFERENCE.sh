#!/usr/bin/env bash

if ! jq --version > /dev/null 2>&1
then
  1>&2 echo -e "Error:
  jq is not installed in your system. Please install jq in your distribution first.
  See: https://stedolan.github.io/jq/"
  exit 1
fi

PACKAGE_NAME="$1"
VERSIONS_STRING="$2"

PACKAGE_URL="https://packagist.org/packages/$PACKAGE_NAME.json"
if ! PACKAGIST_DATA=$(curl "$PACKAGE_URL" -sSf); then
  1>&2 echo -e "Error:
    Something went wrong by fetching the data for \"$PACKAGE_NAME\" package from packagist.org
    See: $PACKAGE_URL"
  exit 2;
fi

SOURCE_REFERENCE=$(echo "$PACKAGIST_DATA" | jq --raw-output '.package.versions."v'"$VERSIONS_STRING"'".source.reference')
if [[ $SOURCE_REFERENCE == "null"  ]] ; then
  SOURCE_REFERENCE=$(echo "$PACKAGIST_DATA" | jq --raw-output '.package.versions."'"$VERSIONS_STRING"'".source.reference')
fi

if [[ $SOURCE_REFERENCE == "null"  ]] ; then
  1>&2 echo -e "Error:
    The env var \"SOURCE_REFERENCE\" could not be created.
    Something went wrong by fetching the Git reference ID from Packagist APIs."
  exit 3;
fi

if [[ $* == *--short* ]]; then
  echo "$SOURCE_REFERENCE" | cut -c 1-6
  exit 0;
fi

echo "$SOURCE_REFERENCE"
