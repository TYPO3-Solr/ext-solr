<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}
// Adds JavaScript for page tree context menu to the BE
if (is_object($TYPO3backend)) {
    /** @var \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer */
    $pageRenderer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);

    $javascriptPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('solr') . 'Resources/JavaScript/ContextMenu/';
    $pageRenderer->addJsFile($javascriptPath . 'initializesolrconnectionsclickmenuaction.js');
}
