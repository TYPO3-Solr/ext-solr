#!/usr/bin/env bash

SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )
EXTENSION_ROOTPATH="$SCRIPTPATH/../../"

if [ -z $TYPO3_VERSION ]; then
	echo "Must set env var TYPO3_VERSION"
	exit 1
fi

wget --version > /dev/null 2>&1
if [ $? -ne "0" ]; then
	echo "Couldn't find wget."
	exit 1
fi



if [[ $TYPO3_VERSION == ~6.2.* ]]; then
	composer require --dev typo3/cms="$TYPO3_VERSION" typo3/cms-composer-installers="1.2.2 as 1.1.4"
else
	composer require --dev typo3/cms="$TYPO3_VERSION"
fi

# Restore composer.json
git checkout composer.json

export TYPO3_PATH_WEB=$SCRIPTPATH/.Build/Web

mkdir -p $TYPO3_PATH_WEB/uploads $TYPO3_PATH_WEB/typo3temp


# Setup Solr Using our install script
sudo ${EXTENSION_ROOTPATH}Resources/Install/install-solr-tomcat.sh