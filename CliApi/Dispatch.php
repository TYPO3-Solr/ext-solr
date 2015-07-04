<?php

if (!defined('TYPO3_cliMode')) {
	die('You cannot run this script directly!');
}

$cliDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Cli\\Dispatcher');
$cliDispatcher->dispatch();


