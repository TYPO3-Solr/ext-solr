<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Apache Solr for TYPO3 - Enterprise Search',
    'description' => 'Apache Solr for TYPO3 is the enterprise search server you were looking for with special features such as Faceted Search or Synonym Support and incredibly fast response times of results within milliseconds.',
    'version' => '12.0.8',
    'state' => 'stable',
    'category' => 'plugin',
    'author' => 'Rafael Kaehm, Markus Friedrich',
    'author_email' => 'info@dkd.de',
    'author_company' => 'dkd Internet Service GmbH',
    'constraints' => [
        'depends' => [
            'scheduler' => '',
            'typo3' => '12.4.3-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'classmap' => [
            'Resources/Private/Php/',
        ],
        'psr-4' => [
            'ApacheSolrForTypo3\\Solr\\' => 'Classes/',
        ],
    ],
];
