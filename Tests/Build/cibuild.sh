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
    php-cs-fixer fix -v --level=psr2 --dry-run .

    if [ $? -ne "0" ]; then
        echo "Some files are not PSR-2 compliant"
        echo "Please fix the files listed above"
        exit 1
    fi
fi

echo "Run unit tests"
.Build/bin/phpunit --colors -c Tests/Build/UnitTests.xml

echo "Run integration tests"
export typo3DatabaseName="typo3";
export typo3DatabaseHost="localhost";
export typo3DatabaseUsername="root";
export typo3DatabasePassword="";

.Build/bin/phpunit --colors -c Tests/Build/IntegrationTests.xml
