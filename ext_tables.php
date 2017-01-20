<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

// TODO change to a constant, so that it can't get manipulated
$GLOBALS['PATH_solr'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr');
$GLOBALS['PATHrel_solr'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('solr');

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// add search plugin to content element wizard
if (TYPO3_MODE == 'BE') {
    $TBE_MODULES_EXT['xMOD_db_new_content_el']['addElClasses']['ApacheSolrForTypo3\\Solr\\Backend\\ContentElementWizardIconProvider'] =
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Classes/Backend/ContentElementWizardIconProvider.php';
}
# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

$extIconPath = 'EXT:solr/Resources/Public/Images/Icons/';
if (TYPO3_MODE === 'BE') {
    $modulePrefix = 'extensions-solr-module';
    $bitmapProvider = \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class;
    $svgProvider = \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class;

        // register all module icons with extensions-solr-module-modulename
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    $iconRegistry->registerIcon($modulePrefix . '-administration', $svgProvider,
        ['source' => $extIconPath . 'ModuleAdministration.svg']);
    $iconRegistry->registerIcon($modulePrefix . '-overview', $bitmapProvider,
        ['source' => $extIconPath . 'Search.png']);
    $iconRegistry->registerIcon($modulePrefix . '-indexqueue', $bitmapProvider,
        ['source' => $extIconPath . 'IndexQueue.png']);
    $iconRegistry->registerIcon($modulePrefix . '-indexmaintenance', $bitmapProvider,
        ['source' => $extIconPath . 'IndexMaintenance.png']);
    $iconRegistry->registerIcon($modulePrefix . '-indexfields', $bitmapProvider,
        ['source' => $extIconPath . 'IndexFields.png']);
    $iconRegistry->registerIcon($modulePrefix . '-stopwords', $bitmapProvider,
        ['source' => $extIconPath . 'StopWords.png']);
    $iconRegistry->registerIcon($modulePrefix . '-synonyms', $bitmapProvider,
        ['source' => $extIconPath . 'Synonyms.png']);
    $iconRegistry->registerIcon($modulePrefix . '-searchstatistics', $bitmapProvider,
        ['source' => $extIconPath . 'SearchStatistics.png']);
    $iconRegistry->registerIcon($modulePrefix . '-initsolrconnections', $svgProvider,
        ['source' => $extIconPath . 'InitSolrConnections.svg']);
}

if (TYPO3_MODE == 'BE') {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'tools',
        'administration',
        '',
        [
            // An array holding the controller-action-combinations that are accessible
            'Administration' => 'index,setSite,setCore,noSiteAvailable'
        ],
        [
            'access' => 'admin',
            'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleAdministration.svg',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xlf',
        ]
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'Overview',
        ['index']
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'IndexQueue',
        ['index,initializeIndexQueue,resetLogErrors,clearIndexQueue']
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'IndexMaintenance',
        ['index,cleanUpIndex,emptyIndex,reloadIndexConfiguration']
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'IndexFields',
        ['index']
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'SearchStatistics',
        ['index']
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'StopWords',
        ['index,saveStopWords']
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'Synonyms',
        ['index,addSynonyms,deleteSynonyms']
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

    // Index Inspector
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
        'web_info',
        \ApacheSolrForTypo3\Solr\Backend\IndexInspector\IndexInspector::class,
        null,
        'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:module_indexinspector'
    );

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

// register click menu item to initialize the Solr connections for a single site
// visible for admin users only
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('
[adminUser = 1]
options.contextMenu.table.pages.items.850 = ITEM
options.contextMenu.table.pages.items.850 {
	name = Tx_Solr_initializeSolrConnections
	label = Initialize Solr Connections
	iconName = extensions-solr-module-initsolrconnections
	displayCondition = getRecord|is_siteroot = 1
	callbackAction = initializeSolrConnections
}

options.contextMenu.table.pages.items.851 = DIVIDER
[global]
');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerExtDirectComponent(
    'TYPO3.Solr.ContextMenuActionController',
    \ApacheSolrForTypo3\Solr\ContextMenuActionController::class,
    'web',
    'admin'
);

// include JS in backend
$GLOBALS['TYPO3_CONF_VARS']['typo3/backend.php']['additionalBackendItems']['Solr.ContextMenuInitializeSolrConnectionsAction'] = $GLOBALS['PATH_solr'] . 'Classes/BackendItem/ContextMenuActionJavascriptRegistration.php';

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// replace the built-in search content element
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:' . $_EXTKEY . '/Configuration/FlexForms/Results.xml',
    'search'
);

$TCA['tt_content']['types']['search']['showitem'] =
    '--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.general;general,
	--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.header;header,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.plugin,
		pi_flexform;;;;1-1-1,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.access,
		--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.visibility;visibility,
		--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.access;access,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.appearance,
		--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.frames;frames,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.behaviour,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.extended';
