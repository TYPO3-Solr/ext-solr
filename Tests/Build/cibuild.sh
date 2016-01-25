#!/usr/bin/env bash

echo "PWD: $(pwd)"

export TYPO3_PATH_WEB=$(pwd)/.Build/Web

if [ $TRAVIS ]; then
    # Travis does not have composer's bin dir in $PATH
    export PATH="$PATH:$HOME/.composer/vendor/bin"
fi

php-cs-fixer --version > /dev/null 2>&1
if [ $? -eq "0" ]; then
    echo "Check PSR-2 compliance"
    php-cs-fixer fix -v --level=psr2 --dry-run Classes

    if [ $? -ne "0" ]; then
        echo "Some files are not PSR-2 compliant"
        echo "Please fix the files listed above"
        exit 1
    fi
fi

echo "Run unit tests"
.Build/bin/phpunit --colors -c Tests/Build/UnitTests.xml

echo "Run integration tests"

#
# Map the travis and shell variable names to the expected
# casing of the TYPO3 core.
#
if [ -n $TYPO3_DATABASE_NAME ]; then
	export typo3DatabaseName=$TYPO3_DATABASE_NAME
else
	echo "No environment variable TYPO3_DATABASE_NAME set. Please set it to run the integration tests."
	exit 1
fi

if [ -n $TYPO3_DATABASE_HOST ]; then
	export typo3DatabaseHost=$TYPO3_DATABASE_HOST
else
	echo "No environment variable TYPO3_DATABASE_HOST set. Please set it to run the integration tests."
	exit 1
fi

if [ -n $TYPO3_DATABASE_USERNAME ]; then
	export typo3DatabaseUsername=$TYPO3_DATABASE_USERNAME
else
	echo "No environment variable TYPO3_DATABASE_USERNAME set. Please set it to run the integration tests."
	exit 1
fi

if [ -n $TYPO3_DATABASE_PASSWORD ]; then
	export typo3DatabasePassword=$TYPO3_DATABASE_PASSWORD
else
	echo "No environment variable TYPO3_DATABASE_PASSWORD set. Please set it to run the integration tests."
	exit 1
fi

.Build/bin/phpunit --colors -c Tests/Build/IntegrationTests.xml
