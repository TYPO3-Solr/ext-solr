#!/usr/bin/env bash

echo "PWD: $(pwd)"

export TYPO3_PATH_WEB=$(pwd)/.Build/Web

echo "Check PSR-2 compliance"
.Build/bin/php-cs-fixer fix -v --level=psr2 --dry-run Classes

echo "Run unit tests"
.Build/bin/phpunit --colors -c Tests/Build/UnitTests.xml

echo "Run functional tests"
export typo3DatabaseName="typo3";
export typo3DatabaseHost="localhost";
export typo3DatabaseUsername="root";
export typo3DatabasePassword="";

.Build/bin/phpunit --colors -c Tests/Build/FunctionalTests.xml
