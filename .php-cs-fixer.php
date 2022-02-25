<?php declare(strict_types=1);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->exclude([
        '.Build'
    ])
    ->in(__DIR__);
return $config;
