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
    $bitmapProvider = 'TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider';
    $svgProvider = 'TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider';

        // register all module icons with extensions-solr-module-modulename
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Imaging\IconRegistry');
    $iconRegistry->registerIcon($modulePrefix . '-administration', $svgProvider,
        array('source' => $extIconPath . 'ModuleAdministration.svg'));
    $iconRegistry->registerIcon($modulePrefix . '-overview', $bitmapProvider,
        array('source' => $extIconPath . 'Search.png'));
    $iconRegistry->registerIcon($modulePrefix . '-indexqueue', $bitmapProvider,
        array('source' => $extIconPath . 'IndexQueue.png'));
    $iconRegistry->registerIcon($modulePrefix . '-indexmaintenance', $bitmapProvider,
        array('source' => $extIconPath . 'IndexMaintenance.png'));
    $iconRegistry->registerIcon($modulePrefix . '-indexfields', $bitmapProvider,
        array('source' => $extIconPath . 'IndexFields.png'));
    $iconRegistry->registerIcon($modulePrefix . '-stopwords', $bitmapProvider,
        array('source' => $extIconPath . 'StopWords.png'));
    $iconRegistry->registerIcon($modulePrefix . '-synonyms', $bitmapProvider,
        array('source' => $extIconPath . 'Synonyms.png'));
    $iconRegistry->registerIcon($modulePrefix . '-initsolrconnections', $bitmapProvider,
        array('source' => $extIconPath . 'InitSolrConnections.png'));
}

if (TYPO3_MODE == 'BE') {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'tools',
        'administration',
        '',
        array(
            // An array holding the controller-action-combinations that are accessible
            'Administration' => 'index,setSite,setCore'
        ),
        array(
            'access' => 'admin',
            'icon' => 'EXT:solr/Resources/Public/Images/Icons/ModuleAdministration.svg',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/ModuleAdministration/locallang.xlf',
        )
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'Overview',
        array('index')
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'IndexQueue',
        array('index,initializeIndexQueue,resetLogErrors,clearIndexQueue')
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'IndexMaintenance',
        array('index,cleanUpIndex,emptyIndex,reloadIndexConfiguration')
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'IndexFields',
        array('index')
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'StopWords',
        array('index,saveStopWords')
    );

    ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
        'ApacheSolrForTypo3.' . $_EXTKEY,
        'Synonyms',
        array('index,addSynonyms,deleteSynonyms')
    );


    // registering reports
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['solr'] = array(
        'ApacheSolrForTypo3\\Solr\\Report\\SchemaStatus',
        'ApacheSolrForTypo3\\Solr\\Report\\SolrConfigStatus',
        'ApacheSolrForTypo3\\Solr\\Report\\SolrConfigurationStatus',
        'ApacheSolrForTypo3\\Solr\\Report\\SolrStatus',
        'ApacheSolrForTypo3\\Solr\\Report\\SolrVersionStatus',
        'ApacheSolrForTypo3\\Solr\\Report\\AccessFilterPluginInstalledStatus',
        'ApacheSolrForTypo3\\Solr\\Report\\AllowUrlFOpenStatus',
        'ApacheSolrForTypo3\\Solr\\Report\\FilterVarStatus'
    );

    // Index Inspector
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
        'web_info',
        'ApacheSolrForTypo3\\Solr\\Backend\\IndexInspector\\IndexInspector',
        null,
        'LLL:EXT:solr/Resources/Private/Language/Backend/locallang.xml:module_indexinspector'
    );

    // register Clear Cache Menu hook
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions']['clearSolrConnectionCache'] = \ApacheSolrForTypo3\Solr\ConnectionManager::class;

    // register Clear Cache Menu ajax call
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerAjaxHandler(
        'solr::clearSolrConnectionCache',
        'ApacheSolrForTypo3\\Solr\\ConnectionManager->updateConnections'
    );


    // the order of registering the garbage collector and the record monitor is important!
    // for certain scenarios items must be removed by GC first, and then be re-added to to Index Queue

    // hooking into TCE Main to monitor record updates that may require deleting documents from the index
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = '&ApacheSolrForTypo3\\Solr\\GarbageCollector';
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = '&ApacheSolrForTypo3\\Solr\\GarbageCollector';

    // hooking into TCE Main to monitor record updates that may require reindexing by the index queue
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = 'ApacheSolrForTypo3\\Solr\\IndexQueue\\RecordMonitor';
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'ApacheSolrForTypo3\\Solr\\IndexQueue\\RecordMonitor';
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
    'ApacheSolrForTypo3\Solr\ContextMenuActionController',
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
