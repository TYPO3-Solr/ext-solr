<?php

declare(strict_types=1);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
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
            'no_superfluous_phpdoc_tags' => true
        ],
    )
    ->getFinder()
    ->exclude([
        '.Build'
    ])
    ->in(__DIR__);
return $config;
