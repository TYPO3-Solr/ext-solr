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
 * Provides an status report about whether a connection to the Solr server can
 * be established.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_report_SolrStatus implements tx_reports_StatusProvider {

	/**
	 * Connection Manager
	 *
	 * @var tx_solr_ConnectionManager
	 */
	protected $connectionManager = NULL;

	/**
	 * Compiles a collection of status checks against each configured Solr server.
	 *
	 * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
	 */
	public function getStatus() {
		$reports = array();
		$this->connectionManager = t3lib_div::makeInstance('tx_solr_ConnectionManager');

		$solrConnections = $this->connectionManager->getAllConfigurations();

		foreach ($solrConnections as $solrConnection) {
			$reports[] = $this->getConnectionStatus($solrConnection);
		}

		return $reports;
	}

	/**
	 * Checks whether a Solr server is available and provides some information.
	 *
	 * @param	array	Solr connection parameters
	 * @return	tx_reports_reports_status_Status Status of the Solr connection
	 */
	protected function getConnectionStatus(array $solrConection) {
		$value    = 'Your site was unable to contact the Apache Solr server.';
		$severity = tx_reports_reports_status_Status::ERROR;

		$solr = $this->connectionManager->getConnection(
			$solrConection['solrHost'],
			$solrConection['solrPort'],
			$solrConection['solrPath'],
			$solrConection['solrScheme']
		);

		$message  = '<ul>'
			. '<li style="padding-bottom: 10px;">Site: ' . $solrConection['label'] . '</li>'

			. '<li>Scheme: ' . $solr->getScheme() . '</li>'
			. '<li>Host: ' . $solr->getHost() . '</li>'
			. '<li>Port: ' . $solr->getPort() . '</li>'
			. '<li style="padding-bottom: 10px;">Path: ' . $solr->getPath() . '</li>';

		$pingQueryTime = $solr->ping();

		if ($pingQueryTime !== FALSE) {
			$severity = tx_reports_reports_status_Status::OK;
			$value = 'Your site has contacted the Apache Solr server.';

			$solrVersion = $this->formatSolrVersion($solr->getSolrServerVersion());

			$message .= '<li>Apache Solr: ' . $solrVersion . '</li>';
			$message .= '<li>Ping Query Time: ' . (int)($pingQueryTime * 1000) . 'ms</li>';
			$message .= '<li>schema.xml: ' . $solr->getSchemaName() . '</li>';
			$message .= '<li>solrconfig.xml: ' . $solr->getSolrconfigName() . '</li>';

			$accessFilterPluginStatus = t3lib_div::makeInstance('tx_solr_report_AccessFilterPluginInstalledStatus');
			$accessFilterPluginVersion = $accessFilterPluginStatus->getInstalledPluginVersion($solr);

			$message .= '<li>Access Filter Plugin: ' . $accessFilterPluginVersion . '</li>';
		}

		$message .= '</ul>';

		return t3lib_div::makeInstance('tx_reports_reports_status_Status',
			'Apache Solr',
			$value,
			$message,
			$severity
		);
	}

	/**
	 * Formats the Apache Solr server version number. By default this is going
	 * to be the simple major.minor.patch-level version. Custom Builds provide
	 * more information though, in case of custom builds, their complete
	 * version will be added, too.
	 *
	 * @param	string	$solrVersion Unformatted Apache Solr version number as provided by Solr.
	 * @return	string	formatted short version number, in case of custom builds followed by the complete version number
	 */
	protected function formatSolrVersion($solrVersion) {
		$explodedSolrVersion = explode('.', $solrVersion);

		$shortSolrVersion = $explodedSolrVersion[0]
			. '.' . $explodedSolrVersion[1]
			. '.' . $explodedSolrVersion[2];

		$formattedSolrVersion = $shortSolrVersion;

		if ($solrVersion != $shortSolrVersion) {
			$formattedSolrVersion .= ' (' . $solrVersion . ')';
		}

		return $formattedSolrVersion;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_solrstatus.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_solrstatus.php']);
}

?>