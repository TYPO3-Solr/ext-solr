#!/usr/bin/env bash
set -e

export TYPO3_BIN_DIR="$(pwd)/.Build/bin/"
export COMPOSER_BIN_DIR="$HOME/.composer/vendor/bin"

# Add TYPO3_BIN_DIR and COMPOSER_BIN_DIR to $PATH
export PATH="$TYPO3_BIN_DIR:$COMPOSER_BIN_DIR:$PATH"

ls -la
ocular code-coverage:upload --format=php-clover coverage.unit.clover
ocular code-coverage:upload --format=php-clover coverage.integration.clover