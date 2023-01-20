<?php

use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexAdministrationModuleController;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die('Access denied.');

(function () {
    if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface
        && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()
    ) {
        $modulePrefix = 'extensions-solr-module';
        $svgProvider = SvgIconProvider::class;
        /* @var ExtensionConfiguration $extensionConfiguration */
        $extensionConfiguration = GeneralUtility::makeInstance(
            ExtensionConfiguration::class
        );

        // register all module icons with extensions-solr-module-modulename
        $extIconPath = 'EXT:solr/Resources/Public/Images/Icons/';
        /* @var IconRegistry $iconRegistry */
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $iconRegistry->registerIcon(
            $modulePrefix . '-main',
            $svgProvider,
            ['source' => $extIconPath . 'ModuleSolrMain.svg']
        );
        $iconRegistry->registerIcon(
            $modulePrefix . '-solr-core-optimization',
            $svgProvider,
            ['source' => $extIconPath . 'ModuleCoreOptimization.svg']
        );
        $iconRegistry->registerIcon(
            $modulePrefix . '-index-administration',
            $svgProvider,
            ['source' => $extIconPath . 'ModuleIndexAdministration.svg']
        );
        // all connections
        $iconRegistry->registerIcon(
            $modulePrefix . '-initsolrconnections',
            $svgProvider,
            ['source' => $extIconPath . 'InitSolrConnections.svg']
        );
        // single connection - context menu
        $iconRegistry->registerIcon(
            $modulePrefix . '-initsolrconnection',
            $svgProvider,
            ['source' => $extIconPath . 'InitSolrConnection.svg']
        );
        // register plugin icon
        $iconRegistry->registerIcon(
            'extensions-solr-plugin-contentelement',
            $svgProvider,
            ['source' => $extIconPath . 'ContentElement.svg']
        );

        // Add Main module "APACHE SOLR".
        // Acces to a main module is implicit, as soon as a user has access to at least one of its submodules. To make it possible, main module must be registered in that way and without any Actions!
        ExtensionManagementUtility::addModule(
            'searchbackend',
            '',
            '',
            null,
            [
                'name' => 'searchbackend',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod.xlf',
                'iconIdentifier' => 'extensions-solr-module-main',
            ]
        );

        $treeComponentId = 'TYPO3/CMS/Backend/PageTree/PageTreeElement';

        ExtensionUtility::registerModule(
            'Solr',
            'searchbackend',
            'Info',
            '',
            [
                \ApacheSolrForTypo3\Solr\Controller\Backend\Search\InfoModuleController::class => 'index, switchSite, switchCore, documentsDetails',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleInfo.svg',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_info.xlf',
                'navigationComponentId' => $treeComponentId,
            ]
        );

        ExtensionUtility::registerModule(
            'Solr',
            'searchbackend',
            'CoreOptimization',
            '',
            [
                \ApacheSolrForTypo3\Solr\Controller\Backend\Search\CoreOptimizationModuleController::class => 'index, addSynonyms, importSynonymList, deleteAllSynonyms, exportSynonyms, deleteSynonyms, saveStopWords, importStopWordList, exportStopWords, switchSite, switchCore',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleCoreOptimization.svg',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_coreoptimize.xlf',
                'navigationComponentId' => $treeComponentId,
            ]
        );

        ExtensionUtility::registerModule(
            'Solr',
            'searchbackend',
            'IndexQueue',
            '',
            [
                \ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexQueueModuleController::class =>
                    'index, initializeIndexQueue, clearIndexQueue, requeueDocument, resetLogErrors, showError, doIndexingRun, switchSite',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleIndexQueue.svg',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_indexqueue.xlf',
                'navigationComponentId' => $treeComponentId,
            ]
        );

        ExtensionUtility::registerModule(
            'Solr',
            'searchbackend',
            'IndexAdministration',
            '',
            [
                IndexAdministrationModuleController::class =>
                    'index, emptyIndex, clearIndexQueue, reloadIndexConfiguration, switchSite',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleIndexAdministration.svg',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_indexadmin.xlf',
                'navigationComponentId' => $treeComponentId,
            ]
        );

        // Register Context Sensitive Help (CSH) translation labels
        ExtensionManagementUtility::addLLrefForTCAdescr(
            'pages',
            'EXT:solr/Resources/Private/Language/locallang_csh_pages.xlf'
        );
    }

    if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface
        && (
            ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()
            || (
                ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend()
                &&
                isset($_POST['TSFE_EDIT'])
            )
        )
    ) {
        // the order of registering the garbage collector and the record monitor is important!
        // for certain scenarios items must be removed by GC first, and then be re-added to to Index Queue

        // hooking into TCE Main to monitor record updates that may require deleting documents from the index
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \ApacheSolrForTypo3\Solr\GarbageCollector::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \ApacheSolrForTypo3\Solr\GarbageCollector::class;

        // hooking into TCE Main to monitor record updates that may require reindexing by the index queue
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor::class;
    }
})();

// ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

$isComposerMode = defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
if (!$isComposerMode) {
    // we load the autoloader for our libraries
    $dir = ExtensionManagementUtility::extPath('solr');
    require $dir . '/Resources/Private/Php/ComposerLibraries/vendor/autoload.php';
}
