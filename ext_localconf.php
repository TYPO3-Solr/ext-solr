<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$GLOBALS['PATH_solr'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// Windows compatibility

if(!function_exists('strptime')) {
	require_once($GLOBALS['PATH_solr'] . 'Lib/strptime/strptime.php');
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #


   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering Index Queue page indexer helpers

if (TYPO3_MODE == 'FE' && isset($_SERVER['HTTP_X_TX_SOLR_IQ'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/index_ts.php']['preprocessRequest']['Tx_Solr_IndexQueue_PageIndexerRequestHandler'] = '&Tx_Solr_IndexQueue_PageIndexerRequestHandler->run';
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer'] = 'ApacheSolrForTypo3\\Solr\\AdditionalFieldsIndexer';

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

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerExtDirectComponent(
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

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'access',
	'ApacheSolrForTypo3\\Solr\\Search\\AccessComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'relevance',
	'ApacheSolrForTypo3\\Solr\\Search\\RelevanceComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'sorting',
	'ApacheSolrForTypo3\\Solr\\Search\\SortingComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'debug',
	'ApacheSolrForTypo3\\Solr\\Search\\DebugComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'analysis',
	'ApacheSolrForTypo3\\Solr\\Search\\AnalysisComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'highlighting',
	'ApacheSolrForTypo3\\Solr\\Search\\HighlightingComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'spellchecking',
	'ApacheSolrForTypo3\\Solr\\Search\\SpellcheckingComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'faceting',
	'ApacheSolrForTypo3\\Solr\\Search\\FacetingComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'statistics',
	'ApacheSolrForTypo3\\Solr\\Search\\StatisticsComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'lastSearches',
	'ApacheSolrForTypo3\\Solr\\Search\\LastSearchesComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
	'elevation',
	'ApacheSolrForTypo3\\Solr\\Search\\ElevationComponent'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// register plugin commands

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
	'results, frequentsearches',
	'frequentSearches',
	'Tx_Solr_PiResults_FrequentSearchesCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_NONE
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
	'search, results',
	'form',
	'Tx_Solr_PiResults_FormCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_NONE
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
	'results',
	'resultsPerPageSwitch',
	'Tx_Solr_PiResults_ResultsPerPageSwitchCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ Tx_Solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
	'search, results',
	'errors',
	'Tx_Solr_PiResults_ErrorsCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_NONE
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
	'results',
	'lastSearches',
	'Tx_Solr_PiResults_LastSearchesCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_NONE
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
	'results',
	'no_results',
	'Tx_Solr_PiResults_NoResultsCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ Tx_Solr_PluginCommand::REQUIREMENT_NO_RESULTS
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
	'results',
	'faceting',
	'Tx_Solr_PiResults_FacetingCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ Tx_Solr_PluginCommand::REQUIREMENT_HAS_RESULTS
	+ Tx_Solr_PluginCommand::REQUIREMENT_NO_RESULTS
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
	'results',
	'results',
	'Tx_Solr_PiResults_ResultsCommand',
	Tx_Solr_PluginCommand::REQUIREMENT_HAS_SEARCHED
	+ Tx_Solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
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
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_suggest'] = 'EXT:solr/Classes/Eid/Suggest.php';
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_api']     = 'EXT:solr/Classes/Eid/Api.php';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// replace the built-in search content element

$searchReplacementTypoScript = trim('
tt_content.search = COA
tt_content.search {
	10 = < lib.stdheader
	20 >
	20 = < plugin.tx_solr_PiResults_Results
	30 >
}
');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
	$_EXTKEY,
	'setup',
	'# Setting ' . $_EXTKEY . ' plugin TypoScript' . $searchReplacementTypoScript,
	43
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// add custom Solr content objects

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][ApacheSolrForTypo3\Solr\ContentObject\Multivalue::CONTENT_OBJECT_NAME] = array(
	ApacheSolrForTypo3\Solr\ContentObject\Multivalue::CONTENT_OBJECT_NAME,
	'ApacheSolrForTypo3\Solr\ContentObject\Multivalue'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][ApacheSolrForTypo3\Solr\ContentObject\Content::CONTENT_OBJECT_NAME] = array(
	ApacheSolrForTypo3\Solr\ContentObject\Content::CONTENT_OBJECT_NAME,
	'ApacheSolrForTypo3\Solr\ContentObject\Content'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][ApacheSolrForTypo3\Solr\ContentObject\Relation::CONTENT_OBJECT_NAME] = array(
	ApacheSolrForTypo3\Solr\ContentObject\Relation::CONTENT_OBJECT_NAME,
	'ApacheSolrForTypo3\Solr\ContentObject\Relation'
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// Register cache for frequent searches

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'] = array();
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

if (TYPO3_MODE == 'BE') {
	$TYPO3_CONF_VARS['SC_OPTIONS']['GLOBAL']['cliKeys'][$_EXTKEY] = array(
		'EXT:' . $_EXTKEY . '/Classes/Cli/Api.php',
		'_CLI_solr'
	);
}

