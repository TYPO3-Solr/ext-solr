#!/usr/bin/env bash

if [[ ! -z ${BASH_SOURCE[0]} ]]; then
    SCRIPTPATH=$( cd $(dirname ${BASH_SOURCE[0]}) ; pwd -P )
else
    SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )
fi

EXTENSION_ROOTPATH="$SCRIPTPATH/../../"
SOLR_INSTALL_PATH="/opt/solr-tomcat/"

DEFAULT_TYPO3_VERSION="10"
DEFAULT_PHP_CS_FIXER_VERSION="^2.16.1"
DEFAULT_TYPO3_DATABASE_HOST="localhost"
DEFAULT_TYPO3_DATABASE_NAME="test"
DEFAULT_TYPO3_DATABASE_USERNAME="root"
DEFAULT_TYPO3_DATABASE_PASSWORD="supersecret"

if [[ $* == *--use-defaults* ]]; then
  export TYPO3_VERSION=$DEFAULT_TYPO3_VERSION
  export PHP_CS_FIXER_VERSION=$DEFAULT_PHP_CS_FIXER_VERSION
  export TYPO3_DATABASE_HOST=$DEFAULT_TYPO3_DATABASE_HOST
  export TYPO3_DATABASE_NAME=$DEFAULT_TYPO3_DATABASE_NAME
  export TYPO3_DATABASE_USERNAME=$DEFAULT_TYPO3_DATABASE_USERNAME
  export TYPO3_DATABASE_PASSWORD=$DEFAULT_TYPO3_DATABASE_PASSWORD
fi

if [[ $* == *--local* ]]; then
  echo -n "Choose a TYPO3 Version (e.g. dev-master,^10.4): "
  read typo3Version
  export TYPO3_VERSION=$typo3Version

  echo -n "Choose a php-cs-fixer version (v2.16.1): "
  read phpCSFixerVersion
  export PHP_CS_FIXER_VERSION=$phpCSFixerVersion

  echo -n "Choose a database hostname: "
  read typo3DbHost
  export TYPO3_DATABASE_HOST=$typo3DbHost

  echo -n "Choose a database name: "
  read typo3DbName
  export TYPO3_DATABASE_NAME=$typo3DbName

  echo -n "Choose a database user: "
  read typo3DbUser
  export TYPO3_DATABASE_USERNAME=$typo3DbUser

  echo -n "Choose a database password: "
  read typo3DbPassword
  export TYPO3_DATABASE_PASSWORD=$typo3DbPassword
fi

echo "Using TYPO3 Version: $TYPO3_VERSION"
echo "Using PHP-CS Fixer Version: $PHP_CS_FIXER_VERSION"
echo "Using database host: $TYPO3_DATABASE_HOST"
echo "Using database dbname: $TYPO3_DATABASE_NAME"
echo "Using database user: $TYPO3_DATABASE_USERNAME"

if [ -z $TYPO3_VERSION ]; then
	echo "Must set env var TYPO3_VERSION (e.g. dev-master or ^9.5)"
	exit 1
fi

if ! wget --version > /dev/null 2>&1
then
  echo "Couldn't find wget."
  exit 1
fi

# Use latest TYPO3 LTS stable version, if version number is compatible with get.typo3.org API
if [[ $TYPO3_VERSION =~ ^[0-9]+$ ]] ; then
  TYPO3_VERSION=$("${BASH_SOURCE%/*}/../Helpers/TYPO3_GET_LATEST_VERSION.sh" "$TYPO3_VERSION")
fi

# Install build tools
echo "Install build tools: "
composer global require friendsofphp/php-cs-fixer:"$PHP_CS_FIXER_VERSION"
composer global require namelesscoder/typo3-repository-client

# Setup TYPO3 environment variables
export TYPO3_PATH_PACKAGES="${EXTENSION_ROOTPATH}.Build/vendor/"
export TYPO3_PATH_WEB="${EXTENSION_ROOTPATH}.Build/Web/"
echo "Using extension path $EXTENSION_ROOTPATH"
echo "Using package path $TYPO3_PATH_PACKAGES"
echo "Using web path $TYPO3_PATH_WEB"

# Install TYPO3 sources

if [[ $TYPO3_VERSION = *"dev"* ]]; then
  composer config minimum-stability dev
fi

if [[ $TYPO3_VERSION = *"master"* ]]; then
  TYPO3_MASTER_DEPENDENCIES='nimut/testing-framework:dev-master'
fi

# Temporary downgrades
CURRENT_PHP_VERSION=$(php -r "echo PHP_VERSION;" | grep --only-matching --perl-regexp "7.\d+")
if [[ ! $CURRENT_PHP_VERSION = *"7.2"* ]] && [[ $TYPO3_VERSION = *"10.4"* ]]; then
  TYPO3_TEMPORARY_DOWNGRADES="doctrine/dbal:2.11.3"
elif [[ $CURRENT_PHP_VERSION = *"7.2"* ]] && [[ $TYPO3_VERSION = *"10.4"* ]]; then
  TYPO3_TEMPORARY_DOWNGRADES="doctrine/dbal:2.10.4"
fi

if ! composer require --dev --update-with-dependencies --prefer-source \
  typo3/cms-core:"$TYPO3_VERSION" \
  typo3/cms-backend:"$TYPO3_VERSION" \
  typo3/cms-fluid:"$TYPO3_VERSION" \
  typo3/cms-frontend:"$TYPO3_VERSION" \
  typo3/cms-extbase:"$TYPO3_VERSION" \
  typo3/cms-reports:"$TYPO3_VERSION" \
  typo3/cms-scheduler:"$TYPO3_VERSION" \
  typo3/cms-tstemplate:"$TYPO3_VERSION" \
  typo3/cms-install:"$TYPO3_VERSION" $TYPO3_TEMPORARY_DOWNGRADES $TYPO3_MASTER_DEPENDENCIES
then
	echo "The test environment could not be installed by composer as expected. Please fix this issue."
	exit 1
fi

# Restore composer.json
mkdir -p $TYPO3_PATH_WEB/uploads $TYPO3_PATH_WEB/typo3temp


if [[ $* != *--skip-solr-install* ]]; then
  # Setup Solr Using our install script
  echo "Setup Solr Using our install script: "
  chmod 500 ${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh
  ${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh -d "$HOME/solr" -t
fi
