<?php

if (!defined('TYPO3_cliMode')) {
	die('You cannot run this script directly!');
}

$cliDispatcher = t3lib_div::makeInstance('tx_solr_cli_Dispatcher');
$cliDispatcher->dispatch();


?>