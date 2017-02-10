<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}
// Adds JavaScript for page tree context menu to the BE
if (is_object($TYPO3backend)) {
    /** @var \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer */
    $pageRenderer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);

    // @Todo This should be removed when we don't support 7.6 LTS anymore
    if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_branch) >= \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger('8.0')) {
        $pageRenderer->addJsFile('EXT:solr/Resources/JavaScript/ContextMenu/initializesolrconnectionsclickmenuaction.js');
    } else {
        $pageRenderer->addJsFile(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('solr') . 'Resources/JavaScript/ContextMenu/initializesolrconnectionsclickmenuaction.js');
    }
}
