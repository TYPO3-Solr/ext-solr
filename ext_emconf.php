<?php

########################################################################
# Extension Manager/Repository config file for ext "solr".
#
# Auto generated 12-11-2012 16:14
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Apache Solr for TYPO3 - Enterprise Search',
    'description' => 'Apache Solr for TYPO3 is the enterprise search server you were looking for with special features such as Faceted Search or Synonym Support and incredibly fast response times of results within milliseconds.',
    'category' => 'plugin',
    'author' => 'Ingo Renner',
    'author_email' => 'ingo@typo3.org',
    'module' => '',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'author_company' => 'dkd Internet Service GmbH',
    'version' => '3.1.1',
    'constraints' => array(
        'depends' => array(
            'scheduler' => '',
            'php' => '5.3.7',
            'typo3' => '6.2.1-7.99.99',
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
