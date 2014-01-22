<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

	// TODO change to a constant, so that it can't get manipulated
$GLOBALS['PATH_solr']    = t3lib_extMgm::extPath('solr');
$GLOBALS['PATHrel_solr'] = t3lib_extMgm::extRelPath('solr');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

t3lib_div::loadTCA('tt_content');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding the search plugin
t3lib_extMgm::addPlugin(
	array(
		'LLL:EXT:solr/locallang_db.xml:tt_content.list_type_pi_results',
		$_EXTKEY . '_pi_results'
	),
	'list_type'
);
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY . '_pi_results'] = 'layout,select_key,pages,recursive';
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY . '_pi_results'] = 'pi_flexform';

	// add flexform to pi_results
t3lib_extMgm::addPiFlexFormValue($_EXTKEY . '_pi_results', 'FILE:EXT:solr/Flexforms/pi_results.xml');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding the Search Form plugin
t3lib_extMgm::addPlugin(
	array(
		'LLL:EXT:solr/locallang_db.xml:tt_content.list_type_pi_search',
		$_EXTKEY . '_pi_search'
	),
	'list_type'
);
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY . '_pi_search'] = 'layout,select_key,pages,recursive';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding the Frequent Searches plugin
t3lib_extMgm::addPlugin(
	array(
		'LLL:EXT:solr/locallang_db.xml:tt_content.list_type_pi_frequentsearches',
		$_EXTKEY . '_pi_frequentsearches'
	),
	'list_type'
);
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY . '_pi_frequentsearches'] = 'layout,select_key,pages,recursive';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// TypoScript
t3lib_extMgm::addStaticFile($_EXTKEY, 'Static/Solr/', 'Apache Solr');

	// OpenSearch
t3lib_extMgm::addStaticFile($_EXTKEY, 'Static/OpenSearch/', 'Apache Solr - OpenSearch');

	// Examples
t3lib_extMgm::addStaticFile($_EXTKEY, 'Static/Examples/BoostQueries/', 'Apache Solr Example - Boost more recent results');
t3lib_extMgm::addStaticFile($_EXTKEY, 'Static/Examples/EverythingOn/', 'Apache Solr Example - Everything On');
t3lib_extMgm::addStaticFile($_EXTKEY, 'Static/Examples/FilterPages/', 'Apache Solr Example - Filter to only show page results');
t3lib_extMgm::addStaticFile($_EXTKEY, 'Static/Examples/IndexQueueNews/', 'Apache Solr Example - Index Queue Configuration for news');
t3lib_extMgm::addStaticFile($_EXTKEY, 'Static/Examples/IndexQueueTtNews/', 'Apache Solr Example - Index Queue Configuration for tt_news');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

if (TYPO3_MODE == 'BE') {
	if (version_compare(TYPO3_version, '6.0.0', '>=')) {
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

		$iconPath = $GLOBALS['PATHrel_solr'] . 'Resources/Public/Images/Icons/';
		\TYPO3\CMS\Backend\Sprite\SpriteManager::addSingleIcons(
			array(
				'ModuleOverview' => $iconPath . 'Search.png',
				'ModuleIndexQueue' => $iconPath . 'IndexQueue.png',
				'ModuleIndexMaintenance' => $iconPath . 'IndexMaintenance.png',
				'ModuleIndexFields' => $iconPath . 'IndexFields.png'
			),
			$_EXTKEY
		);

		ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
			'ApacheSolrForTypo3.' . $_EXTKEY,
			'Overview',
			array('index')
		);

		ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
			'ApacheSolrForTypo3.' . $_EXTKEY,
			'IndexQueue',
			array('index,initializeIndexQueue')
		);

		ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
			'ApacheSolrForTypo3.' . $_EXTKEY,
			'IndexMaintenance',
			array('index,commitPendingDocuments,cleanUpIndex,emptyIndex,reloadIndexConfiguration')
		);

		ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager::registerModule(
			'ApacheSolrForTypo3.' . $_EXTKEY,
			'IndexFields',
			array('index')
		);
	} else {
		t3lib_extMgm::addModulePath('tools_txsolrMAdmin', t3lib_extMgm::extPath($_EXTKEY) . 'ModAdmin/');
		t3lib_extMgm::addModule('tools', 'txsolrMAdmin', '', t3lib_extMgm::extPath($_EXTKEY) . 'ModAdmin/');
	}

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

	if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < 6000000) {
		// registering the index report with the reports module
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_solr']['index'] = array(
			'title' => 'LLL:EXT:solr/locallang.xml:report_index_title',
			'description' => 'LLL:EXT:solr/locallang.xml:report_index_description',
			'report' => 'Tx_Solr_Report_IndexReport',
			'icon' => 'EXT:solr/Report/tx_solr_report.gif'
		);
	}

	// Index Inspector
	t3lib_extMgm::insertModuleFunction(
		'web_info',
		'Tx_Solr_ModIndex_IndexInspector',
		$GLOBALS['PATH_solr'] . 'ModIndex/IndexInspector.php',
		'LLL:EXT:solr/locallang.xml:module_indexinspector'
	);

	// register Clear Cache Menu hook
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions']['clearSolrConnectionCache'] = '&Tx_Solr_ConnectionManager';

	// register Clear Cache Menu ajax call
	$TYPO3_CONF_VARS['BE']['AJAX']['solr::clearSolrConnectionCache'] = 'Tx_Solr_ConnectionManager->updateConnections';


	// hooking into TCE Main to monitor record updates that may require reindexing by the index queue
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = 'Tx_Solr_IndexQueue_RecordMonitor';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'Tx_Solr_IndexQueue_RecordMonitor';

	// hooking into TCE Main to monitor record updates that may require deleting documents from the index
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = '&Tx_Solr_GarbageCollector';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = '&Tx_Solr_GarbageCollector';

}

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// register click menu item to initialize the Solr connections for a single site
	// visible for admin users only
t3lib_extMgm::addUserTSConfig('
[adminUser = 1]
options.contextMenu.table.pages.items.850 = ITEM
options.contextMenu.table.pages.items.850 {
	name = Tx_Solr_initializeSolrConnections
	label = Initialize Solr Connections
	icon = ' . t3lib_div::locationHeaderUrl($GLOBALS['PATHrel_solr'] . 'Resources/Images/cache-init-solr-connections.png') . '
	displayCondition = getRecord|is_siteroot = 1
	callbackAction = initializeSolrConnections
}

options.contextMenu.table.pages.items.851 = DIVIDER
[global]
');

t3lib_extMgm::registerExtDirectComponent(
	'TYPO3.Solr.ContextMenuActionController',
	$GLOBALS['PATHrel_solr'] . 'Classes/ContextMenuActionController.php:Tx_Solr_ContextMenuActionController',
	'web',
	'admin'
);

	// include JS in backend
$GLOBALS['TYPO3_CONF_VARS']['typo3/backend.php']['additionalBackendItems']['Solr.ContextMenuInitializeSolrConnectionsAction'] = $GLOBALS['PATH_solr'] . 'Classes/BackendItem/ContextMenuActionJavascriptRegistration.php';


# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// replace the built-in search content element
t3lib_extMgm::addPiFlexFormValue(
	'*',
	'FILE:EXT:' . $_EXTKEY . '/Flexforms/pi_results.xml',
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


?>
