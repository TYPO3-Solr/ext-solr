<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo@typo3.org>
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
 * Provides an status report, which checks whether the configuration of the
 * extension is ok.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_report_SolrConfigurationStatus implements tx_reports_StatusProvider {

	/**
	 * Compiles a collection of configuration status checks.
	 *
	 * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
	 */
	public function getStatus() {
		$reports = array();

		$rootPageFlagStatus = $this->getRootPageFlagStatus();
		if (!is_null($rootPageFlagStatus)) {
			$reports[] = $rootPageFlagStatus;

				// intended early return, no sense in going on if there are no root pages
			return $reports;
		}

		$domainRecordAvailableStatus = $this->getDomainRecordAvailableStatus();
		if (!is_null($domainRecordAvailableStatus)) {
			$reports[] = $domainRecordAvailableStatus;
		}

		$configIndexEnableStatus = $this->getConfigIndexEnableStatus();
		if (!is_null($configIndexEnableStatus)) {
			$reports[] = $configIndexEnableStatus;
		}

		return $reports;
	}

	/**
	 * Checks whether the "Use as Root Page" page property has been set for any
	 * site.
	 *
	 * @return	NULL|tx_reports_reports_status_Status	An error status is returned if no root pages were found.
	 */
	protected function getRootPageFlagStatus() {
		$status    = NULL;
		$rootPages = $this->getRootPages();

		if (empty($rootPages)) {
			$status = t3lib_div::makeInstance('tx_reports_reports_status_Status',
				'Sites',
				'No sites found',
				'Connections to your Solr server are detected automatically.
				To make this work you need to set the "Use as Root Page" page
				property for your site root pages.',
				tx_reports_reports_status_Status::ERROR
			);
		}

		return $status;
	}

	/**
	 * Checks whether a domain record (sys_domain) has been configured for each site root.
	 *
	 * @return	NULL|tx_reports_reports_status_Status	An error status is returned for each site root page without domain record.
	 */
	protected function getDomainRecordAvailableStatus() {
		$status                 = NULL;
		$rootPages              = $this->getRootPages();
		$rootPagesWithoutDomain = array();

		$rootPageIds = array();
		foreach ($rootPages as $rootPage) {
			$rootPageIds[] = $rootPage['uid'];
		}

		$domainRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, pid',
			'sys_domain',
			'pid IN(' . implode(',', $rootPageIds) . ') AND redirectTo=\'\' AND hidden=0',
			'pid',
			'pid, sorting',
			'',
			'pid'
		);

		foreach ($rootPageIds as $rootPageId) {
			if (!array_key_exists($rootPageId, $domainRecords)) {
				$rootPagesWithoutDomain[$rootPageId] = $rootPages[$rootPageId];
			}
		}

		if (!empty($rootPagesWithoutDomain)) {
			foreach ($rootPagesWithoutDomain as $pageId => $page) {
				$rootPagesWithoutDomain[$pageId] = '[' . $page['uid'] . '] ' . $page['title'];
			}

			$status = t3lib_div::makeInstance('tx_reports_reports_status_Status',
				'Domain Records',
				'Domain records missing',
				'Domain records are needed to properly index pages. The following
				sites are marked as root pages, but do not have a domain configured:
				<ul><li>' . implode('</li><li>', $rootPagesWithoutDomain) . '</li></ul>',
				tx_reports_reports_status_Status::ERROR
			);
		}

		return $status;
	}

	/**
	 * Checks whether config.index_enable is set to 1, otherwise indexing will
	 * not work.
	 *
	 * @return	NULL|tx_reports_reports_status_Status	An error status is returned for each site root page config.index_enable = 0.
	 */
	protected function getConfigIndexEnableStatus() {
		$status                   = NULL;
		$rootPages                = $this->getRootPages();
		$rootPagesWithIndexingOff = array();

		foreach ($rootPages as $rootPage) {
			try {
				tx_solr_Util::initializeTsfe($rootPage['uid']);

				if (!$GLOBALS['TSFE']->config['config']['index_enable']) {
					$rootPagesWithIndexingOff[] = $rootPage;
				}
			} catch (RuntimeException $rte) {

				$rootPagesWithIndexingOff[] = $rootPage;
			} catch (t3lib_error_http_ServiceUnavailableException $sue) {
				if ($sue->getCode() == 1294587218) {
						//  No TypoScript template found, continue with next site
					continue;
				}
			}
		}

		if (!empty($rootPagesWithIndexingOff)) {
			foreach ($rootPagesWithIndexingOff as $key => $rootPageWithIndexingOff) {
				$rootPagesWithIndexingOff[$key] = '[' . $rootPageWithIndexingOff['uid'] . '] ' . $rootPageWithIndexingOff['title'];
			}

			$status = t3lib_div::makeInstance('tx_reports_reports_status_Status',
				'Page Indexing',
				'Indexing is disabled',
				'You need to set config.index_enable = 1 to allow page indexing.
				The following sites were found with indexing disabled:
				<ul><li>' . implode('</li><li>', $rootPagesWithIndexingOff) . '</li></ul>',
				tx_reports_reports_status_Status::ERROR
			);
		}

		return $status;
	}

	/**
	 * Gets the site's root pages. The "Is root of website" flag must be set,
	 * which usually is the case for pages with pid = 0.
	 *
	 * @return	array	An array of (partial) root page records, containing the uid and title fields
	 */
	protected function getRootPages() {
		$rootPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, title',
			'pages',
			'is_siteroot = 1 AND deleted = 0 AND hidden = 0 AND pid != -1 AND doktype IN(1,4) ',
			'', '', '',
			'uid'
		);

		return $rootPages;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_solrconfigurationstatus.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_solrconfigurationstatus.php']);
}

?>