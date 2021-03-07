#!/usr/bin/env bash

if [[ ! -z ${BASH_SOURCE[0]} ]]; then
    SCRIPTPATH=$( cd $(dirname ${BASH_SOURCE[0]}) ; pwd -P )
else
    SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )
fi

EXTENSION_ROOTPATH="$SCRIPTPATH/../../"
SOLR_INSTALL_PATH="/opt/solr-tomcat/"

DEFAULT_TYPO3_VERSION="^10.4"
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
    echo -n "Choose a TYPO3 Version [dafults: " $DEFAULT_TYPO3_VERSION"] : "
    read typo3Version
    if [ -z "$typo3Version" ]; then typo3Version=$DEFAULT_TYPO3_VERSION; fi
    export TYPO3_VERSION=$typo3Version

    echo -n "Choose a php-cs-fixer version [dafults: " $DEFAULT_PHP_CS_FIXER_VERSION"] : "
    read phpCSFixerVersion
    if [ -z "$phpCSFixerVersion" ]; then phpCSFixerVersion=$DEFAULT_PHP_CS_FIXER_VERSION; fi
    export PHP_CS_FIXER_VERSION=$phpCSFixerVersion

    echo -n "Choose a database hostname: [dafults: " $DEFAULT_TYPO3_DATABASE_HOST"] : "
    read typo3DbHost
    if [ -z "$typo3DbHost" ]; then typo3DbHost=$DEFAULT_TYPO3_DATABASE_HOST; fi
    export TYPO3_DATABASE_HOST=$typo3DbHost

    echo -n "Choose a database name: [dafults: " $DEFAULT_TYPO3_DATABASE_NAME"] : "
    read typo3DbName
    if [ -z "$typo3DbName" ]; then typo3DbName=$DEFAULT_TYPO3_DATABASE_NAME; fi
    export TYPO3_DATABASE_NAME=$typo3DbName

    echo -n "Choose a database user: [dafults: " $DEFAULT_TYPO3_DATABASE_USERNAME"] : "
    read typo3DbUser
    if [ -z "$typo3DbUser" ]; then typo3DbUser=$DEFAULT_TYPO3_DATABASE_USERNAME; fi
    export TYPO3_DATABASE_USERNAME=$typo3DbUser

    echo -n "Choose a database password: [dafults: " $DEFAULT_TYPO3_DATABASE_PASSWORD"] : "
    read typo3DbPassword
    if [ -z "$typo3DbPassword" ]; then typo3DbPassword=$DEFAULT_TYPO3_DATABASE_PASSWORD; fi
    export TYPO3_DATABASE_PASSWORD=$typo3DbPassword
fi

echo "Using TYPO3 Version: $TYPO3_VERSION"
echo "Using PHP-CS Fixer Version: $PHP_CS_FIXER_VERSION"
echo "Using database host: $TYPO3_DATABASE_HOST"
echo "Using database dbname: $TYPO3_DATABASE_NAME"
echo "Using database user: $TYPO3_DATABASE_USERNAME"
echo "Using database password: $TYPO3_DATABASE_PASSWORD"

if [ -z $TYPO3_VERSION ]; then
	echo "Must set env var TYPO3_VERSION (e.g. dev-master or ^10.4)"
	exit 1
fi

if ! wget --version > /dev/null 2>&1
then
	echo "Couldn't find wget."
	exit 1
fi

# Install build tools
echo "Install build tools: "
if ! composer global require \
  friendsofphp/php-cs-fixer:"$PHP_CS_FIXER_VERSION" \
  namelesscoder/typo3-repository-client
then
	echo "The build tools(php-cs-fixer, typo3-repository-client) could not be installed. Please fix this issue."
	exit 1
fi


# Setup TYPO3 environment variables
export TYPO3_PATH_PACKAGES="${EXTENSION_ROOTPATH}.Build/vendor/"
export TYPO3_PATH_WEB="${EXTENSION_ROOTPATH}.Build/Web/"
echo "Using extension path $EXTENSION_ROOTPATH"
echo "Using package path $TYPO3_PATH_PACKAGES"
echo "Using web path $TYPO3_PATH_WEB"

# Install TYPO3 sources
if [[ $TYPO3_VERSION = *"master"* ]]; then
    composer config minimum-stability dev
    TYPO3_MASTER_DEPENDENCIES='nimut/testing-framework:dev-master'
fi

if ! composer require --dev --update-with-dependencies --prefer-source \
  typo3/cms-core:"$TYPO3_VERSION" \
  typo3/cms-backend:"$TYPO3_VERSION" \
  typo3/cms-recordlist:"$TYPO3_VERSION" \
  typo3/cms-fluid:"$TYPO3_VERSION" \
  typo3/cms-frontend:"$TYPO3_VERSION" \
  typo3/cms-extbase:"$TYPO3_VERSION" \
  typo3/cms-reports:"$TYPO3_VERSION" \
  typo3/cms-scheduler:"$TYPO3_VERSION" \
  typo3/cms-tstemplate:"$TYPO3_VERSION" \
  typo3/cms-install:"$TYPO3_VERSION" $TYPO3_MASTER_DEPENDENCIES
then
	echo "The test environment could not be . Please fix this issue."
	exit 1
fi

mkdir -p $TYPO3_PATH_WEB/uploads $TYPO3_PATH_WEB/typo3temp

if [[ $* != *--skip-solr-install* ]]; then
    # Setup Solr Using our install script
    echo "Setup Solr Using our install script: "
    chmod 500 ${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh
    if ! ${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh -d "$HOME/solr" -t
    then
      echo "Apache Solr server could not be installed or started. Please fix this issue."
      exit 1
    fi
fi
