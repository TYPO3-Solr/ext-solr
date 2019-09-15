<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Apache Solr for TYPO3 - Enterprise Search',
    'description' => 'Apache Solr for TYPO3 is the enterprise search server you were looking for with special features such as Faceted Search or Synonym Support and incredibly fast response times of results within milliseconds.',
    'version' => '10.0.0-dev',
    'state' => 'stable',
    'category' => 'plugin',
    'author' => 'Ingo Renner, Timo Hund, Markus Friedrich',
    'author_email' => 'ingo@typo3.org',
    'author_company' => 'dkd Internet Service GmbH',
    'clearCacheOnLoad' => 0,
    'constraints' => [
        'depends' => [
            'scheduler' => '',
            'typo3' => '9.5.0-'
        ],
        'conflicts' => [],
        'suggests' => [
            'devlog' => '',
        ],
    ],
    'autoload' => [
        'classmap' => [
            'Resources/Private/Php/'
        ],
        'psr-4' => [
            'ApacheSolrForTypo3\\Solr\\' => 'Classes/',
            'ApacheSolrForTypo3\\Solr\\Tests\\' => 'Tests/'
        ]
    ]
];
