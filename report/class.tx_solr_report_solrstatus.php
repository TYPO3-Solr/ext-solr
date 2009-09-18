<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo@typo3.org>
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
 * Provides an status report about whether a connection to the Solr server can
 * be established.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage tx_solr
 */
class tx_solr_report_SolrStatus implements tx_reports_StatusProvider {


	/**
	 * Compiles a collection of status checks against each configured Solr server.
	 *
	 * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
	 */
	public function getStatus() {
		$reports = array();
		$solrServers = $this->getConfiguredSolrServers();

		foreach ($solrServers as $solrServer) {
			$reports[] = $this->getConnectionStatus($solrServer);
		}

		return $reports;
	}

	/**
	 * Finds all configured Solr servers in the BE
	 *
	 * @return	array	An array of configured Solr server connections
	 */
	protected function getConfiguredSolrServers() {
		$solrServers = array();

			// find website roots
		$rootPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, title',
			'pages',
			'is_siteroot = 1 AND deleted = 0'
		);

		$pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');

			// find solr configurations and them as function menu entries
		foreach ($rootPages as $rootPage) {
			$rootLine = $pageSelect->getRootLine($rootPage['uid']);

			$tmpl = t3lib_div::makeInstance('t3lib_tsparser_ext');
			$tmpl->tt_track = false; // Do not log time-performance information
			$tmpl->init();
			$tmpl->runThroughTemplates($rootLine); // This generates the constants/config + hierarchy info for the template.
			$tmpl->generateConfig();

			list($solrSetup) = $tmpl->ext_getSetup($tmpl->setup, 'plugin.tx_solr.solr');

			if (!empty($solrSetup)) {
				$solrServers[] = $solrSetup;
			}
		}

		return $solrServers;
	}

	protected function getConnectionStatus(array $solrServer) {
		$value    = 'Your site was unable to contact the Apache Solr server.';
		$severity = tx_reports_reports_status_Status::ERROR;

		$message  = '<ul>'
			. '<li>Host: ' . $solrServer['host'] . '</li>'
			. '<li>Port: ' . $solrServer['port'] . '</li>'
			. '<li>Path: ' . $solrServer['path'] . '</li>'
			. '</ul>';

		if (!isset($GLOBALS['TSFE'])) {
			$GLOBALS['TSFE'] = new stdClass();
			$GLOBALS['TSFE']->tmpl = new stdClass();
		}

		$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.'] = array (
			'host' => $solrServer['host'],
			'port' => $solrServer['port'],
			'path' => $solrServer['path']
		);

		$search        = t3lib_div::makeInstance('tx_solr_Search');

		if ($search->ping()) {
			$severity = tx_reports_reports_status_Status::OK;
			$value = 'Your site has contacted the Apache Solr server.';
		}

		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			'Apache Solr',
			$value,
			$message,
			$severity
		);
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_solrstatus.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_solrstatus.php']);
}

?>