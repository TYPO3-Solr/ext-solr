<?php

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die('Access denied.');

(static function () {
    if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface
        && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()
    ) {
        // Register Context Sensitive Help (CSH) translation labels
        ExtensionManagementUtility::addLLrefForTCAdescr(
            'pages',
            'EXT:solr/Resources/Private/Language/locallang_csh_pages.xlf'
        );
    }
})();

// ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

$isComposerMode = defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
if (!$isComposerMode) {
    // we load the autoloader for our libraries
    $dir = ExtensionManagementUtility::extPath('solr');
    require $dir . '/Resources/Private/Php/ComposerLibraries/vendor/autoload.php';
}
