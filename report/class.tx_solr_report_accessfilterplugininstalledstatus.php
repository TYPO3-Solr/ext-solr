<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Ingo Renner <ingo@typo3.org>
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
 * Provides a status report about whether the Access Filter Query Parser Plugin
 * is installed on the Solr server.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage tx_solr
 */
class tx_solr_report_AccessFilterPluginInstalledStatus implements tx_reports_StatusProvider {

	/**
	 * Compiles a collection of solrconfig.xml checks against each configured
	 * Solr server. Only adds an entry if the Access Filter Query Parser Plugin
	 * is not configured.
	 *
	 * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
	 */
	public function getStatus() {
		$reports = array();
		$solrConnections = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getAllConnections();

		foreach ($solrConnections as $solrConnection) {

			if ($solrConnection->ping()
			&& !$this->isAccessFilterQueryParserPluginInstalled($solrConnection)) {

				$message = '<p style="margin-bottom: 10px;">EXT:solr comes with
				a plugin for the Apache Solr server to ensure TYPO3 access
				restrictions are enforced for search results, thus allowing
				visitors to only see results they are allowed to see. The plugin
				is not installed on the following server. It is
				recommended to install the plugin.</p>';

				$message .= '<p>Affected Solr server:</p>
					<ul>'
					. '<li>Host: ' . $solrConnection->getHost() . '</li>'
					. '<li>Port: ' . $solrConnection->getPort() . '</li>'
					. '<li>Path: ' . $solrConnection->getPath() . '</li>
					</ul>';

				$status = t3lib_div::makeInstance('tx_reports_reports_status_Status',
					'Access Filter Query Parser Plugin',
					'Not Installed',
					$message,
					tx_reports_reports_status_Status::WARNING
				);

				$reports[] = $status;
			}
		}

		return $reports;
	}

	/**
	 * Checks whether the Access Filter Query Parser Plugin is installed for
	 * the given Solr server instance.
	 *
	 * @param	tx_solr_SolrService	Solr connection to check for the plugin.
	 * @return	boolean	True if the plugin is installed, false otherwise.
	 */
	protected function isAccessFilterQueryParserPluginInstalled(tx_solr_SolrService $solrConnection) {
		$accessFilterQueryParserPluginInstalled = false;

		$solrconfigXmlUrl = $solrConnection->getScheme() . '://'
			. $solrConnection->getHost() . ':' . $solrConnection->getPort()
			. $solrConnection->getPath()
			. 'admin/file/?file=solrconfig.xml';

		$solrconfigXml = simplexml_load_file($solrconfigXmlUrl);

		foreach ($solrconfigXml->queryParser as $queryParser) {
			if ($queryParser['name'] == 'typo3access'
			&& $queryParser['class'] == 'org.typo3.solr.search.AccessFilterQParserPlugin') {
				$accessFilterQueryParserPluginInstalled = true;
				break;
			}
		}

		return $accessFilterQueryParserPluginInstalled;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_accessfilterplugininstalledstatus.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_accessfilterplugininstalledstatus.php']);
}

?>