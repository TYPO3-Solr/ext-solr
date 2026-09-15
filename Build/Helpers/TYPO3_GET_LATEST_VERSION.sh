#!/usr/bin/env bash

if ! jq --version > /dev/null 2>&1
then
  1>&2 echo -e "Error:
  jq is not installed in your system. Please install jq in your distribution first.
  See: https://stedolan.github.io/jq/"
  exit 1
fi

TYPO3_VERSION="$1"

# Use provided version, for incompatible version strings on get.typo3.org API.
if ! [[ $TYPO3_VERSION =~ ^[0-9]+$ ]] ; then
  echo "$TYPO3_VERSION"
  exit 0;
fi

TYPO3_GET_API_ENDPOINT="https://get.typo3.org/v1/api/major/$TYPO3_VERSION/release/latest"
if ! TYPO3_GET_API_RESPONSE=$(curl "$TYPO3_GET_API_ENDPOINT" --fail --silent --show-error); then
  1>&2 echo -e "Error:
    Something went wrong by fetching the latest version from TYPO3 APIs endpoint:
    $TYPO3_GET_API_ENDPOINT
                         most likely > $(printf "^%.0s" $(eval "echo {1..${#TYPO3_VERSION}}")) < trouble source"
  exit 1;
fi

echo "$TYPO3_GET_API_RESPONSE" | jq --raw-output '.version'
exit 0;
