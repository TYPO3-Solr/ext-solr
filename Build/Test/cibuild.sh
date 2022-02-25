#!/usr/bin/env bash

TYPO3_PATH_WEB="$(pwd)/.Build/Web/"
export TYPO3_PATH_WEB
TYPO3_PATH_PACKAGES="$(pwd)/.Build/vendor/"
export TYPO3_PATH_PACKAGES

TYPO3_BIN_DIR="$(pwd)/.Build/bin/"
export TYPO3_BIN_DIR
export PATH="$TYPO3_BIN_DIR:$PATH"

COMPOSERS_BIN_DIR="$(composer config home)/vendor/bin"
# Add COMPOSERS_BIN_DIR to $PATH, if not present
## Note: That is not https://getcomposer.org/doc/03-cli.md#composer-bin-dir
##       avoid collisions on that.
if [[ $PATH != *"$COMPOSERS_BIN_DIR"* ]]; then
  export PATH="$COMPOSERS_BIN_DIR:$PATH"
fi

echo "PWD: $(pwd)"
echo "COMPOSERS_BIN_DIR: $COMPOSERS_BIN_DIR"
echo "PATH: $PATH"

echo "Run PHP Lint"
if ! find . -name \*.php ! -path "./.Build/*" 2>/dev/null | parallel --gnu php -d display_errors=stderr -l {} > /dev/null
then
  echo "There are syntax errors, please check and fix them."
  exit 1
else
  echo "No syntax errors! Great job!"
fi

# use from vendor dir
if ! php-cs-fixer --version > /dev/null 2>&1
then
  echo "PHP CS Fixer not found, skipping PHP linting."
else
  echo "Check PSR-2 compliance"
  if ! php-cs-fixer fix --diff --verbose --dry-run Classes
  then
    echo "Some files are not PSR-2 compliant"
    echo "Please fix the files listed above"
    exit 1
  fi
fi

echo "Run XML Lint"
if ! xmllint --version > /dev/null 2>&1; then
  echo "XML Lint not found, skipping XML linting."
else
  echo -e "\n\n"
  echo "Check syntax of XML files"
  if ! xmllint Resources/Private/Language/ -p '*.xlf'
  then
    echo "Some XML files are not valid"
    echo "Please fix the files listed above"
    exit 1
  fi
fi


echo -e "\n\n"
echo "Run unit tests"
UNIT_BOOTSTRAP="Build/Test/UnitTestsBootstrap.php"
if ! .Build/bin/phpunit --colors -c Build/Test/UnitTests.xml --bootstrap=$UNIT_BOOTSTRAP --coverage-clover=coverage.unit.clover
then
  echo "Error during running the unit tests please check and fix them"
  exit 1
fi

#
# Map the travis and shell variable names to the expected
# casing of the TYPO3 core.
#
if [[ -n $TYPO3_DATABASE_NAME ]]; then
  export typo3DatabaseName=$TYPO3_DATABASE_NAME
else
  echo "No environment variable TYPO3_DATABASE_NAME set. Please set it to run the integration tests."
  exit 1
fi

if [[ -n $TYPO3_DATABASE_HOST ]]; then
  export typo3DatabaseHost=$TYPO3_DATABASE_HOST
else
  echo "No environment variable TYPO3_DATABASE_HOST set. Please set it to run the integration tests."
  exit 1
fi

if [[ -n $TYPO3_DATABASE_USERNAME ]]; then
  export typo3DatabaseUsername=$TYPO3_DATABASE_USERNAME
else
  echo "No environment variable TYPO3_DATABASE_USERNAME set. Please set it to run the integration tests."
  exit 1
fi

if [[ -n $TYPO3_DATABASE_PASSWORD ]]; then
  export typo3DatabasePassword=$TYPO3_DATABASE_PASSWORD
else
  echo "No environment variable TYPO3_DATABASE_PASSWORD set. Please set it to run the integration tests."
  exit 1
fi

echo -e "\n\n"
echo "Run integration tests"
INTEGRATION_BOOTSTRAP="Build/Test/IntegrationTestsBootstrap.php"
if ! .Build/bin/phpunit --colors -c Build/Test/IntegrationTests.xml --bootstrap=$INTEGRATION_BOOTSTRAP --coverage-clover=coverage.integration.clover
then
  echo "Error during running the integration tests please check and fix them"
  exit 1
fi
