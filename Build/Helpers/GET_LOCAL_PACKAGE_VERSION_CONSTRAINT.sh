#!/usr/bin/env php
<?php

/*
 * This script is made to avoid bash/system dependencies for json-parsing tools.
 * And is made for usage CI/CD usage in TYPO3-Solr addons/projects.
 */
$addOnPath = getenv('EXT_SOLR_ADDON_PATH') ?? null;

$usage = "
  Usage:
    $argv[0] vendor/name
    $argv[0] solarium/solarium

  EXT_SOLR_ADDON_PATH=$(pwd -P)packages/ext-tika $argv[0] typo3/cms-core
";

if (!isset($argv[1])) {
  fwrite(
    STDERR,
    'This script requires a package name from composer.json' .
    $usage
  );
  exit(1);
}

$composerJsonPath = ($addOnPath ?: __DIR__ . '/../..') . '/composer.json';

if (false === realpath($composerJsonPath)) {
  fwrite(
    STDERR,
    "No such file: {$composerJsonPath}" . PHP_EOL
  );
  exit(1);
}

$composerManifest = json_decode(file_get_contents(realpath($composerJsonPath)), true);

$requestedPackageName = $argv[1];
if (isset($composerManifest['require'][$requestedPackageName])) {
  fwrite(STDOUT, $composerManifest['require'][$requestedPackageName] . PHP_EOL);
  exit(0);
}

if (isset($composerManifest['require-dev'][$requestedPackageName])) {
  fwrite(STDOUT, $composerManifest['require-dev'][$requestedPackageName] . PHP_EOL);
  exit(0);
}

fwrite(STDERR, "The package \"{$requestedPackageName}\" is not defined in {$composerJsonPath}");
exit(10);
