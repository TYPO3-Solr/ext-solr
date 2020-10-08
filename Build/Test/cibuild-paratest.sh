#!/usr/bin/env bash

#print System-Hardware Information
echo "System-Hardware Information:"
echo "CPUs: " "$(nproc)"
echo "RAM: "
free -hg

CWD=$(pwd)

# Defaults
DEFAULT_TYPO3_PATH_WEB=$CWD"/.Build/Web/"
DEFAULT_TYPO3_PATH_VENDOR=$CWD"/.Build/vendor/"
DEFAULT_UNIT_BOOTSTRAP=$DEFAULT_TYPO3_PATH_VENDOR"nimut/testing-framework/res/Configuration/UnitTestsBootstrap.php"
DEFAULT_INTEGRATION_BOOTSTRAP=$DEFAULT_TYPO3_PATH_VENDOR"nimut/testing-framework/res/Configuration/FunctionalTestsBootstrap.php"
DEFAULT_TYPO3_BIN_DIR=$CWD"/.Build/bin/"

DEFAULT_COMPOSER_BIN_DIR="$HOME/.composer/vendor/bin"

## Settings via environment variables
if [[ -v TYPO3_PATH_WEB && -d "${TYPO3_PATH_WEB}" ]]; then
  echo "Using public path provided by environment variable TYPO3_PATH_WEB=""$TYPO3_PATH_WEB"
else
  export TYPO3_PATH_WEB=$DEFAULT_TYPO3_PATH_WEB
fi

if [[ -v TYPO3_PATH_VENDOR && -d "${TYPO3_PATH_VENDOR}" ]]; then
  echo "Using composer vendor path provided by environment variable TYPO3_PATH_VENDOR=""$TYPO3_PATH_VENDOR"
else
  export TYPO3_PATH_VENDOR=$DEFAULT_TYPO3_PATH_VENDOR
fi

if [[ -v TYPO3_BIN_DIR && -d "${TYPO3_BIN_DIR}" ]]; then
  echo "Using TYPO3 bin path provided by environment variable TYPO3_BIN_DIR=""$TYPO3_BIN_DIR"
else
  export TYPO3_BIN_DIR=$DEFAULT_TYPO3_BIN_DIR
fi

if [[ -v COMPOSER_BIN_DIR && -d "${COMPOSER_BIN_DIR}" ]]; then
  echo "Using public path provided by environment variable COMPOSER_BIN_DIR=""$COMPOSER_BIN_DIR"
else
  export COMPOSER_BIN_DIR=$DEFAULT_COMPOSER_BIN_DIR
fi
## End: Settings via environment variables

## Add TYPO3_BIN_DIR and COMPOSER_BIN_DIR to $PATH
export PATH="$TYPO3_BIN_DIR:$COMPOSER_BIN_DIR:$PATH"

function runPHPsLinterSyntaxCheckOnly() {
  echo "Run PHP Lint"
  if ! find "." -name "*.php" ! -path "./.Build/*" | parallel --gnu php -d display_errors=stderr -l {} > /dev/null ;
  then
    echo "You have syntax errors in your code, please fix them from above and try again." | tee >(cat >&2)
    exit 101
  fi
}


function runPhpCsFixer() {
  # use from vendor dir
  if ! php-cs-fixer --version > /dev/null 2>&1;
  then
    echo "php-cs-fixer is not installed, please make sure it is installed." | tee >(cat >&2)
    exit 102
  fi

  echo "Check PSR-2 compliance"
  if ! php-cs-fixer fix --diff --verbose --dry-run --rules='{"function_declaration": {"closure_function_spacing": "none"}}' Classes;
  then
    echo "Some files are not PSR-2 compliant" | tee >(cat >&2)
    echo "Please fix the files listed above" | tee >(cat >&2)
    exit 103
  fi
}


function runUnitTests() {
  echo "Run unit tests"

  if [[ -v UNIT_BOOTSTRAP && -f "${UNIT_BOOTSTRAP}" ]]; then
    echo "Using bootstrap for unit tests from environment variable UNIT_BOOTSTRAP=""$UNIT_BOOTSTRAP"
  else
    UNIT_BOOTSTRAP=$DEFAULT_UNIT_BOOTSTRAP
  fi

  if ! .Build/bin/paratest \
    --runner=WrapperRunner \
    --configuration=Build/Test/UnitTests.xml \
    --bootstrap=$UNIT_BOOTSTRAP \
    --coverage-clover=coverage.unit.clover \
    --colors ;
  then
      echo "Error during running the unit tests please check and fix them" | tee >(cat >&2)
      exit 104
  fi
}

#
# Map the travis and shell variable names to the expected
# casing of the TYPO3 core.
#
if [[ -n $TYPO3_DATABASE_NAME ]]; then
	export typo3DatabaseName=$TYPO3_DATABASE_NAME
