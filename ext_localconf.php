<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$GLOBALS['PATH_solr'] = t3lib_extMgm::extPath('solr');

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

$compatMode = FALSE;
if (!t3lib_div::compat_version('6.0')) {
	$compatMode = TRUE;
	require_once($GLOBALS['PATH_solr'] . 'compat/interface.tx_scheduler_progressprovider.php');
}

define('SOLR_COMPAT', $compatMode);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// Windows compatibility

if(!function_exists('strptime')) {
	require_once($GLOBALS['PATH_solr'] . 'lib/strptime/strptime.php');
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding the Search plugin
t3lib_extMgm::addPItoST43(
	$_EXTKEY,
	'pi_results/class.tx_solr_pi_results.php',
	'_pi_results',
	'list_type',
	FALSE
);

	// adding the Search Form plugin
t3lib_extMgm::addPItoST43(
	$_EXTKEY,
	'pi_search/class.tx_solr_pi_search.php',
	'_pi_search',
	'list_type',
	TRUE
);

	// adding the Frequent Searches plugin
t3lib_extMgm::addPItoST43(
	$_EXTKEY,
	'pi_frequentsearches/class.tx_solr_pi_frequentsearches.php',
	'_pi_frequentsearches',
	'list_type',
	TRUE
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering Index Queue page indexer helpers

if (TYPO3_MODE == 'FE' && isset($_SERVER['HTTP_X_TX_SOLR_IQ'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/index_ts.php']['preprocessRequest']['tx_solr_indexqueue_PageIndexerRequestHandler'] = '&tx_solr_indexqueue_PageIndexerRequestHandler->run';
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['tx_solr_AdditionalFieldsIndexer'] = 'tx_solr_AdditionalFieldsIndexer';

	tx_solr_indexqueue_frontendhelper_Manager::registerFrontendHelper(
		'findUserGroups',
		'tx_solr_indexqueue_frontendhelper_UserGroupDetector'
	);

	tx_solr_indexqueue_frontendhelper_Manager::registerFrontendHelper(
		'indexPage',
		'tx_solr_indexqueue_frontendhelper_PageIndexer'
	);
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

t3lib_extMgm::registerExtDirectComponent(
	'TYPO3.tx_solr.IndexInspector.Remote',
	$GLOBALS['PATH_solr'] . 'mod_index/class.tx_solr_mod_index_indexinspectorremotecontroller.php:tx_solr_mod_index_IndexInspectorRemoteController',
	'web_info',
	'user,group'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// page module plugin settings summary

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info'][$_EXTKEY . '_pi_results'][$_EXTKEY] = 'tx_solr_pluginbase_BackendSummary->getSummary';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// register search components

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'access',
	'tx_solr_search_AccessComponent'
);

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'relevance',
	'tx_solr_search_RelevanceComponent'
);

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'sorting',
	'tx_solr_search_SortingComponent'
);

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'debug',
	'tx_solr_search_DebugComponent'
);

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'analysis',
	'tx_solr_search_AnalysisComponent'
);

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'highlighting',
	'tx_solr_search_HighlightingComponent'
);

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'spellchecking',
	'tx_solr_search_SpellcheckingComponent'
);

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'faceting',
	'tx_solr_search_FacetingComponent'
);

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'statistics',
	'tx_solr_search_StatisticsComponent'
);

tx_solr_search_SearchComponentManager::registerSearchComponent(
	'lastSearches',
	'tx_solr_search_LastSearchesComponent'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// register plugin commands

#tx_solr_CommandResolver::registerPluginCommand(
#	'results',
#	'advanced_form',
#	'tx_solr_pi_results_AdvancedFormCommand',
#	tx_solr_PluginCommand::REQUIREMENT_NONE
#);

tx_solr_CommandResolver::registerPluginCommand(
	'results, frequentsearches',
	'frequentSearches',
	'tx_solr_pi_results_FrequentSearchesCommand',
	tx_solr_PluginCommand::REQUIREMENT_NONE
);

tx_solr_CommandResolver::registerPluginCommand(
	'search, results',
	'form',
	'tx_solr_pi_results_FormCommand',
	tx_solr_PluginCommand::REQUIREMENT_NONE
);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'resultsPerPageSwitch',
	'tx_solr_pi_results_ResultsPerPageSwitchCommand',
	tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ tx_solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

tx_solr_CommandResolver::registerPluginCommand(
	'search, results',
	'errors',
	'tx_solr_pi_results_ErrorsCommand',
	tx_solr_PluginCommand::REQUIREMENT_NONE
);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'lastSearches',
	'tx_solr_pi_results_LastSearchesCommand',
	tx_solr_PluginCommand::REQUIREMENT_NONE
);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'no_results',
	'tx_solr_pi_results_NoResultsCommand',
	tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ tx_solr_PluginCommand::REQUIREMENT_NO_RESULTS
);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'faceting',
	'tx_solr_pi_results_FacetingCommand',
	tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ tx_solr_PluginCommand::REQUIREMENT_HAS_RESULTS
	+ tx_solr_PluginCommand::REQUIREMENT_NO_RESULTS
);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'results',
	'tx_solr_pi_results_ResultsCommand',
	tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ tx_solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'sorting',
	'tx_solr_pi_results_SortingCommand',
	tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ tx_solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering facet types

