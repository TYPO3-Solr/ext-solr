<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo@typo3.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * A report to get an overview of the Apache Solr Index
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_report_IndexReport implements tx_reports_Report {

	protected $reportsModule;

	/**
	 * Solr server connection
	 *
	 * @var	tx_solr_SolrService
	 */
	protected $solr;

	/**
	 * constructor for class tx_solr_IndexReport
	 */
	public function __construct(tx_reports_Module $reportsModule) {
		$this->reportsModule = $reportsModule;

		$this->reportsModule->doc->addStyleSheet(
			'tx_solr',
			'../' . t3lib_extMgm::siteRelPath('solr') . 'resources/css/report/index.css'
		);
	}

	public function getReport() {
		$content = '';

		$solrConnections = $this->getConfiguredSolrConnections();
		if (count($solrConnections) > 1) {
			$connectionMenu = $this->getSolrConnectionMenu($solrConnections);
			$this->injectSolrConnectionMenuIntoReportsModule($connectionMenu);
		}

		try {
			$this->solr = $this->getSelectedSolrConnection($solrConnections);
			$data = $this->solr->getLukeMetaData();

			$content = $this->renderData($data);
		} catch (Exception $e) {
			$message = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				'Failed to establish Solr connection.',
				'',
				t3lib_FlashMessage::ERROR,
				TRUE
			);

			$content = $message->render();
		}

		return $content;
	}

	/**
	 * Renders the index field table.
	 *
	 * Acknowledgement: Some of the code is taken from Drupal's apachesolr module
	 *
	 * @param $data
	 * @return unknown_type
	 */
	protected function renderData(Apache_Solr_Response $data) {
		$content  = '';
		$registry = t3lib_div::makeInstance('t3lib_Registry');
		$limit    = $registry->get('tx_solr', 'luke.limit', 20000);

		$numberOfDocuments = $data->index->numDocs;
		$content .= '<p>Number of documents in index: ' . $numberOfDocuments . '</p>';

		if (isset($data->index->numDocs) && $data->index->numDocs > $limit) {
			$notFound = '<em>Omitted</em>';
			$content .= '<p>You have more than ' . $limit . ' documents, so term frequencies are being omitted for performance reasons.</p>';
		} elseif (isset($data->index->numDocs)) {
			$notFound = 'Nothing indexed';
				// below limit, so we get more data
				// Note: we use 2 since 1 fails on Ubuntu Hardy.
			$data = $this->solr->getLukeMetaData(2);
			$content .= '<p>Number of terms in index: ' . $data->index->numTerms . '</p>';
		}

		$fields = (array) $data->fields;
		$content .= '<p>Number of fields in index: ' . count($fields) . '</p>';


			// getting fields, sorting
		$rows = array();
		foreach ($fields as $name => $field) {
			$rows[$name] = array(
				$name,
				$field->type,
				isset($field->distinct) ? $field->distinct : $notFound);
		}
		ksort($rows);

			// Initialise table layout
		$tableLayout = array(
			'table' => array(
				'<table border="0" cellspacing="0" cellpadding="2" class="tx_solr_index_list">', '</table>'
			),
			'0'     => array(
				'tr'     => array('<tr class="bgColor2" valign="top">', '</tr>'),
				'defCol' => array('<td>', '</td>')
			),
			'defRowOdd' => array(
				'tr'     => array('<tr class="bgColor3-20">', '</tr>'),
				'defCol' => array('<td>', '</td>')
			),
			'defRowEven' => array(
				'tr'     => array('<tr>', '</tr>'),
				'defCol' => array('<td>', '</td>')
			)
		);

		$table = array();

			// header row
		$table[] = array('Field Name', 'Index Type', 'Distinct Terms');
		foreach ($rows as $row) {
			$table[] = $row;
		}

			// Render table
		$content .= $this->reportsModule->doc->table($table, $tableLayout);

		return $content;
	}

	protected function getSolrConnectionMenu(array $solrConnections) {
		$connectionMenuItems = array();

		foreach ($solrConnections as $key => $solrConnection) {
			$connectionMenuItems[$key] = $solrConnection['label'];
		}

		$this->reportsModule->MOD_MENU = array_merge(
			$this->reportsModule->MOD_MENU,
			array('tx_solr_connection' => $connectionMenuItems)
		);

			// updating module settings after modifying MOD_MENU
		$this->reportsModule->MOD_SETTINGS = t3lib_BEfunc::getModuleData(
			$this->reportsModule->MOD_MENU,
			t3lib_div::_GP('SET'),
			$this->reportsModule->MCONF['name'],
			$this->reportsModule->modMenu_type,
			$this->reportsModule->modMenu_dontValidateList,
			$this->reportsModule->modMenu_setDefaultList
		);

		$connectionMenu = 'Solr Server: ' . t3lib_BEfunc::getFuncMenu(
			0,
			'SET[tx_solr_connection]',
			$this->reportsModule->MOD_SETTINGS['tx_solr_connection'],
			$this->reportsModule->MOD_MENU['tx_solr_connection']
		);

		$connectionMenu = '<div id="tx-solr-connection">' . $connectionMenu . '</div>';

		return $connectionMenu;
	}

	protected function getSelectedSolrConnection(array $solrConnections) {
		reset($solrConnections);
		$solrServer = current($solrConnections);

		if (count($solrConnections) > 1) {
			foreach ($solrConnections as $key => $solrConnection) {
				if ($key == $this->reportsModule->MOD_SETTINGS['tx_solr_connection']) {
					$solrServer = $solrConnection;
					break;
				}
			}
		}

		$solr = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getConnection(
			$solrServer['solrHost'],
			$solrServer['solrPort'],
			$solrServer['solrPath'],
			$solrServer['solrScheme']
		);

		return $solr;
	}

	protected function injectSolrConnectionMenuIntoReportsModule($connectionMenu) {
		$this->reportsModule->doc->moduleTemplate = str_replace(
			'###FUNC_MENU###</div>',
			'###FUNC_MENU###</div>' . $connectionMenu,
			$this->reportsModule->doc->moduleTemplate
		);
	}

	protected function getConfiguredSolrConnections() {
		$registry = t3lib_div::makeInstance('t3lib_Registry');
		$configuredSolrConnections = $registry->get('tx_solr', 'servers', array());

		return $configuredSolrConnections;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_indexreport.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_indexreport.php']);
}

?>