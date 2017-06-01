<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

if (TYPO3_MODE == 'BE') {
    // add search plugin to content element wizard
    $TBE_MODULES_EXT['xMOD_db_new_content_el']['addElClasses']['ApacheSolrForTypo3\\Solr\\Backend\\ContentElementWizardIconProvider'] =
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Classes/Backend/ContentElementWizardIconProvider.php';

    $modulePrefix = 'extensions-solr-module';
    $svgProvider = \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class;

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
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xlf:module_main_label',
            'iconIdentifier' => 'extensions-solr-module-main'
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'searchbackend',
        'Info',
        '',
        [
            'Backend\\Search\\InfoModule' => 'index, switchSite, switchCore',
            'Backend\\Web\\Info\\ApacheSolrDocument' => 'index'
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleInfo.svg',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xlf:module_info_label',
            'navigationComponentId' => 'typo3-pagetree'
        ]
    );

    // Index Inspector is hidden under Web->Info->Index Inspector
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
        'web_info',
        \ApacheSolrForTypo3\Solr\Backend\IndexInspector\ModuleBootstrap::class,
        null,
        'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:module_indexinspector'
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'searchbackend',
        'CoreOptimization',
        '',
        [
            'Backend\\Search\\CoreOptimizationModule' => 'index, addSynonyms, deleteSynonyms, saveStopWords, switchSite, switchCore'
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleCoreOptimization.svg',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xlf:module_core_optimization_label',
            'navigationComponentId' => 'typo3-pagetree'
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'searchbackend',
        'IndexQueue',
        '',
        [
            'Backend\\Search\\IndexQueueModule' => 'index, initializeIndexQueue, clearIndexQueue, resetLogErrors, showError, doIndexingRun, switchSite'
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleIndexQueue.svg',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xlf:module_indexqueue_label',
            'navigationComponentId' => 'typo3-pagetree'
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'searchbackend',
        'IndexAdministration',
        '',
        [
            'Backend\\Search\\IndexAdministrationModule' => 'index, emptyIndex, clearIndexQueue, reloadIndexConfiguration, switchSite'
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleIndexAdministration.svg',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xlf:solr.backend.index_administration.label',
            'navigationComponentId' => 'typo3-pagetree'
        ]
    );

    // registering reports
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['solr'] = [
        \ApacheSolrForTypo3\Solr\Report\SchemaStatus::class,
        \ApacheSolrForTypo3\Solr\Report\SolrConfigStatus::class,
        \ApacheSolrForTypo3\Solr\Report\SolrConfigurationStatus::class,
        \ApacheSolrForTypo3\Solr\Report\SolrStatus::class,
        \ApacheSolrForTypo3\Solr\Report\SolrVersionStatus::class,
        \ApacheSolrForTypo3\Solr\Report\AccessFilterPluginInstalledStatus::class,
        \ApacheSolrForTypo3\Solr\Report\AllowUrlFOpenStatus::class,
        \ApacheSolrForTypo3\Solr\Report\FilterVarStatus::class
    ];

    // register Clear Cache Menu hook
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions']['clearSolrConnectionCache'] = \ApacheSolrForTypo3\Solr\ConnectionManager::class;
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

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

$GLOBALS['TYPO3_CONF_VARS']['BE']['ContextMenu']['ItemProviders'][1487876780] = \ApacheSolrForTypo3\Solr\ContextMenu\ItemProviders\InitializeConnectionProvider::class;

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerExtDirectComponent(
    'TYPO3.Solr.ContextMenuActionController',
    \ApacheSolrForTypo3\Solr\ContextMenuActionController::class,
    'web',
    'admin'
);

// include JS in backend
$GLOBALS['TYPO3_CONF_VARS']['typo3/backend.php']['additionalBackendItems']['Solr.ContextMenuInitializeSolrConnectionsAction'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr') . 'Classes/BackendItem/ContextMenuActionJavascriptRegistration.php';
