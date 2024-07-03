<?php

declare(strict_types=1);

use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create();

if (getenv('IS_ON_GITHUB_ACTIONS') === 'true') {
    $config = $config->setHideProgress(true);
}

$config
    ->addRules(
        [
            'ordered_imports' => [
                'imports_order' => ['class', 'function', 'const'],
                'sort_algorithm' => 'alpha'
            ],
            'general_phpdoc_annotation_remove' => [
                'annotations' => [
                    'author', 'autor',
                    'copyright',
                ]
            ],
        ],
    )
    ->getFinder()
    ->exclude([
        '.Build'
    ])
    ->in(__DIR__);
return $config;
