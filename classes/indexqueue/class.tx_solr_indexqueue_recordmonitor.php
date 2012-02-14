<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2011 Ingo Renner <ingo@typo3.org>
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


// TODO track publish / swap events for records (workspace support)

/**
 * A class that monitors changes to records so that the changed record gets
 * passed to the index queue to update the according index document.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_RecordMonitor {

	/**
	 * Hooks into TCE main and tracks record deletion commands.
	 *
	 * @param	string	The command.
	 * @param	string	The table the record belongs to
	 * @param	integer	The record's uid
	 * @param	string
	 * @param	t3lib_TCEmain	TYPO3 Core Engine parent object
	 */
	public function processCmdmap_preProcess($command, $table, $uid, $value, t3lib_TCEmain $tceMain) {
		if ($command == 'delete' && $table == 'tt_content') {
			$indexQueue = t3lib_div::makeInstance('tx_solr_indexqueue_Queue');
			$indexQueue->updateItem('pages', $tceMain->getPID($table, $uid));
		}
	}

	/**
	 * Hooks into TCE main and tracks page move commands.
	 *
	 * @param	string	The command.
	 * @param	string	The table the record belongs to
	 * @param	integer	The record's uid
	 * @param	string
	 * @param	t3lib_TCEmain	TYPO3 Core Engine parent object
	 */
	public function processCmdmap_postProcess($command, $table, $uid, $value, t3lib_TCEmain $tceMain) {
		if ($command == 'move' && $table == 'pages') {
			$indexQueue = t3lib_div::makeInstance('tx_solr_indexqueue_Queue');
			$indexQueue->updateItem('pages', $uid);
		}
	}

	/**
	 * Hooks into TCE Main and watches all record creations and updates. If it
	 * detects that the new/updated record belongs to a table configured for
	 * indexing through Solr, we add the record to the index queue.
	 *
	 * @param	string	Status of the current operation, 'new' or 'update'
	 * @param	string	The table the record belongs to
	 * @param	mixed	The record's uid, [integer] or [string] (like 'NEW...')
	 * @param	array	The record's data
	 * @param	t3lib_TCEmain	TYPO3 Core Engine parent object
	 * @return	void
	 */
	public function processDatamap_afterDatabaseOperations($status, $table, $uid, array $fields, t3lib_TCEmain $tceMain) {
		$recordTable  = $table;
		$recordUid    = $uid;
		$recordPageId = 0;

		if ($status == 'new') {
			$recordUid = $tceMain->substNEWwithIDs[$recordUid];
		}

		if ($status == 'update' && !isset($fields['pid'])) {
			$recordPageId = $tceMain->getPID($recordTable, $recordUid);
		} else {
			$recordPageId = $fields['pid'];
		}

		$indexQueue      = t3lib_div::makeInstance('tx_solr_indexqueue_Queue');
		$monitoredTables = $this->getMonitoredTables($recordPageId);

		if (in_array($recordTable, $monitoredTables)) {
				// FIXME must respect the indexer's additionalWhereClause option: must not add items to the index queue which are excluded through additionalWhereClause

			$record = t3lib_BEfunc::getRecord($recordTable, $recordUid);
			if ($this->isLocalizedRecord($recordTable, $record)) {
					// if it's a localization overlay, update the original record instead
				$recordUid = $record[$GLOBALS['TCA'][$recordTable]['ctrl']['transOrigPointerField']];
			}

			$indexQueue->updateItem($recordTable, $recordUid);

			if ($recordTable == 'pages') {
				$this->updateMountPages($recordUid);
			}
		}

			// when a content element changes we need to updated the page instead
		if ($recordTable == 'tt_content' && in_array('pages', $monitoredTables)) {
			$indexQueue->updateItem('pages', $recordPageId);
		}

			// TODO need to check for creation of "pages_language_overlay" records to trigger "pages" updates
	}

	/**
	 * Gets an array of tables configured for indexing by the Index Queue. The
	 * record monitor must watch these tables for manipulation.
	 *
	 * @param	integer	The page id for which we need to retrieve the configuration for
	 * @return	array	Array of table names to be watched by the record monitor.
	 */
	protected function getMonitoredTables($pageId) {
		$monitoredTables = array();

			// FIXME!! $pageId might be outside of a site root and thus might not know about solr configuration
			// -> leads to record not being queued for reindexing
		$solrConfiguration = tx_solr_Util::getSolrConfigurationFromPageId($pageId);
		$indexingConfigurations = t3lib_div::makeInstance('tx_solr_indexqueue_Queue')
			->getTableIndexingConfigurations($solrConfiguration);

		foreach ($indexingConfigurations as $indexingConfigurationName) {
			$monitoredTable = $indexingConfigurationName;

			if (!empty($solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'])) {
					// table has been set explicitly. Allows to index the same table with different configurations
				$monitoredTable = $solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'];
			}

			$monitoredTables[] = $monitoredTable;
		}

		return array_unique($monitoredTables);
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
		$pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');
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
		$pageIds = array();
		foreach ($rootLine as $pageRecord) {
			$pageIds[] = $pageRecord['uid'];

			if ($pageRecord['is_siteroot']) {
				break;
			}
		}

		$mountPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, uid AS mountPageDestination, mount_pid AS mountPageSource, mount_pid_ol AS mountPageOverlayed',
			'pages',
			'doktype = 7 AND mount_pid IN(' . implode(',', $pageIds) . ')'
		);

		return $mountPages;
	}

	/**
	 * Adds a page to the Index Queue of a site mounting the page.
	 *
	 * @param integer $mountedPageId ID (uid) of the mounted page.
	 * @param array $mountProperties Array of mount point properties mountPageSource, mountPageDestination, and mountPageOverlayed
	 */
	protected function addPageToMountingSiteIndexQueue($mountedPageId, array $mountProperties) {
		$mountingSite = tx_solr_Site::getSiteByPageId($mountProperties['mountPageDestination']);

		$pageInitializer = t3lib_div::makeInstance('tx_solr_indexqueue_initializer_Page');
		$pageInitializer->setSite($mountingSite);

		$pageInitializer->initializeMountedPage($mountProperties, $mountedPageId);
	}

	/**
	 * Checks whether a record is a localization overlay.
	 *
	 * @param string $table The record's table name
	 * @param array $record The record to check
	 * @return boolean TRUE if the record is a language overlay, FALSE otherwise
	 */
	protected function isLocalizedRecord($table, array $record) {
		$isLocalizedRecord = FALSE;
		$translationOriginalPointerField = '';

		if (isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])) {
			$translationOriginalPointerField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];

			if ($record[$translationOriginalPointerField] > 0) {
				$isLocalizedRecord = TRUE;
			}
		}

		return $isLocalizedRecord;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_recordmonitor.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_recordmonitor.php']);
}

?>