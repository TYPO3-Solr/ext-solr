<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

	// TODO change to a constant, so that it can't get manipulated
$PATH_solr    = t3lib_extMgm::extPath('solr');
$PATHrel_solr = t3lib_extMgm::extRelPath('solr');

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
t3lib_extMgm::addPiFlexFormValue($_EXTKEY . '_pi_results', 'FILE:EXT:solr/flexforms/pi_results.xml');

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

	// TypoScript
t3lib_extMgm::addStaticFile($_EXTKEY, 'static/solr/', 'Apache Solr');

	// Examples
t3lib_extMgm::addStaticFile($_EXTKEY, 'static/examples/everything-on/', 'Apache Solr Example - Everything On');
t3lib_extMgm::addStaticFile($_EXTKEY, 'static/examples/filter-pages/', 'Apache Solr Example - Filter to only show page results');
t3lib_extMgm::addStaticFile($_EXTKEY, 'static/examples/indexqueue-ttnews/', 'Apache Solr Example - Index Queue Configuration for tt_news');


   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

if (TYPO3_MODE == 'BE') {
	t3lib_extMgm::addModulePath('tools_txsolrMAdmin', t3lib_extMgm::extPath($_EXTKEY) . 'mod_admin/');
	t3lib_extMgm::addModule('tools', 'txsolrMAdmin', '', t3lib_extMgm::extPath($_EXTKEY) . 'mod_admin/');

		// registering reports
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['solr'] = array(
		'tx_solr_report_SchemaStatus',
		'tx_solr_report_SolrconfigStatus',
#		'tx_solr_report_SolrConfigurationStatus',
		'tx_solr_report_SolrStatus',
		'tx_solr_report_SolrVersionStatus',
		'tx_solr_report_AccessFilterPluginInstalledStatus'
	);

		// registering the index report with the reports module
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_solr']['index'] = array(
		'title'       => 'LLL:EXT:solr/locallang.xml:report_index_title',
		'description' => 'LLL:EXT:solr/locallang.xml:report_index_description',
		'report'      => 'tx_solr_report_IndexReport',
		'icon'        => 'EXT:solr/report/tx_solr_report.gif'
	);

		// Index Inspector
	t3lib_extMgm::insertModuleFunction(
		'web_info',
		'tx_solr_mod_index_IndexInspector',
		$PATH_solr . 'mod_index/class.tx_solr_mod_index_indexinspector.php',
		'LLL:EXT:solr/locallang.xml:module_indexinspector'
	);

		// register Clear Cache Menu hook
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions']['clearSolrConnectionCache'] = 'EXT:solr/classes/class.tx_solr_connectionmanager.php:&tx_solr_ConnectionManager';

		// register Clear Cache Menu ajax call
	$TYPO3_CONF_VARS['BE']['AJAX']['solr::clearSolrConnectionCache'] = 'EXT:solr/classes/class.tx_solr_connectionmanager.php:tx_solr_ConnectionManager->updateConnections';


		// hooking into TCE Main to monitor record updates that may require reindexing by the index queue
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][]  = 'EXT:solr/classes/indexqueue/class.tx_solr_indexqueue_recordmonitor.php:tx_solr_indexqueue_RecordMonitor';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'EXT:solr/classes/indexqueue/class.tx_solr_indexqueue_recordmonitor.php:tx_solr_indexqueue_RecordMonitor';

		// hooking into TCE Main to monitor record updates that may require deleting documents from the index
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][]  = 'EXT:solr/classes/class.tx_solr_garbagecollector.php:&tx_solr_GarbageCollector';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'EXT:solr/classes/class.tx_solr_garbagecollector.php:&tx_solr_GarbageCollector';

}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// replace the built-in search content element
t3lib_extMgm::addPiFlexFormValue(
	'*',
	'FILE:EXT:' . $_EXTKEY . '/flexforms/pi_results.xml',
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