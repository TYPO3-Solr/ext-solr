#!/usr/bin/env bash

SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )
EXTENSION_ROOTPATH="$SCRIPTPATH/../../"

if [ -z $TYPO3_VERSION ]; then
	echo "Must set env var TYPO3_VERSION (e.g. dev-master or ~7.6.0)"
	exit 1
fi

wget --version > /dev/null 2>&1
if [ $? -ne "0" ]; then
	echo "Couldn't find wget."
	exit 1
fi

composer require --dev typo3/cms="$TYPO3_VERSION"

# Restore composer.json
git checkout composer.json

export TYPO3_PATH_WEB=$SCRIPTPATH/.Build/Web

mkdir -p $TYPO3_PATH_WEB/uploads $TYPO3_PATH_WEB/typo3temp


# Setup Solr Using our install script
sudo ${EXTENSION_ROOTPATH}Resources/Install/install-solr-tomcat.sh
