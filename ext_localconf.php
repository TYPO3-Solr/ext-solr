<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$GLOBALS['PATH_solr'] = t3lib_extMgm::extPath('solr');

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

$compatMode = FALSE;
if (!t3lib_div::compat_version('6.0')) {
	$compatMode = TRUE;
	require_once($GLOBALS['PATH_solr'] . 'Compat/interface.tx_scheduler_progressprovider.php');
}

define('SOLR_COMPAT', $compatMode);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// Windows compatibility

if(!function_exists('strptime')) {
	require_once($GLOBALS['PATH_solr'] . 'Lib/strptime/strptime.php');
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding the Search plugin
t3lib_extMgm::addPItoST43(
	$_EXTKEY,
	'PiResults/Results.php',
	'_pi_results',
	'list_type',
	FALSE
);

	// adding the Search Form plugin
t3lib_extMgm::addPItoST43(
	$_EXTKEY,
	'PiSearch/Search.php',
	'_pi_search',
	'list_type',
	TRUE
);

	// adding the Frequent Searches plugin
t3lib_extMgm::addPItoST43(
	$_EXTKEY,
	'PiFrequentSearches/FrequentSearches.php',
	'_pi_frequentsearches',
	'list_type',
	TRUE
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering Index Queue page indexer helpers

if (TYPO3_MODE == 'FE' && isset($_SERVER['HTTP_X_TX_SOLR_IQ'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/index_ts.php']['preprocessRequest']['Tx_Solr_IndexQueue_PageIndexerRequestHandler'] = '&Tx_Solr_IndexQueue_PageIndexerRequestHandler->run';
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['Tx_Solr_AdditionalFieldsIndexer'] = 'Tx_Solr_AdditionalFieldsIndexer';

	Tx_Solr_IndexQueue_FrontendHelper_Manager::registerFrontendHelper(
		'findUserGroups',
		'Tx_Solr_IndexQueue_FrontendHelper_UserGroupDetector'
	);

	Tx_Solr_IndexQueue_FrontendHelper_Manager::registerFrontendHelper(
		'indexPage',
		'Tx_Solr_IndexQueue_FrontendHelper_PageIndexer'
	);
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

t3lib_extMgm::registerExtDirectComponent(
	'TYPO3.tx_solr.IndexInspector.Remote',
	$GLOBALS['PATH_solr'] . 'ModIndex/IndexInspectorRemoteController.php:Tx_Solr_ModIndex_IndexInspectorRemoteController',
	'web_info',
	'user,group'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// page module plugin settings summary

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info'][$_EXTKEY . '_PiResults_Results'][$_EXTKEY] = 'Tx_Solr_PluginBase_BackendSummary->getSummary';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// register search components

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'access',
	'Tx_Solr_Search_AccessComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'relevance',
	'Tx_Solr_Search_RelevanceComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'sorting',
	'Tx_Solr_Search_SortingComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'debug',
	'Tx_Solr_Search_DebugComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'analysis',
	'Tx_Solr_Search_AnalysisComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'highlighting',
	'Tx_Solr_Search_HighlightingComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'spellchecking',
	'Tx_Solr_Search_SpellcheckingComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'faceting',
	'Tx_Solr_Search_FacetingComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'statistics',
	'Tx_Solr_Search_StatisticsComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'lastSearches',
	'Tx_Solr_Search_LastSearchesComponent'
);

Tx_Solr_Search_SearchComponentManager::registerSearchComponent(
	'elevation',
	'Tx_Solr_Search_ElevationComponent'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// register plugin commands

#Tx_Solr_CommandResolver::registerPluginCommand(
#	'results',
#	'advanced_form',
#	'Tx_Solr_PiResults_AdvancedFormCommand',
#	Tx_Solr_PluginCommand::REQUIREMENT_NONE
#);

Tx_Solr_CommandResolver::registerPluginCommand(
	'results, frequentsearches',
	'frequentSearches',
	'Tx_Solr_PiResults_FrequentSearchesCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_NONE
);

Tx_Solr_CommandResolver::registerPluginCommand(
	'search, results',
	'form',
	'Tx_Solr_PiResults_FormCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_NONE
);

Tx_Solr_CommandResolver::registerPluginCommand(
	'results',
	'resultsPerPageSwitch',
	'Tx_Solr_PiResults_ResultsPerPageSwitchCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ Tx_Solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

Tx_Solr_CommandResolver::registerPluginCommand(
	'search, results',
	'errors',
	'Tx_Solr_PiResults_ErrorsCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_NONE
);

Tx_Solr_CommandResolver::registerPluginCommand(
	'results',
	'lastSearches',
	'Tx_Solr_PiResults_LastSearchesCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_NONE
);

Tx_Solr_CommandResolver::registerPluginCommand(
	'results',
	'no_results',
	'Tx_Solr_PiResults_NoResultsCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ Tx_Solr_PluginCommand::REQUIREMENT_NO_RESULTS
);

Tx_Solr_CommandResolver::registerPluginCommand(
	'results',
	'faceting',
	'Tx_Solr_PiResults_FacetingCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ Tx_Solr_PluginCommand::REQUIREMENT_HAS_RESULTS
	+ Tx_Solr_PluginCommand::REQUIREMENT_NO_RESULTS
);

Tx_Solr_CommandResolver::registerPluginCommand(
	'results',
	'results',
	'Tx_Solr_PiResults_ResultsCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ Tx_Solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

Tx_Solr_CommandResolver::registerPluginCommand(
	'results',
	'sorting',
	'Tx_Solr_PiResults_SortingCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ Tx_Solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering facet types

Tx_Solr_Facet_FacetRendererFactory::registerFacetType(
	'numericRange',
	'Tx_Solr_Facet_NumericRangeFacetRenderer',
	'Tx_Solr_Query_FilterEncoder_Range',
	'Tx_Solr_Query_FilterEncoder_Range'
);

Tx_Solr_Facet_FacetRendererFactory::registerFacetType(
	'dateRange',
	'Tx_Solr_Facet_DateRangeFacetRenderer',
	'Tx_Solr_Query_FilterEncoder_DateRange',
	'Tx_Solr_Query_FilterEncoder_DateRange'
);

Tx_Solr_Facet_FacetRendererFactory::registerFacetType(
	'hierarchy',
	'Tx_Solr_Facet_HierarchicalFacetRenderer',
	'Tx_Solr_Query_FilterEncoder_Hierarchy'
);

Tx_Solr_Facet_FacetRendererFactory::registerFacetType(
	'queryGroup',
	'Tx_Solr_Facet_QueryGroupFacetRenderer',
	'Tx_Solr_Query_FilterEncoder_QueryGroup',
	'Tx_Solr_Query_FilterEncoder_QueryGroup'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding scheduler tasks

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Tx_Solr_Scheduler_ReIndexTask'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'LLL:EXT:solr/Resources/Private/Language/ModuleScheduler.xml:reindex_title',
	'description'      => 'LLL:EXT:solr/Resources/Private/Language/ModuleScheduler.xml:reindex_description',
	'additionalFields' => 'Tx_Solr_Scheduler_ReIndexTaskAdditionalFieldProvider'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Tx_Solr_Scheduler_IndexQueueWorkerTask'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'LLL:EXT:solr/Resources/Private/Language/ModuleScheduler.xml:indexqueueworker_title',
	'description'      => 'LLL:EXT:solr/Resources/Private/Language/ModuleScheduler.xml:indexqueueworker_description',
	'additionalFields' => 'Tx_Solr_Scheduler_IndexQueueWorkerTaskAdditionalFieldProvider'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// TODO move into pi_results, initializeSearch, add only when features are activated
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['keepParameters'] = 'Tx_Solr_PiResults_ParameterKeepingFormModifier';
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['spellcheck']     = 'Tx_Solr_PiResults_SpellCheckFormModifier';
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['suggest']        = 'Tx_Solr_PiResults_SuggestFormModifier';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering the eID scripts
	// TODO move to suggest form modifier
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_suggest'] = 'EXT:solr/EidSuggest/Suggest.php';
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_api']     = 'EXT:solr/EidApi/Dispatch.php';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// replace the built-in search content element

$searchReplacementTypoScript = trim('
tt_content.search = COA
tt_content.search {
	10 = < lib.stdheader
	20 >
	20 = < plugin.tx_solr_pi_results
	30 >
}
');

t3lib_extMgm::addTypoScript(
	$_EXTKEY,
	'setup',
	'# Setting ' . $_EXTKEY . ' plugin TypoScript' . $searchReplacementTypoScript,
	43
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// add custom Solr content objects

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][Tx_Solr_ContentObject_Multivalue::CONTENT_OBJECT_NAME] = array(
	Tx_Solr_ContentObject_Multivalue::CONTENT_OBJECT_NAME,
	'Tx_Solr_ContentObject_Multivalue'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][Tx_Solr_ContentObject_Content::CONTENT_OBJECT_NAME] = array(
	Tx_Solr_ContentObject_Content::CONTENT_OBJECT_NAME,
	'Tx_Solr_ContentObject_Content'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][Tx_Solr_ContentObject_Relation::CONTENT_OBJECT_NAME] = array(
	Tx_Solr_ContentObject_Relation::CONTENT_OBJECT_NAME,
	'Tx_Solr_ContentObject_Relation'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// Register cache for frequent searches, this is enough for TYPO3 4.6+

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'] = array();
}

if (version_compare(TYPO3_version, '4.6', '<')) {

		// use variable frontend as caching frontend
	if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['frontend'])) {
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['frontend'] = 't3lib_cache_frontend_VariableFrontend';
	}

		// use database backend as caching backend
	if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['backend'])) {
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['backend'] = 't3lib_cache_backend_DbBackend';
	}

		// data and tags table
	if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['options'])) {
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['options'] = array();
	}
	if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['options']['cacheTable'])) {
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['options']['cacheTable'] = 'tx_solr_cache';
	}
	if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['options']['tagsTable'])) {
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['options']['tagsTable'] = 'tx_solr_cache_tags';
	}
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

if (TYPO3_MODE == 'BE') {
	$TYPO3_CONF_VARS['SC_OPTIONS']['GLOBAL']['^cliKeys'][$_EXTKEY] = array(
		'EXT:' . $_EXTKEY . '/CliApi/Dispatch.php',
		'_CLI_solr'
	);
}

?>
