<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}
	// Adds JavaScript for page tree context menu to the BE
if (is_object($TYPO3backend)) {
	$pageRenderer = $GLOBALS['TBE_TEMPLATE']->getPageRenderer();

	$javascriptPath = t3lib_extMgm::extRelPath('solr') . 'resources/javascript/contextmenu/';
	$pageRenderer->addJsFile($javascriptPath . 'initializesolrconnectionsclickmenuaction.js');
}

?>