<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

$PATH_solr = t3lib_extMgm::extPath('solr');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

switch (TYPO3_branch) {
	case '4.3':
			// the FE indexer is asking the Index Queue FE helper wether it's active, the helper is using this interface
		require_once($PATH_solr . 'compat/interface.t3lib_pageselect_getpageoverlayhook.php');
		require_once($PATH_solr . 'compat/class.ux_t3lib_page.php');
		require_once($PATH_solr . 'compat/interface.tslib_content_postinithook.php');
		if (TYPO3_MODE == 'FE') {
			require_once($PATH_solr . 'compat/class.ux_tslib_cobj.php');
		}
		break;
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
		// select and register the page indexer
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/index_ts.php']['preprocessRequest']['tx_solr_IndexerSelector'] = 'EXT:solr/classes/class.tx_solr_indexerselector.php:tx_solr_IndexerSelector->registerIndexer';

	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['tx_solr_AdditionalFieldsIndexer'] = 'EXT:solr/classes/class.tx_solr_additionalfieldsindexer.php:tx_solr_AdditionalFieldsIndexer';
}

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering Index Queue page indexer hooks

if (TYPO3_MODE == 'FE' && isset($_SERVER['HTTP_X_TX_SOLR_IQ'])) {
		// TODO move into IndexerSelector if possible - depends on order of execution of hooks
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

	// register plugin commands

#tx_solr_CommandResolver::registerPluginCommand(
#	'results',
#	'advanced_form',
#	'tx_solr_pi_results_AdvancedFormCommand',
#	tx_solr_PluginCommand::REQUIREMENT_NONE
#);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'faceting',
	'tx_solr_pi_results_FacetingCommand',
	tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED + tx_solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

tx_solr_CommandResolver::registerPluginCommand(
	'search, results',
	'form',
	'tx_solr_pi_results_FormCommand',
	tx_solr_PluginCommand::REQUIREMENT_NONE
);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'no_results',
	'tx_solr_pi_results_NoResultsCommand',
	tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED + tx_solr_PluginCommand::REQUIREMENT_NO_RESULTS
);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'results',
	'tx_solr_pi_results_ResultsCommand',
	tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED + tx_solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

tx_solr_CommandResolver::registerPluginCommand(
	'search, results',
	'errors',
	'tx_solr_pi_results_ErrorsCommand',
	tx_solr_PluginCommand::REQUIREMENT_NONE
);

tx_solr_CommandResolver::registerPluginCommand(
	'results',
	'sorting',
	'tx_solr_pi_results_SortingCommand',
	tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED + tx_solr_PluginCommand::REQUIREMENT_HAS_RESULTS
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering with the "crawler" extension:
$TYPO3_CONF_VARS['EXTCONF']['crawler']['procInstructions']['tx_solr_reindex'] = 'Solr Re-indexing';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding scheduler tasks
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_solr_scheduler_OptimizeTask'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'LLL:EXT:solr/lang/locallang.xml:scheduler_optimizer_title',
	'description'      => 'LLL:EXT:solr/lang/locallang.xml:scheduler_optimizer_description',
		// TODO needs to be provided with arguments of which solr server to optimize
		// might be a nice usability feature to have the same select as in the Solr BE admin module
	'additionalFields' => 'tx_solr_scheduler_OptimizeTaskSolrServerField'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_solr_scheduler_CommitTask'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'LLL:EXT:solr/lang/locallang.xml:scheduler_commit_title',
	'description'      => 'LLL:EXT:solr/lang/locallang.xml:scheduler_commit_description',
		// TODO needs to be provided with arguments of which solr server to commit to
		// might be a nice usability feature to have the same select as in the Solr BE admin module
	'additionalFields' => 'tx_solr_scheduler_CommitTaskSolrServerField'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_solr_scheduler_IndexQueueWorkerTask'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'LLL:EXT:solr/lang/locallang.xml:scheduler_indexqueueworker_title',
	'description'      => 'LLL:EXT:solr/lang/locallang.xml:scheduler_indexqueueworker_description',
		// TODO needs to be provided with arguments of which solr server to index to
		// might be a nice usability feature to have the same select as in the Solr BE admin module
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

?>