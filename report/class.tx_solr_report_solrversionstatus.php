<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Stefan Sprenger <stefan.sprenger@dkd.de>
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
 * Provides a status report about whether the installed Solr version matches
 * the required version.
 *
 * @author	Stefan Sprenger <stefan.sprenger@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_report_SolrVersionStatus implements tx_reports_StatusProvider {

	/**
	 * Required Solr version. The version that gets installed when using the
	 * provided install script EXT:solr/resources/shell/install-solr.sh
	 *
	 * @var	string
	 */
	const REQUIRED_SOLR_VERSION = '3.6.1';

	/**
	 * Compiles a version check against each configured Solr server.
	 *
	 * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
	 */
	public function getStatus() {
		$reports = array();
		$solrConnections = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getAllConnections();

		foreach ($solrConnections as $solrConnection) {
			if ($solrConnection->ping()) {
				$solrVersion = $solrConnection->getSolrServerVersion();

				$isOutdatedVersion = version_compare(
					$this->getCleanSolrVersion($solrVersion),
					self::REQUIRED_SOLR_VERSION,
					'<'
				);

				if ($isOutdatedVersion) {
					$message = '<p style="margin-bottom: 10px;">Found an
						outdated Apache Solr server version. The minimum
						required version is '
						. self::REQUIRED_SOLR_VERSION . '</p>
						<p>Affected Solr server:</p>
						<ul>'
						. '<li>Host: ' . $solrConnection->getHost() . '</li>'
						. '<li>Port: ' . $solrConnection->getPort() . '</li>'
						. '<li>Path: ' . $solrConnection->getPath() . '</li>'
						. '<li><strong>Version: ' . $this->formatSolrVersion($solrVersion) . '</strong></li>
						</ul>';

					$status = t3lib_div::makeInstance('tx_reports_reports_status_Status',
						'Apache Solr Version',
						'Outdated, Unsupported',
						$message,
						tx_reports_reports_status_Status::ERROR
					);

					$reports[] = $status;
				}
			}
		}

		return $reports;
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
		$shortSolrVersion     = $this->getCleanSolrVersion($solrVersion);
		$formattedSolrVersion = $shortSolrVersion;

		if ($solrVersion != $shortSolrVersion) {
			$formattedSolrVersion .= ' (' . $solrVersion . ')';
		}

		return $formattedSolrVersion;
	}

	/**
	 * Gets the clean Solr version in case of a custom build which may have
	 * additional information in the version string.
	 *
	 * @param	string	$solrVersion Unformatted Apache Solr version number as provided by Solr.
	 * @return	string	Clean Solr version number: mayor.minor.patchlevel
	 */
	protected function getCleanSolrVersion($solrVersion) {
		$explodedSolrVersion = explode('.', $solrVersion);

		$shortSolrVersion = $explodedSolrVersion[0]
			. '.' . $explodedSolrVersion[1]
			. '.' . $explodedSolrVersion[2];

		return $shortSolrVersion;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_solrversionstatus.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_solrversionstatus.php']);
}

?>