<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
*  Copyright notice
*
*  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;


/**
 * Provides an status report about whether a connection to the Solr server can
 * be established.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class SolrStatus implements StatusProviderInterface {

	/**
	 * Connection Manager
	 *
	 * @var ConnectionManager
	 */
	protected $connectionManager = NULL;

	/**
	 * Compiles a collection of status checks against each configured Solr server.
	 *
	 */
	public function getStatus() {
		$reports = array();
		$this->connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager');

		$solrConnections = $this->connectionManager->getAllConfigurations();

		foreach ($solrConnections as $solrConnection) {
			$reports[] = $this->getConnectionStatus($solrConnection);
		}

		return $reports;
	}

	/**
	 * Checks whether a Solr server is available and provides some information.
	 *
	 * @param array $solrConnection Solr connection parameters
	 * @return Status Status of the Solr connection
	 */
	protected function getConnectionStatus(array $solrConnection) {
		$value    = 'Your site was unable to contact the Apache Solr server.';
		$severity = Status::ERROR;

		$solr = $this->connectionManager->getConnection(
			$solrConnection['solrHost'],
			$solrConnection['solrPort'],
			$solrConnection['solrPath'],
			$solrConnection['solrScheme']
		);

		$message  = '<ul>'
			. '<li style="padding-bottom: 10px;">Site: ' . $solrConnection['label'] . '</li>'

			. '<li>Scheme: ' . $solr->getScheme() . '</li>'
			. '<li>Host: ' . $solr->getHost() . '</li>'
			. '<li>Port: ' . $solr->getPort() . '</li>'
			. '<li style="padding-bottom: 10px;">Path: ' . $solr->getPath() . '</li>';

		$pingQueryTime = $solr->ping();

		if ($pingQueryTime !== FALSE) {
			$severity = Status::OK;
			$value = 'Your site has contacted the Apache Solr server.';

			$solrVersion = $this->formatSolrVersion($solr->getSolrServerVersion());

			$message .= '<li>Apache Solr: ' . $solrVersion . '</li>';
			$message .= '<li>Ping Query Time: ' . (int)($pingQueryTime * 1000) . 'ms</li>';
			$message .= '<li>schema.xml: ' . $solr->getSchemaName() . '</li>';
			$message .= '<li>solrconfig.xml: ' . $solr->getSolrconfigName() . '</li>';

			$accessFilterPluginStatus  = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Report\\AccessFilterPluginInstalledStatus');
			$accessFilterPluginVersion = $accessFilterPluginStatus->getInstalledPluginVersion($solr);

			$message .= '<li>Access Filter Plugin: ' . $accessFilterPluginVersion . '</li>';
		}

		$message .= '</ul>';

		return GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status',
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
	 * @param string $solrVersion Unformatted Apache Solr version number as provided by Solr.
	 * @return string formatted short version number, in case of custom builds followed by the complete version number
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

