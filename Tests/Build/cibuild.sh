#!/usr/bin/env bash

echo "PWD: $(pwd)"

export TYPO3_PATH_WEB=$(pwd)/.Build/Web

echo "Check psr-2 compliance"
output=$(.Build/bin/php-cs-fixer fix -v --dry-run Classes);
if [[ $output ]]; then
    while read -r line; do
        echo -e "\e[00;31m$line\e[00m";
    done <<< "$output";
fi

echo "Run unit tests"
.Build/bin/phpunit --colors -c Tests/Build/UnitTests.xml

echo "Run functional tests"
export typo3DatabaseName="typo3";
export typo3DatabaseHost="localhost";
export typo3DatabaseUsername="root";
export typo3DatabasePassword="";

.Build/bin/phpunit --colors -c Tests/Build/FunctionalTests.xml
