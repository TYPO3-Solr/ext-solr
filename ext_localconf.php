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
	false
);

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// adding the indexer to the same hook that EXT:indexed_search would use
$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['pageIndexing']['tx_solr_Indexer'] = 'EXT:solr/classes/class.tx_solr_indexer.php:tx_solr_Indexer';
	// tracking FE user groups used to protect content on a page
$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['postInit']['tx_solr_Indexer'] = 'EXT:solr/classes/class.tx_solr_indexer.php:&tx_solr_Indexer';

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

	// TODO move into pi_results, initializeSearch, add only when highlighting is activated
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['spellcheck'] = 'EXT:solr/pi_results/class.tx_solr_pi_results_spellcheckformmodifier.php:tx_solr_pi_results_SpellcheckFormModifier';

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

	// registering the eID script for auto suggest
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_suggest'] = 'EXT:solr/eid_suggest/suggest.php';

?>