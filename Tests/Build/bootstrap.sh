#!/usr/bin/env bash

SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )

mkdir -p $SCRIPTPATH/.Build/bin
wget http://cs.sensiolabs.org/get/php-cs-fixer.phar -O $SCRIPTPATH/.Build/bin/php-cs-fixer.phar
wget https://phar.phpunit.de/phpunit-4.8.9.phar -O $SCRIPTPATH/.Build/bin/phpunit.phar

if [[ $TYPO3_VERSION == ~6.2.* ]]; then
	composer require --dev typo3/cms="$TYPO3_VERSION" typo3/cms-composer-installers="1.2.2 as 1.1.4"
else
	composer require --dev typo3/cms="$TYPO3_VERSION"
fi

git checkout composer.json

export TYPO3_PATH_WEB=$SCRIPTPATH/.Build/Web

mkdir -p $TYPO3_PATH_WEB/uploads $TYPO3_PATH_WEB/typo3temp


