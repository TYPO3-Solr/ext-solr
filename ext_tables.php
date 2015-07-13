<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

	// TODO change to a constant, so that it can't get manipulated
$GLOBALS['PATH_solr']    = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr');
$GLOBALS['PATHrel_solr'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('solr');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding the search plugin
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
	array(
		'LLL:EXT:solr/locallang_db.xml:tt_content.list_type_pi_results',
		$_EXTKEY . '_pi_results'
	),
	'list_type'
);
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY . '_pi_results'] = 'layout,select_key,pages,recursive';
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY . '_pi_results'] = 'pi_flexform';

	// add flexform to pi_results
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($_EXTKEY . '_pi_results', 'FILE:EXT:solr/Configuration/FlexForms/Results.xml');

	// add search plugin to content element wizard
if (TYPO3_MODE == 'BE') {
	$TBE_MODULES_EXT['xMOD_db_new_content_el']['addElClasses']['Tx_Solr_Backend_ContentElementWizardIconProvider'] =
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Classes/Backend/ContentElementWizardIconProvider.php';
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding the Search Form plugin
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
	array(
		'LLL:EXT:solr/locallang_db.xml:tt_content.list_type_pi_search',
		$_EXTKEY . '_pi_search'
	),
	'list_type'
);
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY . '_pi_search'] = 'layout,select_key,pages,recursive';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding the Frequent Searches plugin
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
	array(
		'LLL:EXT:solr/locallang_db.xml:tt_content.list_type_pi_frequentsearches',
		$_EXTKEY . '_pi_frequentsearches'
	),
	'list_type'
);
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY . '_pi_frequentsearches'] = 'layout,select_key,pages,recursive';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// TypoScript
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/Solr/', 'Apache Solr - Default Configuration');

	// OpenSearch
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/OpenSearch/', 'Apache Solr - OpenSearch');

	// Extension Pre-Configuration
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/Examples/IndexQueueNews/', 'Apache Solr - Index Queue Configuration for news');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/Examples/IndexQueueNewsContentElements/', 'Apache Solr - Index Queue Configuration for news with content elements');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/Examples/IndexQueueTtNews/', 'Apache Solr - Index Queue Configuration for tt_news');

	// Examples
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/Examples/BoostQueries/', 'Apache Solr Example - Boost more recent results');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/Examples/EverythingOn/', 'Apache Solr Example - Everything On');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/Examples/FilterPages/', 'Apache Solr Example - Filter to only show page results');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/Examples/IntroPackageSearchBox/', 'Apache Solr Example - Replace Introduction Package search box');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

$iconPath = $GLOBALS['PATHrel_solr'] . 'Resources/Public/Images/Icons/';
\TYPO3\CMS\Backend\Sprite\SpriteManager::addSingleIcons(
	array(
		'ModuleOverview' => $iconPath . 'Search.png',
		'ModuleIndexQueue' => $iconPath . 'IndexQueue.png',
		'ModuleIndexMaintenance' => $iconPath . 'IndexMaintenance.png',
		'ModuleIndexFields' => $iconPath . 'IndexFields.png',
		'ModuleSynonyms' => $iconPath . 'Synonyms.png'
	),
	$_EXTKEY
);

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
			'icon' => 'EXT:' . $_EXTKEY . '/Resources/Public/Images/Icons/ModuleAdministration.png',
			'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/ModuleAdministration.xlf',
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
		'Synonyms',
		array('index,addSynonyms,deleteSynonyms')
	);



	// registering reports
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['solr'] = array(
		'Tx_Solr_Report_SchemaStatus',
		'Tx_Solr_Report_SolrconfigStatus',
		'Tx_Solr_Report_SolrConfigurationStatus',
		'Tx_Solr_Report_SolrStatus',
		'Tx_Solr_Report_SolrVersionStatus',
		'Tx_Solr_Report_AccessFilterPluginInstalledStatus',
		'Tx_Solr_Report_AllowUrlFOpenStatus',
		'Tx_Solr_Report_FilterVarStatus'
	);

	// Index Inspector
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
		'web_info',
		'Tx_Solr_ModIndex_IndexInspector',
		$GLOBALS['PATH_solr'] . 'ModIndex/IndexInspector.php',
		'LLL:EXT:solr/Resources/Private/Language/Backend.xml:module_indexinspector'
	);

	// register Clear Cache Menu hook
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions']['clearSolrConnectionCache'] = '&Tx_Solr_ConnectionManager';

	// register Clear Cache Menu ajax call
	$TYPO3_CONF_VARS['BE']['AJAX']['solr::clearSolrConnectionCache'] = 'Tx_Solr_ConnectionManager->updateConnections';


	// the order of registering the garbage collector and the record monitor is important!
	// for certain scenarios items must be removed by GC first, and then be re-added to to Index Queue

	// hooking into TCE Main to monitor record updates that may require deleting documents from the index
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = '&Tx_Solr_GarbageCollector';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = '&Tx_Solr_GarbageCollector';

	// hooking into TCE Main to monitor record updates that may require reindexing by the index queue
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = 'Tx_Solr_IndexQueue_RecordMonitor';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'Tx_Solr_IndexQueue_RecordMonitor';

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
	icon = ' . \TYPO3\CMS\Core\Utility\GeneralUtility::locationHeaderUrl($GLOBALS['PATHrel_solr'] . 'Resources/Images/cache-init-solr-connections.png') . '
	displayCondition = getRecord|is_siteroot = 1
	callbackAction = initializeSolrConnections
}

options.contextMenu.table.pages.items.851 = DIVIDER
[global]
');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerExtDirectComponent(
	'TYPO3.Solr.ContextMenuActionController',
	$GLOBALS['PATHrel_solr'] . 'Classes/ContextMenuActionController.php:Tx_Solr_ContextMenuActionController',
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