tx_solr_facet_FacetRendererFactory::registerFacetType(
	'numericRange',
	'tx_solr_facet_NumericRangeFacetRenderer',
	'tx_solr_query_filterencoder_Range',
	'tx_solr_query_filterencoder_Range'
);

tx_solr_facet_FacetRendererFactory::registerFacetType(
	'dateRange',
	'tx_solr_facet_DateRangeFacetRenderer',
	'tx_solr_query_filterencoder_DateRange',
	'tx_solr_query_filterencoder_DateRange'
);

tx_solr_facet_FacetRendererFactory::registerFacetType(
	'hierarchy',
	'tx_solr_facet_HierarchicalFacetRenderer',
	'tx_solr_query_filterencoder_Hierarchy'
);

tx_solr_facet_FacetRendererFactory::registerFacetType(
	'queryGroup',
	'tx_solr_facet_QueryGroupFacetRenderer',
	'tx_solr_query_filterencoder_QueryGroup',
	'tx_solr_query_filterencoder_QueryGroup'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding scheduler tasks

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_solr_scheduler_OptimizeTask'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'LLL:EXT:solr/lang/locallang.xml:scheduler_optimizer_title',
	'description'      => 'LLL:EXT:solr/lang/locallang.xml:scheduler_optimizer_description',
	'additionalFields' => 'tx_solr_scheduler_OptimizeTaskSolrServerField'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_solr_scheduler_CommitTask'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'LLL:EXT:solr/lang/locallang.xml:scheduler_commit_title',
	'description'      => 'LLL:EXT:solr/lang/locallang.xml:scheduler_commit_description',
	'additionalFields' => 'tx_solr_scheduler_CommitTaskSolrServerField'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_solr_scheduler_ReIndexTask'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'LLL:EXT:solr/lang/locallang.xml:scheduler_reindex_title',
	'description'      => 'LLL:EXT:solr/lang/locallang.xml:scheduler_reindex_description',
	'additionalFields' => 'tx_solr_scheduler_ReIndexTaskSolrServerField'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_solr_scheduler_IndexQueueWorkerTask'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'LLL:EXT:solr/lang/locallang.xml:scheduler_indexqueueworker_title',
	'description'      => 'LLL:EXT:solr/lang/locallang.xml:scheduler_indexqueueworker_description',
	'additionalFields' => 'tx_solr_scheduler_IndexQueueWorkerTaskAdditionalFieldProvider'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// TODO move into pi_results, initializeSearch, add only when features are activated
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['keepParameters'] = 'tx_solr_pi_results_ParameterKeepingFormModifier';
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['spellcheck']     = 'tx_solr_pi_results_SpellcheckFormModifier';
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['suggest']        = 'tx_solr_pi_results_SuggestFormModifier';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering the eID scripts
	// TODO move to suggest form modifier
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_suggest'] = 'EXT:solr/eid_suggest/suggest.php';
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_api']     = 'EXT:solr/eid_api/dispatch.php';

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

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][tx_solr_contentobject_Multivalue::CONTENT_OBJECT_NAME] = array(
	tx_solr_contentobject_Multivalue::CONTENT_OBJECT_NAME,
	'tx_solr_contentobject_Multivalue'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][tx_solr_contentobject_Content::CONTENT_OBJECT_NAME] = array(
	tx_solr_contentobject_Content::CONTENT_OBJECT_NAME,
	'tx_solr_contentobject_Content'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][tx_solr_contentobject_Relation::CONTENT_OBJECT_NAME] = array(
	tx_solr_contentobject_Relation::CONTENT_OBJECT_NAME,
	'tx_solr_contentobject_Relation'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// Register cache for frequent searches, this is enough for TYPO3 4.6+

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'] = array();
}

if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < 4006000) {

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
	$TYPO3_CONF_VARS['SC_OPTIONS']['GLOBAL']['cliKeys'][$_EXTKEY] = array(
		'EXT:' . $_EXTKEY . '/cli_api/dispatch.php',
		'_CLI_solr'
	);
}

?>
