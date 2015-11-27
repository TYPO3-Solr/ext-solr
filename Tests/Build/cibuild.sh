#!/usr/bin/env bash
SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )

echo "PWD: $(pwd)"

export TYPO3_PATH_WEB=$(pwd)/.Build/Web

echo "Check psr-2 compliance"
output=$(php  $SCRIPTPATH/.Build/bin/php-cs-fixer.phar fix -v --dry-run Classes); if [[ $output ]]; then while read -r line; do echo -e "\e[00;31m$line\e[00m"; done <<< "$output"; fi;

echo "Run unit tests"
php $SCRIPTPATH/.Build/bin/phpunit.phar --debug --colors -c Tests/Build/UnitTests.xml
