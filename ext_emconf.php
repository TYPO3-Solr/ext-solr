<?php
$EM_CONF[$_EXTKEY] = array(
    'title' => 'Apache Solr for TYPO3 - Enterprise Search',
    'description' => 'Apache Solr for TYPO3 is the enterprise search server you were looking for with special features such as Faceted Search or Synonym Support and incredibly fast response times of results within milliseconds.',
    'version' => '5.0.0-dev',
    'state' => 'stable',
    'category' => 'plugin',
    'author' => 'Ingo Renner',
    'author_email' => 'ingo@typo3.org',
    'author_company' => 'dkd Internet Service GmbH',
    'module' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'constraints' => array(
        'depends' => array(
            'scheduler' => '',
            'typo3' => '7.6.0-8.0.99'
        ),
        'conflicts' => array(),
        'suggests' => array(
            'devlog' => '',
        ),
    ),
    'autoload' => array(
        'classmap' => array(
            'Resources/Private/Php/'
        ),
        'psr-4' => array(
            'ApacheSolrForTypo3\\Solr\\' => 'Classes/',
            'ApacheSolrForTypo3\\Solr\\Tests\\' => 'Tests/'
        )
    )
);
