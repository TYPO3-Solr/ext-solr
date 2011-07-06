<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2011 Ingo Renner <ingo.renner@dkd.de>
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
 * General frontend page indexer.
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @author	Daniel Poetzinger <poetzinger@aoemedia.de>
 * @author	Timo Schmidt <schmidt@aoemedia.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_Indexer implements tslib_content_PostInitHook {

	/**
	 * Collects the frontens user group IDs used in content elements on the
	 * page (and the currently logged in user has access to).
	 *
	 * @var	array
	 */
	protected static $contentFrontendUserAccessGroups = array();

	/**
	 * the page currently being indexed.
	 *
	 * @var	tslib_fe
	 */
	protected $page;


	// page indexing


	/**
	 * Handles the indexing of the page content during post processing of
	 * a generated page.
	 *
	 * @param	tslib_fe	Typoscript frontend
	 */
	public function hook_indexContent(tslib_fe $page) {
		$this->page = $page;

			// determine if the current page should be indexed
		if ($this->indexingEnabled()) {
			try {
					// do some checks first
				if ($page->page['no_search']) {
					throw new Exception(
						'Index page? No, The "No Search" flag has been set in the page properties!',
						1234523946
					);
				}

				if ($page->no_cache) {
					throw new Exception(
						'Index page? No, page was set to "no_cache" and so cannot be indexed.',
						1234524030
					);
				}

				if ($page->sys_language_uid != $page->sys_language_content) {
					throw new Exception(
						'Index page? No, ->sys_language_uid was different from sys_language_content which indicates that the page contains fall-back content and that would be falsely indexed as localized content.',
						1234524095
					);
				}

				if ($GLOBALS['TSFE']->beUserLogin && !$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['enableIndexingWhileBeUserLoggedIn']) {
					throw new Exception(
						'Index page? No, Detected a BE user being logged in.',
						1246444055
					);
				}

					// everything ready, let's do it
				$indexer = t3lib_div::makeInstance('tx_solr_Typo3PageIndexer', $page);
				$indexer->setPageAccessRootline($this->getAccessRootline());
				$indexer->indexPage();

			} catch (Exception $e) {
#				$this->log($e->getMessage() . ' Error code: ' . $e->getCode(), 3);
				if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
					t3lib_div::devLog('Exception while trying to index a page', 'solr', 3, array(
						$e->__toString()
					));
				}
			}
		}
	}

	/**
	 * Determines whether indexing is enabled for a given page.
	 *
	 * @return	boolean	Indicator whether the page should be indexed or not.
	 * @todo	Move to tx_solr_Typo3Environment
	 */
	protected function indexingEnabled() {
		$indexingEnabled = FALSE;

		if ($this->page->config['config']['index_enable']) {
			if (is_array($this->page->applicationData['tx_crawler'])) {
				$crawlerActive = t3lib_extMgm::isLoaded('crawler') && $this->page->applicationData['tx_crawler']['running'];
			}

			$solrPageIndexingEnabled    = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['enablePageIndexing'];
			$solrCrawlerIndexingEnabled = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['enableCrawlerIndexing'];

			/*
			 * A page can be indexed by a normal frontend call or by the crawler.
			 * In case of the crawler the following requirements need to be met:
			 *   - Indexing of pages in general enabled
			 *   - Indexing with the crawler needs to be enabled in the settings
			 *   - Crawler needs to be loaded
			 *   - Crawler needs to be active
			 *   - Processing instruction needs to be active
			 * In case of an normal frontend indexing call the following requirements need to be met:
			 *   - Indexing of pages in general enabled
			 *   - pageIndexing needs to be enabled in the solr config
			 */
			if ($solrCrawlerIndexingEnabled && $crawlerActive) {
				$solrProcessingInstructionActive = in_array(
					'tx_solr_reindex',
					$this->page->applicationData['tx_crawler']['parameters']['procInstructions']
				);
				$this->page->applicationData['tx_crawler']['log'][] = 'Solr is indexing';

				if ($solrProcessingInstructionActive) {
					$indexingEnabled = TRUE;
				}
			} elseif ($solrPageIndexingEnabled) {
				$indexingEnabled = TRUE;
			}
		}

		return $indexingEnabled;
	}


	// User group tracking & access restrictions


	/**
	 * Hook for post processing the initialization of tslib_cObj
	 *
	 * @param	tslib_cObj	parent content object
	 */
	public function postProcessContentObjectInitialization(tslib_cObj &$parentObject) {
		$this->trackContentAccessGroups($parentObject);
	}

	/**
	 * Tracks the content access groups applied to content elements.
	 *
	 * @param	tslib_cObj	$contentObject Current content object
	 */
	protected function trackContentAccessGroups(tslib_cObj $contentObject) {
		if (!empty($contentObject->currentRecord)) {
			list($table) = explode(':', $contentObject->currentRecord);

			if (!empty($table)
				&& $table != 'pages'
				&& $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group']
			) {
				$allowedGroups = $contentObject->data[$GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group']];
				if (!empty($allowedGroups)) {
					self::$contentFrontendUserAccessGroups[] = $allowedGroups;
				}
			}
		}
	}

	/**
	 * Gets the access rootline for the current page.
	 *
	 * @return	tx_solr_access_Rootline	The page's access rootline
	 */
	protected function getAccessRootline() {
		$accessRootline = tx_solr_access_Rootline::getAccessRootlineByPageId($this->page->id);

		$contentAccessGroups = $this->getAccessGroupsFromContent();
		$contentAccessGroups = 'c:' . implode(',', $contentAccessGroups);

		$accessRootline->push(t3lib_div::makeInstance(
			'tx_solr_access_RootlineElement',
			$contentAccessGroups
		));

		return $accessRootline;
	}

	/**
	 * Gets the groups set as access restrictions on content elements present
	 * on the current page.
	 *
	 * @return	array	An array of fe group IDs.
	 */
	protected function getAccessGroupsFromContent() {
		$groupList = implode(',', self::$contentFrontendUserAccessGroups);
		$groups    = t3lib_div::intExplode(',', $groupList);

		$groups = tx_solr_access_Rootline::cleanGroupArray($groups);

		if (empty($groups)) {
			$groups[] = 0;
		}

		return $groups;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_indexer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_indexer.php']);
}

?>