<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2010 Ingo Renner <ingo@typo3.org>
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
		$solrConnections = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getAllConnections();

		foreach ($solrConnections as $solrConnection) {
			$reports[] = $this->getConnectionStatus($solrConnection);
		}

		return $reports;
	}

	/**
	 * Checks whether a Solr server is available and provides some information.
	 *
	 * @param	tx_solr_SolrService	Solr connection
	 * @return	tx_reports_reports_status_Status Status of the Solr connection
	 */
	protected function getConnectionStatus(tx_solr_SolrService $solr) {
		$value    = 'Your site was unable to contact the Apache Solr server.';
		$severity = tx_reports_reports_status_Status::ERROR;

		$message  = '<ul>'
			. '<li>Host: ' . $solr->getHost() . '</li>'
			. '<li>Port: ' . $solr->getPort() . '</li>'
			. '<li style="padding-bottom: 10px;">Path: ' . $solr->getPath() . '</li>';

		if ($solr->ping()) {
			$severity = tx_reports_reports_status_Status::OK;
			$value = 'Your site has contacted the Apache Solr server.';

			$completeSolrVersion = $solr->getSolrServerVersion();

			$explodedSolrVersion = explode('.', $completeSolrVersion);
			$shortSolrVersion = $explodedSolrVersion[0]
				. '.' . $explodedSolrVersion[1]
				. '.' . $explodedSolrVersion[2];

			$message .= '<li>Solr: ' . $shortSolrVersion . ' (' . $completeSolrVersion . ')</li>';
			$message .= '<li>Schema: ' . $solr->getSchemaName() . '</li>';
		}

		$message .= '</ul>';

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