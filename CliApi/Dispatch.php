<?php

if (!defined('TYPO3_cliMode')) {
	die('You cannot run this script directly!');
}

$cliDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_Cli_Dispatcher');
$cliDispatcher->dispatch();


?>