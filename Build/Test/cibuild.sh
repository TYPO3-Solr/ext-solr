#!/usr/bin/env bash

EXIT_CODE=0

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
  EXIT_CODE=1
else
  echo "No syntax errors! Great job!"
fi


echo "TYPO3 Coding Standards compliance: See https://github.com/TYPO3/coding-standards"
if ! composer t3:standards:fix -- --diff --verbose --dry-run && rm .php-cs-fixer.cache
then
  echo "Some files are not compliant to TYPO3 Coding Standards"
  echo "Please fix the files listed above."
  echo "Tip for auto fix: "
  echo "  TYPO3_VERSION=\"${TYPO3_VERSION}\" composer tests:setup && composer t3:standards:fix"
  EXIT_CODE=3
else
  echo "The code is TYPO3 Coding Standards compliant! Great job!"
fi
echo -e "\n\n"

echo "Run XML Lint"
if ! composer tests:lint-xml
then
  EXIT_CODE=4
fi

echo -e "\n\n"
echo "Run PHPStan analysis"
if ! composer tests:phpstan
then
  # disable fail temporary
  EXIT_CODE=7
  echo "Error during running the PHPStan analysis, please check and fix them."
  echo "Tip for working on them: "
  echo "  TYPO3_VERSION=\"${TYPO3_VERSION}\" composer tests:setup && composer tests:phpstan"
fi

echo -e "\n\n"
echo "Run unit tests"
if ! composer tests:unit -- --coverage-clover=coverage.unit.clover
then
  echo "Error during running the unit tests please check and fix them"
  EXIT_CODE=5
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
if ! composer tests:integration -- --coverage-clover=coverage.integration.clover
then
  echo "Error during running the integration tests please check and fix them"
  EXIT_CODE=6
fi

exit $EXIT_CODE
