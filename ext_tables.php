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

t3lib_extMgm::addStaticFile($_EXTKEY, 'static/solr/', 'Apache Solr');

   # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

if (TYPO3_MODE == 'BE') {
	t3lib_extMgm::addModulePath('tools_txsolrMAdmin', t3lib_extMgm::extPath($_EXTKEY) . 'mod_admin/');
	t3lib_extMgm::addModule('tools', 'txsolrMAdmin', '', t3lib_extMgm::extPath($_EXTKEY) . 'mod_admin/');

	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['solr'] = 'tx_solr_report_SolrStatus';
}

?>