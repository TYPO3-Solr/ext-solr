<?php

use ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper\TestAdditionalPageIndexer;
use ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper\TestPageIndexerDocumentsModifier;

defined('TYPO3') || die();

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments']['TestAdditionalPageIndexer'] = TestAdditionalPageIndexer::class;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['preAddModifyDocuments']['TestPageIndexerDocumentsModifier'] = TestPageIndexerDocumentsModifier::class;

$GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids'] = 1;
