<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

$PATH_solr = t3lib_extMgm::extPath('solr');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

switch (TYPO3_branch) {
	case '4.3':
			// adding a hook that was added in TYPO3 4.4
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