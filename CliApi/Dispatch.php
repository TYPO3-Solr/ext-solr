<?php

if (!defined('TYPO3_cliMode')) {
	die('You cannot run this script directly!');
}

$cliDispatcher = t3lib_div::makeInstance('Tx_Solr_Cli_Dispatcher');
$cliDispatcher->dispatch();


?>