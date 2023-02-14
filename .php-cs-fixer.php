<?php

declare(strict_types=1);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config
    ->addRules(['modernize_strpos' => false])
    ->getFinder()
    ->exclude([
        '.Build'
    ])
    ->in(__DIR__);
return $config;
