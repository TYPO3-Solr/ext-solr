#!/usr/bin/env bash
set -e

wget https://github.com/scrutinizer-ci/ocular/releases/download/1.6.0/ocular.phar
php ocular.phar code-coverage:upload --format=php-clover coverage.unit.clover
php ocular.phar code-coverage:upload --format=php-clover coverage.integration.clover
php ocular.phar code-coverage:upload --format=php-clover coverage.integration.frontend.clover
rm ocular.phar