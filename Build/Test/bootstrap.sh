#!/usr/bin/env bash

SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )
EXTENSION_ROOTPATH="$SCRIPTPATH/../../"
SOLR_INSTALL_PATH="/opt/solr-tomcat/"

if [[ $* == *--local* ]]; then
    echo -n "Choose a TYPO3 Version (e.g. dev-master,^8.7,^7.6): "
    read typo3Version
    export TYPO3_VERSION=$typo3Version

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

    echo -n "Choose a php-cs-fixer version (v1.13.1): "
    read phpCSFixerVersion
    export PHP_CS_FIXER_VERSION=$phpCSFixerVersion
fi

if [ -z $TYPO3_VERSION ]; then
	echo "Must set env var TYPO3_VERSION (e.g. dev-master or ^7.6)"
	exit 1
fi

wget --version > /dev/null 2>&1
if [ $? -ne "0" ]; then
	echo "Couldn't find wget."
	exit 1
fi

# Install build tools
composer global require friendsofphp/php-cs-fixer:"$PHP_CS_FIXER_VERSION"
composer global require scrutinizer/ocular:"1.3.1"
composer global require namelesscoder/typo3-repository-client

# Setup TYPO3 environment variables
export TYPO3_PATH_PACKAGES="${EXTENSION_ROOTPATH}.Build/vendor/"
export TYPO3_PATH_WEB="${EXTENSION_ROOTPATH}.Build/Web/"
echo "Using extension path $EXTENSION_ROOTPATH"
echo "Using package path $TYPO3_PATH_PACKAGES"
echo "Using web path $TYPO3_PATH_WEB"

# Install TYPO3 sources
composer require --dev typo3/cms="$TYPO3_VERSION"

# Restore composer.json
git checkout composer.json
mkdir -p $TYPO3_PATH_WEB/uploads $TYPO3_PATH_WEB/typo3temp

# Setup Solr Using our install script
chmod 500 ${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh
${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh -d "$HOME/solr" -t