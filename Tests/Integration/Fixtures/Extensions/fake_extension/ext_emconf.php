<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Fake Extension',
    'description' => 'Fake Extension Test',
    'category' => 'test',
    'version' => '1.0.0',
    'state' => 'beta',
    'author' => 'Benni Mack',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.3-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
