<?php

declare(strict_types=1);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config
    ->addRules(
        [
            'ordered_imports' => [
                'imports_order' => ['class', 'function', 'const'],
                'sort_algorithm' => 'alpha'
            ]
        ],
    )
    ->getFinder()
    ->exclude([
        '.Build'
    ])
    ->in(__DIR__);
return $config;
