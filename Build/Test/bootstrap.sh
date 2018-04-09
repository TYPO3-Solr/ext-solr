#!/usr/bin/env bash

SCRIPTPATH=$( cd $(dirname ${BASH_SOURCE[0]}) ; pwd -P )
EXTENSION_ROOTPATH="$SCRIPTPATH/../../"
SOLR_INSTALL_PATH="/opt/solr-tomcat/"

if [[ $* == *--local* ]]; then
    echo -n "Choose a TYPO3 Version (e.g. dev-master,^8.7): "
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

    echo -n "Choose a php-cs-fixer version (v2.3.2): "
    read phpCSFixerVersion
    export PHP_CS_FIXER_VERSION=$phpCSFixerVersion
fi

if [ -z $TYPO3_VERSION ]; then
	echo "Must set env var TYPO3_VERSION (e.g. dev-master or ^8.7)"
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
if [[ $TYPO3_VERSION = *"9."* ]]; then
    # only needed until https://github.com/symfony/symfony/issues/26757 is resolved
    composer require --dev symfony/finder:4.0.6

    composer require --dev typo3/cms-backend="$TYPO3_VERSION"
    composer require --dev typo3/cms-core="$TYPO3_VERSION"
    composer require --dev typo3/cms-fluid="$TYPO3_VERSION"
    composer require --dev typo3/cms-frontend="$TYPO3_VERSION"
    composer require --dev typo3/cms-lang="$TYPO3_VERSION"
    composer require --dev typo3/cms-extbase="$TYPO3_VERSION"
    composer require --dev typo3/cms-extbase="$TYPO3_VERSION"
    composer require --dev typo3/cms-reports="$TYPO3_VERSION"
    composer require --dev typo3/cms-scheduler="$TYPO3_VERSION"
    composer require --dev typo3/cms-tstemplate="$TYPO3_VERSION"
else
    composer require --dev typo3/cms="$TYPO3_VERSION"
fi

# Restore composer.json
git checkout composer.json
mkdir -p $TYPO3_PATH_WEB/uploads $TYPO3_PATH_WEB/typo3temp

# Setup Solr Using our install script
chmod 500 ${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh
${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh -d "$HOME/solr" -t