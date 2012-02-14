<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$PATH_solr = t3lib_extMgm::extPath('solr');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// Windows compatibility
if(!function_exists('strptime')) {
	require_once($PATH_solr . 'lib/strptime/strptime.php');
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


if (TYPO3_MODE == 'FE') {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['tx_solr_AdditionalFieldsIndexer'] = 'EXT:solr/classes/class.tx_solr_additionalfieldsindexer.php:tx_solr_AdditionalFieldsIndexer';
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering Index Queue page indexer hooks

if (TYPO3_MODE == 'FE' && isset($_SERVER['HTTP_X_TX_SOLR_IQ'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/index_ts.php']['preprocessRequest']['tx_solr_indexqueue_PageIndexerRequestHandler'] = 'EXT:solr/classes/indexqueue/class.tx_solr_indexqueue_pageindexerrequesthandler.php:&tx_solr_indexqueue_PageIndexerRequestHandler->run';

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
	$PATH_solr . 'mod_index/class.tx_solr_mod_index_indexinspectorremotecontroller.php:tx_solr_mod_index_IndexInspectorRemoteController',
	'web_info',
	'user,group'
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
	'search, results',
	'form',
	'tx_solr_pi_results_FormCommand',
	tx_solr_PluginCommand::REQUIREMENT_NONE
);

tx_solr_CommandResolver::registerPluginCommand(
	'search, results',
	'errors',
	'tx_solr_pi_results_ErrorsCommand',
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
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['spellcheck'] = 'EXT:solr/pi_results/class.tx_solr_pi_results_spellcheckformmodifier.php:tx_solr_pi_results_SpellcheckFormModifier';
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['suggest'] = 'EXT:solr/pi_results/class.tx_solr_pi_results_suggestformmodifier.php:tx_solr_pi_results_SuggestFormModifier';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering the eID script for auto suggest
	// TODO move to suggest form modifier
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_suggest'] = 'EXT:solr/eid_suggest/suggest.php';

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
	'EXT:solr/classes/contentobject/class.tx_solr_contentobject_multivalue.php:tx_solr_contentobject_Multivalue'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][tx_solr_contentobject_Content::CONTENT_OBJECT_NAME] = array(
	tx_solr_contentobject_Content::CONTENT_OBJECT_NAME,
	'EXT:solr/classes/contentobject/class.tx_solr_contentobject_content.php:tx_solr_contentobject_Content'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][tx_solr_contentobject_Relation::CONTENT_OBJECT_NAME] = array(
	tx_solr_contentobject_Relation::CONTENT_OBJECT_NAME,
	'EXT:solr/classes/contentobject/class.tx_solr_contentobject_relation.php:tx_solr_contentobject_Relation'
);

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #


?>