<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/*
 * Guards against silently losing a test case.
 *
 * Build/Test/known-tests.txt records every test method of every suite with the number of cases
 * it contributes, data sets included. The check fails when a recorded method disappears or
 * contributes fewer cases than before. Additions never fail, so adding tests needs no update to
 * the record — only a removal or a rename does, which is exactly the change that has to be
 * justified in review.
 *
 *   composer tests:known-tests             # check, exit 1 on loss
 *   composer tests:known-tests:update      # re-record, after a justified removal
 *
 * It serves every EXT:solr add-on from this one copy, because it inspects a *target directory*
 * rather than its own location: the suites are every Build/Test/*Tests.xml there and the record
 * sits beside them, so the format stays identical across the family. The target defaults to the
 * current working directory, which is what an add-on needs:
 *
 *   "tests:known-tests": [
 *     "@php .Build/vendor/apache-solr-for-typo3/solr/Build/Test/known-tests.php"
 *   ]
 *
 * That reaches into the installed EXT:solr the same way EXT:solrfal's tests:setup already copies
 * .php-cs-fixer.php out of it. Like every other add-on-local test script it addresses the
 * standalone layout, which is the one CI uses.
 *
 * `--target=<dir>` and forwarding of any further argument to phpunit cover the solr-ddev-site
 * monorepo, where the packages are symlinked into the root vendor and some add-ons pin a
 * bootstrap inside their own absent .Build/vendor — the root's tests:<addon>:known-tests passes
 * the same `--bootstrap=` override its tests:<addon>:unit already does. The recorded ids do not
 * depend on the context either way.
 */

$argumentValue = static function (string $name) use ($argv): ?string {
    foreach ($argv as $argument) {
        if (str_starts_with($argument, '--' . $name . '=')) {
            return substr($argument, strlen($name) + 3);
        }
    }
    return null;
};

$target = rtrim($argumentValue('target') ?? (getcwd() ?: '.'), '/');
$record = $target . '/Build/Test/known-tests.txt';
$suites = glob($target . '/Build/Test/*Tests.xml') ?: [];
sort($suites);

if ($suites === []) {
    printf("No Build/Test/*Tests.xml in %s — nothing to record.\n", $target);
    exit(0);
}

$forwarded = implode(' ', array_map(
    'escapeshellarg',
    array_values(array_filter(
        array_slice($argv, 1),
        static fn(string $argument): bool => $argument !== '--update'
            && !str_starts_with($argument, '--target='),
    )),
));

/**
 * @param string[] $suites
 * @return array<string, int> test method => number of cases it contributes
 */
$collect = static function (array $suites) use ($forwarded): array {
    $counts = [];
    foreach ($suites as $configuration) {
        $lines = [];
        $command = trim(sprintf(
            'phpunit --config=%s --list-tests %s',
            escapeshellarg($configuration),
            $forwarded,
        )) . ' 2>/dev/null';
        exec($command, $lines, $exitCode);
        if ($exitCode !== 0) {
            fwrite(STDERR, sprintf("Could not list the tests of %s\n  %s\n", $configuration, $command));
            exit(2);
        }
        foreach ($lines as $line) {
            // " - Vendor\Class::method" optionally followed by a data set label
            if (preg_match('/^\s*-\s+(\S+?)::([A-Za-z_]\w*)/', $line, $matches) !== 1) {
                continue;
            }
            $test = $matches[1] . '::' . $matches[2];
            $counts[$test] = ($counts[$test] ?? 0) + 1;
        }
    }
    ksort($counts);

    return $counts;
};

$current = $collect($suites);

if (in_array('--update', $argv, true)) {
    $lines = [];
    foreach ($current as $test => $cases) {
        $lines[] = $test . "\t" . $cases;
    }
    file_put_contents($record, implode("\n", $lines) . "\n");
    printf("Recorded %d test methods, %d cases.\n", count($current), array_sum($current));
    exit(0);
}

if (!is_file($record)) {
    fwrite(STDERR, sprintf("No record at %s — create it with --update.\n", $record));
    exit(2);
}

$recorded = [];
foreach (file($record, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    [$test, $cases] = explode("\t", $line);
    $recorded[$test] = (int)$cases;
}

$gone = [];
$shrunk = [];
foreach ($recorded as $test => $cases) {
    if (!isset($current[$test])) {
        $gone[] = $test;
        continue;
    }
    if ($current[$test] < $cases) {
        $shrunk[] = sprintf('%s (%d → %d cases)', $test, $cases, $current[$test]);
    }
}

if ($gone === [] && $shrunk === []) {
    printf(
        "No test lost. %d recorded methods, %d present, %d cases.\n",
        count($recorded),
        count($current),
        array_sum($current),
    );
    exit(0);
}

fwrite(STDERR, "Test cases were lost.\n\n");
foreach ($gone as $test) {
    fwrite(STDERR, '  gone:   ' . $test . "\n");
}
foreach ($shrunk as $test) {
    fwrite(STDERR, '  shrunk: ' . $test . "\n");
}
fwrite(
    STDERR,
    "\nA test may only disappear when the production code it asserts was removed. If that code\n"
    . "moved, move the test with it. When a removal is intended, say why in the commit message and\n"
    . "re-record with: composer tests:known-tests:update\n",
);
exit(1);
