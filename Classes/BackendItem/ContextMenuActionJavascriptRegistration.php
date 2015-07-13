<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}
	// Adds JavaScript for page tree context menu to the BE
if (is_object($TYPO3backend)) {
	$pageRenderer = $GLOBALS['TBE_TEMPLATE']->getPageRenderer();

	$javascriptPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('solr') . 'Resources/JavaScript/ContextMenu/';
	$pageRenderer->addJsFile($javascriptPath . 'initializesolrconnectionsclickmenuaction.js');
}

?>