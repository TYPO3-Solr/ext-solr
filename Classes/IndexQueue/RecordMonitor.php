<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

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

use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * A class that monitors changes to records so that the changed record gets
 * passed to the index queue to update the according index document.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class RecordMonitor {

	/**
	 * Solr TypoScript configuration
	 *
	 * TODO check whether we need this or whether it's better to retrieve each time as in getMonitoredTables()
	 *
	 * @var array
	 */
	protected $solrConfiguration;

	/**
	 * Index Queue
	 *
	 * @var Queue
	 */
	protected $indexQueue;


	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		$this->indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
	}

	/**
	 * Hooks into TCE main and tracks record deletion commands.
	 *
	 * @param string $command The command.
	 * @param string $table The table the record belongs to
	 * @param integer $uid The record's uid
	 * @param string $value
	 * @param DataHandler $tceMain TYPO3 Core Engine parent object
	 */
	public function processCmdmap_preProcess($command, $table, $uid, $value, DataHandler $tceMain) {
		if ($command == 'delete' && $table == 'tt_content' && $GLOBALS['BE_USER']->workspace == 0) {
			// skip workspaces: index only LIVE workspace
			$this->indexQueue->updateItem('pages', $tceMain->getPID($table, $uid));
		}
	}

	/**
	 * Hooks into TCE main and tracks workspace publish/swap events and
	 * page move commands in LIVE workspace.
	 *
	 * @param string $command The command.
	 * @param string $table The table the record belongs to
	 * @param integer $uid The record's uid
	 * @param string $value
	 * @param DataHandler $tceMain TYPO3 Core Engine parent object
	 */
	public function processCmdmap_postProcess($command, $table, $uid, $value, DataHandler $tceMain) {
		if (Util::isDraftRecord($table, $uid)) {
			// skip workspaces: index only LIVE workspace
			return;
		}

		// track publish / swap events for records (workspace support)
		// command "version"
		if ($command == 'version' && $value['action'] == 'swap') {
			switch ($table) {
				/** @noinspection PhpMissingBreakStatementInspection */
				case 'tt_content':
					$uid   = $tceMain->getPID($table, $uid);
					$table = 'pages';
				case 'pages':
					$this->solrConfiguration = Util::getSolrConfigurationFromPageId($uid);
					$record                  = $this->getRecord($table, $uid);

					if (!empty($record) && $this->isEnabledRecord($table, $record)) {
						$this->updateMountPages($uid);

						$this->indexQueue->updateItem($table, $uid);
					} else {
						// TODO should be moved to garbage collector
						if ($this->indexQueue->containsItem($table, $uid)) {
							$this->removeFromIndexAndQueue($table, $uid);
						}
					}
					break;
				default:
					$recordPageId            = $tceMain->getPID($table, $uid);
					$this->solrConfiguration = Util::getSolrConfigurationFromPageId($recordPageId);
					$monitoredTables         = $this->getMonitoredTables($recordPageId);

					if (in_array($table, $monitoredTables)) {
						$record = $this->getRecord($table, $uid);

						if (!empty($record) && $this->isEnabledRecord($table, $record)) {
							if (Util::isLocalizedRecord($table, $record)) {
								// if it's a localization overlay, update the original record instead
								$uid = $record[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']];
							}

							$configurationName = $this->getIndexingConfigurationName($table, $uid);
							$this->indexQueue->updateItem($table, $uid, $configurationName);
						} else {
							// TODO should be moved to garbage collector
							if ($this->indexQueue->containsItem($table, $uid)) {
								$this->removeFromIndexAndQueue($table, $uid);
							}
						}
					}
			}

		}

		if ($command == 'move' && $table == 'pages' && $GLOBALS['BE_USER']->workspace == 0) {
			// moving pages in LIVE workspace
			$this->solrConfiguration = Util::getSolrConfigurationFromPageId($uid);
			$record = $this->getRecord('pages', $uid);
			if (!empty($record) && $this->isEnabledRecord($table, $record)) {
				$this->indexQueue->updateItem('pages', $uid);
			} else {
				// check if the item should be removed from the index because it no longer matches the conditions
				if ($this->indexQueue->containsItem('pages', $uid)) {
					$this->removeFromIndexAndQueue('pages', $uid);
				}
			}
		}
	}

	/**
	 * Hooks into TCE Main and watches all record creations and updates. If it
	 * detects that the new/updated record belongs to a table configured for
	 * indexing through Solr, we add the record to the index queue.
	 *
	 * @param string $status Status of the current operation, 'new' or 'update'
	 * @param string $table The table the record belongs to
	 * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
	 * @param array $fields The record's data
	 * @param DataHandler $tceMain TYPO3 Core Engine parent object
	 * @return void
	 */
	public function processDatamap_afterDatabaseOperations($status, $table, $uid, array $fields, DataHandler $tceMain) {
		$recordTable  = $table;
		$recordUid    = $uid;
		$recordPageId = 0;

		if ($status == 'new') {
			$recordUid = $tceMain->substNEWwithIDs[$recordUid];
		}

		if (Util::isDraftRecord($table, $recordUid)) {
			// skip workspaces: index only LIVE workspace
			return;
		}

		if ($status == 'update' && !isset($fields['pid'])) {
			$recordPageId = $tceMain->getPID($recordTable, $recordUid);
		} else {
			$recordPageId = $fields['pid'];
		}

		// when a content element changes we need to updated the page instead
		if ($recordTable == 'tt_content') {
			$recordTable = 'pages';
			$recordUid   = $recordPageId;
		}

		$this->solrConfiguration = Util::getSolrConfigurationFromPageId($recordPageId);
		$monitoredTables = $this->getMonitoredTables($recordPageId);

		if (in_array($recordTable, $monitoredTables, TRUE)) {
			$record = $this->getRecord($recordTable, $recordUid);

			if (!empty($record)) {
				// only update/insert the item if we actually found a record

				if (Util::isLocalizedRecord($recordTable, $record)) {
					// if it's a localization overlay, update the original record instead
					$recordUid = $record[$GLOBALS['TCA'][$recordTable]['ctrl']['transOrigPointerField']];

					if ($recordTable == 'pages_language_overlay') {
						$recordTable = 'pages';
					}

					$tableEnableFields = implode(', ', $GLOBALS['TCA'][$recordTable]['ctrl']['enablecolumns']);
					$l10nParentRecord = BackendUtility::getRecord($recordTable, $recordUid, $tableEnableFields, '', FALSE);
					if (!$this->isEnabledRecord($recordTable, $l10nParentRecord)) {
						// the l10n parent record must be visible to have it's translation indexed
						return;
					}
				}

				if ($this->isEnabledRecord($recordTable, $record)) {
					$configurationName = NULL;
					if ($recordTable !== 'pages') {
						$configurationName = $this->getIndexingConfigurationName($table, $uid);
					}

					$this->indexQueue->updateItem($recordTable, $recordUid, $configurationName);
				}

				if ($recordTable == 'pages') {
					$this->updateCanonicalPages($recordUid);
					$this->updateMountPages($recordUid);
				}
			} else {
				// TODO move this part to the garbage collector

				// check if the item should be removed from the index because it no longer matches the conditions
				if ($this->indexQueue->containsItem($recordTable, $recordUid)) {
					$this->removeFromIndexAndQueue($recordTable, $recordUid);
				}
			}
		}
	}

	/**
	 * Removes record from the index queue and from the solr index
	 *
	 * @param string $recordTable Name of table where the record lives
	 * @param int $recordUid Id of record
	 */
	protected function removeFromIndexAndQueue($recordTable, $recordUid) {
		$garbageCollector = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\GarbageCollector');
		$garbageCollector->collectGarbage($recordTable, $recordUid);
	}

	/**
	 * Retrieves a record, taking into account the additionalWhereClauses of the
	 * Indexing Queue configurations.
	 *
	 * @param string $recordTable Table to read from
	 * @param int $recordUid Id of the record
	 * @return array Record if found, otherwise empty array
	 */
	protected function getRecord($recordTable, $recordUid) {
		$record = array();

		$indexingConfigurations = $this->indexQueue->getTableIndexingConfigurations($this->solrConfiguration);

		foreach ($indexingConfigurations as $indexingConfigurationName) {
			$tableToIndex = $indexingConfigurationName;
			if (!empty($this->solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'])) {
				// table has been set explicitly. Allows to index the same table with different configurations
				$tableToIndex = $this->solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'];
			}

			if ($tableToIndex === $recordTable) {
				$recordWhereClause = $this->buildUserWhereClause($indexingConfigurationName);
				$record = BackendUtility::getRecord($recordTable, $recordUid, '*', $recordWhereClause);

				if (!empty($record)) {
					// if we found a record which matches the conditions, we can continue
					break;
				}
			}
		}

		return $record;
	}

	/**
	 * Build additional where clause from index queue configuration
	 *
	 * @param string $indexingConfigurationName Indexing configuration name
	 * @return string Optional extra where clause
	 */
	protected function buildUserWhereClause($indexingConfigurationName) {
		$condition = '';

		// FIXME replace this with the mechanism described in ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer::buildUserWhereClause()
		if (isset($this->solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['additionalWhereClause'])) {
			$condition = ' AND ' . $this->solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['additionalWhereClause'];
		}

		return $condition;
	}

	/**
	 * Gets an array of tables configured for indexing by the Index Queue. The
	 * record monitor must watch these tables for manipulation.
	 *
	 * @param integer $pageId The page id for which we need to retrieve the configuration for
	 * @return array Array of table names to be watched by the record monitor.
	 */
	protected function getMonitoredTables($pageId) {
		$monitoredTables = array();

		// FIXME!! $pageId might be outside of a site root and thus might not know about solr configuration
		// -> leads to record not being queued for reindexing
		$solrConfiguration = Util::getSolrConfigurationFromPageId($pageId);
		$indexingConfigurations = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue')
			->getTableIndexingConfigurations($solrConfiguration);

		foreach ($indexingConfigurations as $indexingConfigurationName) {
			$monitoredTable = $indexingConfigurationName;

			if (!empty($solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'])) {
				// table has been set explicitly. Allows to index the same table with different configurations
				$monitoredTable = $solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'];
			}

			$monitoredTables[] = $monitoredTable;
			if ($monitoredTable == 'pages') {
				// when monitoring pages, also monitor creation of translations
				$monitoredTables[] = 'pages_language_overlay';
			}
		}

		return array_unique($monitoredTables);
	}


	// Handle pages showing content from another page


	/**
	 * Triggers Index Queue updates for other pages showing content from the
	 * page currently being updated.
	 *
	 * @param integer $pageId UID of the page currently being updated
	 */
	protected function updateCanonicalPages($pageId) {
		$canonicalPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid',
			'pages',
			'content_from_pid = ' . $pageId
				. BackendUtility::deleteClause('pages')
		);

		foreach ($canonicalPages as $page) {
			$this->indexQueue->updateItem('pages', $page['uid']);
		}
	}


	// Mount Page Handling


	/**
	 * Handles updates of the Index Queue in case a newly created or changed
	 * page is part of a tree that is mounted into a another site.
	 *
	 * @param integer $pageId Page Id (uid).
	 */
	protected function updateMountPages($pageId) {

		// get the root line of the page, every parent page could be a Mount Page source
		$pageSelect = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
		$rootLine   = $pageSelect->getRootLine($pageId);

		// remove the current page / newly created page
		array_shift($rootLine);

		$destinationMountProperties = $this->getDestinationMountPropertiesByRootLine($rootLine);

		if (!empty($destinationMountProperties)) {
			foreach ($destinationMountProperties as $destinationMount) {
				$this->addPageToMountingSiteIndexQueue($pageId, $destinationMount);
			}
		}
	}

	/**
	 * Finds Mount Pages that mount pages in a given root line.
	 *
	 * @param array $rootLine Root line of pages to check for usage as mount source
	 * @return array Array of pages found to be mounting pages from the root line.
	 */
	protected function getDestinationMountPropertiesByRootLine(array $rootLine) {
		$mountPages = array();
		$pageIds    = array();

		if (!empty($rootLine)) {
			foreach ($rootLine as $pageRecord) {
				$pageIds[] = $pageRecord['uid'];

				if ($pageRecord['is_siteroot']) {
					break;
				}
			}

			$mountPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'uid, uid AS mountPageDestination, mount_pid AS mountPageSource, mount_pid_ol AS mountPageOverlayed',
				'pages',
				'doktype = 7 AND no_search = 0 AND mount_pid IN(' . implode(',', $pageIds) . ')'
					. BackendUtility::deleteClause('pages')
			);
		}

		return $mountPages;
	}

	/**
	 * Adds a page to the Index Queue of a site mounting the page.
	 *
	 * @param integer $mountedPageId ID (uid) of the mounted page.
	 * @param array $mountProperties Array of mount point properties mountPageSource, mountPageDestination, and mountPageOverlayed
	 */
	protected function addPageToMountingSiteIndexQueue($mountedPageId, array $mountProperties) {
		$mountingSite = Site::getSiteByPageId($mountProperties['mountPageDestination']);

		$pageInitializer = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Initializer\\Page');
		$pageInitializer->setSite($mountingSite);

		$pageInitializer->initializeMountedPage($mountProperties, $mountedPageId);
	}

	/**
	 * Checks if a record is "enabled"
	 *
	 * A record is considered "enabeled" if
	 *  - it is not hidden
	 *  - it is not deleted
	 *  - as a page it is not set to be excluded from search
	 *
	 * @param string $table The record's table name
	 * @param array $record The record to check
	 * @return boolean TRUE if the record is enabled, FALSE otherwise
	 */
	protected function isEnabledRecord($table, $record) {
		$recordEnabled = TRUE;

		if (
			(isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled']) && !empty($record[$GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled']]))
			||
			(isset($GLOBALS['TCA'][$table]['ctrl']['delete']) && !empty($record[$GLOBALS['TCA'][$table]['ctrl']['delete']]))
			||
			($table == 'pages' && !empty($record['no_search']))
		) {
			$recordEnabled = FALSE;
		}

		return $recordEnabled;
	}

	/**
	 * Retrieves the name of the  Indexing Queue Configuration for a record
	 *
	 * @param string $recordTable Table to read from
	 * @param int $recordUid Id of the record
	 * @return string Name of indexing configuration
	 */
	protected function getIndexingConfigurationName($recordTable, $recordUid) {
		$name = $recordTable;

		$indexingConfigurations = $this->indexQueue->getTableIndexingConfigurations($this->solrConfiguration);

		foreach ($indexingConfigurations as $indexingConfigurationName) {
			$tableToIndex = $indexingConfigurationName;

			if (!$this->solrConfiguration['index.']['queue.'][$indexingConfigurationName]) {
				// ignore disabled indexing configurations
				continue;
			}

			if (!empty($this->solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'])) {
				// table has been set explicitly. Allows to index the same table with different configurations
				$tableToIndex = $this->solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'];
			}

			if ($tableToIndex === $recordTable) {
				$recordWhereClause = $this->buildUserWhereClause($indexingConfigurationName);
				$record = BackendUtility::getRecord($recordTable, $recordUid, '*', $recordWhereClause);

				if (!empty($record)) {
					// we found a record which matches the conditions
					$name = $indexingConfigurationName;
					// FIXME currently returns after the first configuration match
					break;
				}
			}
		}

		return $name;
	}

}

