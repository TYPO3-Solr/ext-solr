#!/usr/bin/env bash

DEFAULT_TYPO3_VERSION="10"
DEFAULT_TYPO3_DATABASE_HOST="localhost"
DEFAULT_TYPO3_DATABASE_NAME="test"
DEFAULT_TYPO3_DATABASE_USERNAME="root"
DEFAULT_TYPO3_DATABASE_PASSWORD="supersecret"

if [[ $* == *--use-defaults* ]]; then
  export TYPO3_VERSION=$DEFAULT_TYPO3_VERSION
  export TYPO3_DATABASE_HOST=$DEFAULT_TYPO3_DATABASE_HOST
  export TYPO3_DATABASE_NAME=$DEFAULT_TYPO3_DATABASE_NAME
  export TYPO3_DATABASE_USERNAME=$DEFAULT_TYPO3_DATABASE_USERNAME
  export TYPO3_DATABASE_PASSWORD=$DEFAULT_TYPO3_DATABASE_PASSWORD
fi

if [[ $* == *--local* ]]; then
  echo -n "Choose a TYPO3 Version [defaults: " $DEFAULT_TYPO3_VERSION"] : "
  read -r typo3Version
  if [ -z "$typo3Version" ]; then typo3Version=$DEFAULT_TYPO3_VERSION; fi
  export TYPO3_VERSION=$typo3Version

  echo -n "Choose a database hostname: [defaults: " $DEFAULT_TYPO3_DATABASE_HOST"] : "
  read -r typo3DbHost
  if [ -z "$typo3DbHost" ]; then typo3DbHost=$DEFAULT_TYPO3_DATABASE_HOST; fi
  export TYPO3_DATABASE_HOST=$typo3DbHost

  echo -n "Choose a database name: [defaults: " $DEFAULT_TYPO3_DATABASE_NAME"] : "
  read -r typo3DbName
  if [ -z "$typo3DbName" ]; then typo3DbName=$DEFAULT_TYPO3_DATABASE_NAME; fi
  export TYPO3_DATABASE_NAME=$typo3DbName

  echo -n "Choose a database user: [defaults: " $DEFAULT_TYPO3_DATABASE_USERNAME"] : "
  read -r typo3DbUser
  if [ -z "$typo3DbUser" ]; then typo3DbUser=$DEFAULT_TYPO3_DATABASE_USERNAME; fi
  export TYPO3_DATABASE_USERNAME=$typo3DbUser

  echo -n "Choose a database password: [defaults: " $DEFAULT_TYPO3_DATABASE_PASSWORD"] : "
  read -r typo3DbPassword
  if [ -z "$typo3DbPassword" ]; then typo3DbPassword=$DEFAULT_TYPO3_DATABASE_PASSWORD; fi
  export TYPO3_DATABASE_PASSWORD=$typo3DbPassword
fi

if [ -z $TYPO3_VERSION ]; then
  echo "Must set env var TYPO3_VERSION (e.g. dev-main or ^10.4)"
  exit 1
fi

if ! wget --version > /dev/null 2>&1
then
  echo "Couldn't find wget."
  exit 1
fi

echo "Using TYPO3 Version: $TYPO3_VERSION"
echo "Using database host: $TYPO3_DATABASE_HOST"
echo "Using database dbname: $TYPO3_DATABASE_NAME"
echo "Using database user: $TYPO3_DATABASE_USERNAME"
echo "Using database password: $TYPO3_DATABASE_PASSWORD"

# shellcheck disable=SC2034
COMPOSER_NO_INTERACTION=1

composer config repositories.elts composer https://elts.typo3.com

echo "Installing test environment"
if ! composer tests:setup
then
  echo "The test environment could not be installed by composer as expected. Please fix this issue."
  exit 1
fi

echo "Install third party tools globally:"
# shellcheck disable=SC2155
export PATH=$PATH:$(composer config --global home)/vendor/bin
if ! composer tests:setup:global-require
then
  "The test environment could not be installed by composer as expected. Please fix this issue."
  exit 1
fi
