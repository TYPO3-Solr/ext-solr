<?php

defined('TYPO3_MODE') || die();

(function () {
    if (TYPO3_MODE == 'BE') {
        $modulePrefix = 'extensions-solr-module';
        $svgProvider = \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class;
        /* @var \ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration $extensionConfiguration */
        $extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration::class
        );

        // register all module icons with extensions-solr-module-modulename
        $extIconPath = 'EXT:solr/Resources/Public/Images/Icons/';
        /* @var \TYPO3\CMS\Core\Imaging\IconRegistry $iconRegistry */
        $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
        $iconRegistry->registerIcon($modulePrefix . '-main', $svgProvider,
            ['source' => $extIconPath . 'ModuleSolrMain.svg']);
        $iconRegistry->registerIcon($modulePrefix . '-solr-core-optimization', $svgProvider,
            ['source' => $extIconPath . 'ModuleCoreOptimization.svg']);
        $iconRegistry->registerIcon($modulePrefix . '-index-administration', $svgProvider,
            ['source' => $extIconPath . 'ModuleIndexAdministration.svg']);
        // all connections
        $iconRegistry->registerIcon($modulePrefix . '-initsolrconnections', $svgProvider,
            ['source' => $extIconPath . 'InitSolrConnections.svg']);
        // single connection - context menu
        $iconRegistry->registerIcon($modulePrefix . '-initsolrconnection', $svgProvider,
            ['source' => $extIconPath . 'InitSolrConnection.svg']);
        // register plugin icon
        $iconRegistry->registerIcon('extensions-solr-plugin-contentelement', $svgProvider,
            ['source' => $extIconPath . 'ContentElement.svg']);

        // Add Main module "APACHE SOLR".
        // Acces to a main module is implicit, as soon as a user has access to at least one of its submodules. To make it possible, main module must be registered in that way and without any Actions!
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
            'searchbackend',
            '',
            '',
            null,
            [
                'name' => 'searchbackend',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod.xlf',
                'iconIdentifier' => 'extensions-solr-module-main'
            ]
        );

        $treeComponentId = 'TYPO3/CMS/Backend/PageTree/PageTreeElement';

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'ApacheSolrForTypo3.solr',
            'searchbackend',
            'Info',
            '',
            [
                'Backend\\Search\\InfoModule' => 'index, switchSite, switchCore, documentsDetails',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleInfo.svg',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_info.xlf',
                'navigationComponentId' => $treeComponentId
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'ApacheSolrForTypo3.solr',
            'searchbackend',
            'CoreOptimization',
            '',
            [
                'Backend\\Search\\CoreOptimizationModule' => 'index, addSynonyms, importSynonymList, deleteAllSynonyms, exportSynonyms, deleteSynonyms, saveStopWords, importStopWordList, exportStopWords, switchSite, switchCore'
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleCoreOptimization.svg',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_coreoptimize.xlf',
                'navigationComponentId' => $treeComponentId
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'ApacheSolrForTypo3.solr',
            'searchbackend',
            'IndexQueue',
            '',
            [
                'Backend\\Search\\IndexQueueModule' => 'index, initializeIndexQueue, requeueDocument, resetLogErrors, showError, doIndexingRun, switchSite'
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleIndexQueue.svg',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_indexqueue.xlf',
                'navigationComponentId' => $treeComponentId
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'ApacheSolrForTypo3.solr',
            'searchbackend',
            'IndexAdministration',
            '',
            [
                'Backend\\Search\\IndexAdministrationModule' => 'index, emptyIndex, clearIndexQueue, reloadIndexConfiguration, switchSite'
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleIndexAdministration.svg',
                'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_indexadmin.xlf',
                'navigationComponentId' => $treeComponentId
            ]
        );

        // registering reports
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['solr'] = [
            \ApacheSolrForTypo3\Solr\Report\SiteHandlingStatus::class,
            \ApacheSolrForTypo3\Solr\Report\SchemaStatus::class,
            \ApacheSolrForTypo3\Solr\Report\SolrConfigStatus::class,
            \ApacheSolrForTypo3\Solr\Report\SolrConfigurationStatus::class,
            \ApacheSolrForTypo3\Solr\Report\SolrStatus::class,
            \ApacheSolrForTypo3\Solr\Report\SolrVersionStatus::class,
            \ApacheSolrForTypo3\Solr\Report\AccessFilterPluginInstalledStatus::class,
            \ApacheSolrForTypo3\Solr\Report\AllowUrlFOpenStatus::class,
            \ApacheSolrForTypo3\Solr\Report\FilterVarStatus::class
        ];
    }

    if ((TYPO3_MODE === 'BE') || (TYPO3_MODE === 'FE' && isset($_POST['TSFE_EDIT']))) {
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


# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

$isComposerMode = defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
if (!$isComposerMode) {
    // we load the autoloader for our libraries
    $dir = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr');
    require $dir . '/Resources/Private/Php/ComposerLibraries/vendor/autoload.php';
}