else
	echo "No environment variable TYPO3_DATABASE_NAME set. Please set it to run the integration tests." | tee >(cat >&2)
	exit 10
fi

if [[ -n $TYPO3_DATABASE_HOST ]]; then
	export typo3DatabaseHost=$TYPO3_DATABASE_HOST
else
	echo "No environment variable TYPO3_DATABASE_HOST set. Please set it to run the integration tests." | tee >(cat >&2)
	exit 11
fi

if [[ -n $TYPO3_DATABASE_USERNAME ]]; then
	export typo3DatabaseUsername=$TYPO3_DATABASE_USERNAME
else
	echo "No environment variable TYPO3_DATABASE_USERNAME set. Please set it to run the integration tests." | tee >(cat >&2)
	exit 12
fi

# DB password can be empty, no needs to check.
export typo3DatabasePassword=$TYPO3_DATABASE_PASSWORD

function removeInTestsUnusedCores() {
  # only danish, german and english cores are currently used in integration tests.
  CORES_TO_REMOVE=(
    "arabic" "basque" "bulgarian" "catalan" "czech" "dutch" "finnish" "galician" "greek" "hungarian" \
    "irish" "japanese" "korean" "latvia" "persian" "portuguese" "russian" "spanish" "thai" "ukrainian" \
    "armenian" "brazilian_portuguese" "burmese" "chinese" "french" "hindi" "indonesian" \
    "italian" "khmer" "lao" "norwegian" "polish" "romanian" "serbian" "swedish" "turkish"
  )
  for CORE_TO_REMOVE in "${CORES_TO_REMOVE[@]}"
  do
    rm -Rf "Resources/Private/Solr/cores/""$CORE_TO_REMOVE"
  done
}

removeInTestsUnusedCores

function scaleSolrServers() {
  echo "Start $(nproc) Solr Servers in docker."
  removeInTestsUnusedCores
  docker-compose --project-name=travis-test-build --file=Build/testing-via-docker/docker-compose.yaml up --scale solr-test-node="$(nproc)" -d
  docker ps
}

function shutdownSolrServers() {
  docker-compose --project-name=travis-test-build --file=Build/testing-via-docker/docker-compose.yaml down --rmi=all
}


function runIntegrationTests() {
  echo "Run integration tests"
  scaleSolrServers

  if [[ -v INTEGRATION_BOOTSTRAP && -f "${INTEGRATION_BOOTSTRAP}" ]]; then
    echo "Using bootstrap for unit tests from environment variable UNIT_BOOTSTRAP=""$UNIT_BOOTSTRAP"
  else
    INTEGRATION_BOOTSTRAP=$DEFAULT_INTEGRATION_BOOTSTRAP
  fi

  if ! .Build/bin/paratest \
    --runner=WrapperRunner \
    --configuration=Build/Test/IntegrationTests.xml \
    --exclude-group=frontend \
    --bootstrap="$INTEGRATION_BOOTSTRAP" \
    --coverage-clover=coverage.integration.clover \
    --colors;
  then
      shutdownSolrServers
      echo "Error during running the integration tests please check and fix them" | tee >(cat >&2)
      exit 105
  fi

  echo "Run frontend-related integration tests"
  if ! .Build/bin/paratest \
    --runner=WrapperRunner \
    --configuration=Build/Test/IntegrationFrontendTests.xml \
    --bootstrap="$INTEGRATION_BOOTSTRAP" \
    --group=frontend \
    --coverage-clover=coverage.integration.clover \
    --colors;
  then
    shutdownSolrServers
    echo "Error during running the frontend-related integration tests please check and fix them" | tee >(cat >&2)
    exit 106
  fi
  shutdownSolrServers
}

### atomic or sequential test scenario calls
VIA_PARAMETER_CALLED=0

if [[ $* == *--lint* ]]; then
  VIA_PARAMETER_CALLED=1
  runPHPsLinterSyntaxCheckOnly
fi

if [[ $* == *--php-cs-fixer* ]]; then
  VIA_PARAMETER_CALLED=1
  runPhpCsFixer
fi

if [[ $* == *--unit* ]]; then
  VIA_PARAMETER_CALLED=1
  runUnitTests
fi

if [[ $* == *--integration* ]]; then
  VIA_PARAMETER_CALLED=1
  runIntegrationTests
fi

#
if [[ $VIA_PARAMETER_CALLED -eq 1 ]]; then
  exit 0
fi
### End: atomic or sequential test scenario calls


# call in standard sequence if no params provided
runPHPsLinterSyntaxCheckOnly
runPhpCsFixer
runUnitTests
runIntegrationTests
