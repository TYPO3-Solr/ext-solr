#!/usr/bin/env bash

SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )
EXTENSION_ROOTPATH="$SCRIPTPATH/../../"
SOLR_INSTALL_PATH="/opt/solr-tomcat/"

if [[ $* == *--local* ]]; then
    echo -n "Choose a TYPO3 Version (e.g. dev-master,~6.2.17,~7.6.2): "
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
fi

if [ -z $TYPO3_VERSION ]; then
	echo "Must set env var TYPO3_VERSION (e.g. dev-master or ~7.6.0)"
	exit 1
fi

wget --version > /dev/null 2>&1
if [ $? -ne "0" ]; then
	echo "Couldn't find wget."
	exit 1
fi

export TYPO3_PATH_PACKAGES="${EXTENSION_ROOTPATH}.Build/vendor/"
export TYPO3_PATH_WEB="${EXTENSION_ROOTPATH}.Build/Web/"

echo "Using extension path $EXTENSION_ROOTPATH"
echo "Using package path $TYPO3_PATH_PACKAGES"
echo "Using web path $TYPO3_PATH_WEB"

if [[ $TYPO3_VERSION == "dev-master" ]]; then
    # For dev-master we need to use the new testing framework
    # after dropping 7.x support we need to change this in the patched files
    composer require --dev typo3/cms="$TYPO3_VERSION"
    composer require --dev --prefer-source typo3/testing-framework="1.0.1"

    sed  -i 's/Core\Tests\FunctionalTestCase as TYPO3IntegrationTest/TYPO3\TestingFramework\Core\FunctionalTestCase as TYPO3IntegrationTest/g' Tests/Integration/IntegrationTest.php
    sed  -i 's/Core\Tests\UnitTestCase as TYPO3UnitTest/TYPO3\TestingFramework\Core\UnitTestCase as TYPO3UnitTest/g' Tests/Unit/UnitTest.php
else
    composer require --dev --prefer-source typo3/cms="$TYPO3_VERSION"
fi

# Restore composer.json
git checkout composer.json

mkdir -p $TYPO3_PATH_WEB/uploads $TYPO3_PATH_WEB/typo3temp

# Setup Solr Using our install script
chmod 500 ${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh
${EXTENSION_ROOTPATH}Resources/Private/Install/install-solr.sh -d "$HOME/solr" -t