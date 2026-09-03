#!/usr/bin/env php
<?php

/*
 * Derives the inputs for Build/Helpers/BUILD_TER_ZIP.sh from a composer manifest
 * on STDIN, so the caller can pass the committed one rather than the working tree.
 *
 * PHP to avoid a system dependency on a JSON parser, like its sibling
 * GET_LOCAL_PACKAGE_VERSION_CONSTRAINT.sh.
 */

$manifest = json_decode((string)stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$typo3 = $manifest['extra']['typo3/cms'] ?? [];

$key = $typo3['extension-key'] ?? null;
if ($key === null) {
    fwrite(STDERR, 'extra.typo3/cms.extension-key is not defined' . PHP_EOL);
    exit(1);
}

$version = $typo3['version'] ?? null;
if ($version === null) {
    fwrite(STDERR, 'extra.typo3/cms.version is not defined' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'key=' . $key . PHP_EOL);
fwrite(STDOUT, 'version=' . $version . PHP_EOL);

$provides = $typo3['Package']['providesPackages'] ?? [];
$require = $manifest['require'] ?? [];

// Only directly required packages are installed; the rest arrive as their
// dependencies and are declared so classic mode knows they exist.
foreach ($provides as $package => $vendorPath) {
    if (isset($require[$package])) {
        fwrite(STDOUT, sprintf("bundle=%s\t%s\t%s\n", $package, $require[$package], $vendorPath));
    }
}

foreach ($provides as $package => $vendorPath) {
    fwrite(STDOUT, sprintf("provides=%s\t%s\n", $package, $vendorPath));
}
