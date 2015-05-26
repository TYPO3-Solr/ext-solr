<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2012 Ingo Renner <ingo@typo3.org>
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
 * Garbage Collector, removes related documents from the index when a record is
 * set to hidden, is deleted or is otherwise made invisible to website visitors.
 *
 * Garbage collection will happen for online/LIVE workspaces only.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Tx_Solr_GarbageCollector {

	protected $trackedRecords = array();

	/**
	 * Hooks into TCE main and tracks record deletion commands.
	 *
	 * @param string $command The command.
	 * @param string $table The table the record belongs to
	 * @param integer $uid The record's uid
	 * @param string $value Not used
	 * @param t3lib_TCEmain $tceMain TYPO3 Core Engine parent object, not used
	 */
	public function processCmdmap_preProcess($command, $table, $uid, $value, t3lib_TCEmain $tceMain) {
			// workspaces: collect garbage only for LIVE workspace
		if ($command == 'delete' && $GLOBALS['BE_USER']->workspace == 0) {
			$this->collectGarbage($table, $uid);

			if ($table == 'pages') {
				$indexQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');
				$indexQueue->deleteItem($table, $uid);
			}
		}
	}

	/**
	 * Hooks into TCE main and tracks page move commands.
	 *
	 * @param string $command The command.
	 * @param string $table The table the record belongs to
	 * @param integer $uid The record's uid
	 * @param string $value Not used
	 * @param t3lib_TCEmain $tceMain TYPO3 Core Engine parent object, not used
	 */
	public function processCmdmap_postProcess($command, $table, $uid, $value, t3lib_TCEmain $tceMain) {
			// workspaces: collect garbage only for LIVE workspace
		if ($command == 'move' && $table == 'pages' && $GLOBALS['BE_USER']->workspace == 0) {
				// TODO the below comment is not valid anymore, pid has been removed from doc ID
				// ...still needed?

				// must be removed from index since the pid changes and
				// is part of the Solr document ID
			$this->collectGarbage($table, $uid);

				// now re-index with new properties
			$indexQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');
			$indexQueue->updateItem($table, $uid);
		}
	}

	/**
	 * Hooks into TCE main and tracks changed records. In this case the current
	 * record's values are stored to do a change comparison later on for fields
	 * like fe_group.
	 *
	 * @param array $incomingFields An array of incoming fields, new or changed, not used
	 * @param string $table The table the record belongs to
	 * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
	 * @param t3lib_TCEmain $tceMain TYPO3 Core Engine parent object, not used
	 */
	public function processDatamap_preProcessFieldArray($incomingFields, $table, $uid, t3lib_TCEmain $tceMain) {
		if (!is_int($uid)) {
				// a newly created record, skip
			return;
		}

		if (Tx_Solr_Util::isDraftRecord($table, $uid)) {
				// skip workspaces: collect garbage only for LIVE workspace
			return;
		}

		$visibilityAffectingFields = $this->getVisibilityAffectingFieldsByTable($table);

		if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])
			&& array_key_exists('fe_group', $GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])) {

			$record = t3lib_BEfunc::getRecord(
				$table,
				$uid,
				$visibilityAffectingFields,
				'',
				FALSE
			);
			$record = $this->normalizeFrontendGroupField($table, $record);

				// keep previous state of important fields for later comparison
			$this->trackedRecords[$table][$uid] = $record;
		}
	}

	/**
	 * Hooks into TCE Main and watches all record updates. If a change is
	 * detected that would remove the record from the website, we try to find
	 * related documents and remove them from the index.
	 *
	 * @param string $status Status of the current operation, 'new' or 'update'
	 * @param string $table The table the record belongs to
	 * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
	 * @param array $fields The record's data, not used
	 * @param t3lib_TCEmain $tceMain TYPO3 Core Engine parent object, not used
	 */
	public function processDatamap_afterDatabaseOperations($status, $table, $uid, array $fields, t3lib_TCEmain $tceMain) {
		if ($status == 'new') {
				// a newly created record, skip
			return;
		}

		if (Tx_Solr_Util::isDraftRecord($table, $uid)) {
				// skip workspaces: collect garbage only for LIVE workspace
			return;
		}

		$garbageCollectionRelevantFields = $this->getVisibilityAffectingFieldsByTable($table);

		$record = t3lib_BEfunc::getRecord($table, $uid, $garbageCollectionRelevantFields, '', FALSE);
		$record = $this->normalizeFrontendGroupField($table, $record);

		if ($this->isHidden($table, $record)
			|| ($this->isStartTimeInFuture($table, $record) && $this->isMarkedAsIndexed($table, $record))
			|| $this->hasFrontendGroupsRemoved($table, $record)
			|| ($table == 'pages' && $this->isPageExcludedFromSearch($record))
			|| ($table == 'pages' && !$this->isIndexablePageType($record))
		) {
			$this->collectGarbage($table, $uid);

			// if the start time is in the future and not otherwise invisible,
			// it is necessary to add the item to the queue again to be indexed once the start time is reached
			if ($this->isStartTimeInFuture($table, $record)
				&& !$this->isHidden($table, $record)
				&& !$this->hasFrontendGroupsRemoved($table, $record)
				&& ($table != 'tt_content' && $table != 'pages_language_overlay')
				&& !($table == 'pages' && $this->isPageExcludedFromSearch($record))
				&& !($table == 'pages' && !$this->isIndexablePageType($record))
			) {
				/** @var Tx_Solr_IndexQueue_Queue $indexQueue */
				$indexQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');
				$indexQueue->updateItem($table, $uid);
			}
		}
	}

	/**
	 * Compiles a list of visibility affecting fields of a table so that it can
	 * be used in SQL queries.
	 *
	 * @param string $table Table name to retrieve visibility affecting fields for
	 * @return string Comma separated list of field names that affect the visibility of a record on the website
	 */
	protected function getVisibilityAffectingFieldsByTable($table) {
		static $visibilityAffectingFields;

		if (!isset($visibilityAffectingFields[$table])) {
				// we always want to get the uid and pid although they do not affect visibility
			$fields = array('uid', 'pid');
			if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])) {
				$fields = array_merge($fields, $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']);
			}

			if (isset($GLOBALS['TCA'][$table]['ctrl']['delete'])) {
				$fields[] = $GLOBALS['TCA'][$table]['ctrl']['delete'];
			}

			if ($table == 'pages') {
				$fields[] = 'no_search';
				$fields[] = 'doktype';
			}

			$visibilityAffectingFields[$table] = implode(', ', $fields);
		}

		return $visibilityAffectingFields[$table];
	}


	// methods checking whether to trigger garbage collection


	/**
	 * Checks whether a hidden field exists for the current table and if so
	 * determines whether it is set on the current record.
	 *
	 * @param string $table The table name.
	 * @param array $record An array with record fields that may affect visibility.
	 * @return boolean True if the record is hidden, FALSE otherwise.
	 */
	protected function isHidden($table, $record) {
		$hidden = FALSE;

		if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'])) {
			$hiddenField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'];
			$hidden      = (boolean) $record[$hiddenField];
		}

		return $hidden;
	}

	/**
	 * Checks whether a start time field exists for the record's table and if so
	 * determines if a time is set and whether that time is in the future,
	 * making the record invisible on the website.
	 *
	 * @param string $table The table name.
	 * @param array $record An array with record fields that may affect visibility.
	 * @return boolean True if the record's start time is in the future, FALSE otherwise.
	 */
	protected function isStartTimeInFuture($table, $record) {
		$startTimeInFuture = FALSE;

		if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['starttime'])) {
			$startTimeField    = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['starttime'];
			$startTimeInFuture = $record[$startTimeField] > time();
		}

		return $startTimeInFuture;
	}

	/**
	 * Checks whether the record is in the Index Queue and whether it has been
	 * indexed already.
	 *
	 * @param string $table The table name.
	 * @param array $record An array with record fields that may affect visibility.
	 * @return boolean True if the record is marked as being indexed
	 */
	protected function isMarkedAsIndexed($table, $record) {
		$indexQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');
		return $indexQueue->containsIndexedItem($table, $record['uid']);
	}

	/**
	 * Checks whether the a frontend group field exists for the record and if so
	 * whether groups have been removed from accessing the record thus making
	 * the record invisible to at least some people.
	 *
	 * @param string $table The table name.
	 * @param array $record An array with record fields that may affect visibility.
	 * @return boolean TRUE if frontend groups have been removed from access to the record, FALSE otherwise.
	 */
	protected function hasFrontendGroupsRemoved($table, $record) {
		$frontendGroupsRemoved = FALSE;

		if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'])) {
			$frontendGroupsField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'];
			$previousGroups = $this->trackedRecords[$table][$record['uid']][$frontendGroupsField];

			$previousGroups = explode(',', (string) $this->trackedRecords[$table][$record['uid']][$frontendGroupsField]);
			$currentGroups  = explode(',', (string) $record[$frontendGroupsField]);

			$removedGroups  = array_diff($previousGroups, $currentGroups);

			$frontendGroupsRemoved = (boolean) count($removedGroups);
		}

		return $frontendGroupsRemoved;
	}

	/**
	 * Checks whether the page has been excluded from searching.
	 *
	 * @param array $record An array with record fields that may affect visibility.
	 * @return boolean True if the page has been excluded from searching, FALSE otherwise
	 */
	protected function isPageExcludedFromSearch($record) {
		return (boolean) $record['no_search'];
	}

	/**
	 * Checks whether a page has a page type that can be indexed.
	 * Currently standard pages and mount pages can be indexed.
	 *
	 * @param array $record A page record
	 * @return boolean TRUE if the page can be indexed according to its page type, FALSE otherwise
	 */
	protected function isIndexablePageType(array $record) {
		return tx_solr_Util::isAllowedPageType($record);
	}

	/**
	 * Tracks down index documents belonging to a particular record or page and
	 * removes them from the index and the Index Queue.
	 *
	 * @param string $table The record's table name.
	 * @param integer $uid The record's uid.
	 * @throws UnexpectedValueException if a hook object does not implement interface Tx_Solr_GarbageCollectorPostProcessor
	 */
	public function collectGarbage($table, $uid) {
		if ($table == 'tt_content' || $table == 'pages' || $table == 'pages_language_overlay') {
			$this->collectPageGarbage($table, $uid);
		} else {
			$this->collectRecordGarbage($table, $uid);
		}

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessGarbageCollector'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessGarbageCollector'] as $classReference) {
				$garbageCollectorPostProcessor = \TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj($classReference);

				if ($garbageCollectorPostProcessor instanceof Tx_Solr_GarbageCollectorPostProcessor) {
					$garbageCollectorPostProcessor->postProcessGarbageCollector($table, $uid);
				} else {
					throw new UnexpectedValueException(
						get_class($garbageCollectorPostProcessor) . ' must implement interface Tx_Solr_GarbageCollectorPostProcessor',
						1345807460
					);
				}
			}
		}
	}

	/**
	 * Cleans an index from garbage entries.
	 *
	 * Was used to clean the index from expired documents/past endtime. Solr 4.8
	 * introduced DocExpirationUpdateProcessor to do that job by itself.
	 *
	 * The method remains as a dummy for possible later cleanups and to prevent
	 * things from breaking if others were using it.
	 *
	 * @param Tx_Solr_Site $site The site to clean indexes on
	 * @param boolean $commitAfterCleanUp Whether to commit right after the clean up, defaults to TRUE
	 * @return void
	 */
	public function cleanIndex(Tx_Solr_Site $site, $commitAfterCleanUp = TRUE) {

	}

	/**
	 * Tracks down index documents belonging to a particular record and
	 * removes them from the index and the Index Queue.
	 *
	 * @param string $table The record's table name.
	 * @param integer $uid The record's uid.
	 */
	protected function collectRecordGarbage($table, $uid) {
		$indexQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');

		$this->deleteIndexDocuments($table, $uid);
		$indexQueue->deleteItem($table, $uid);
	}

	/**
	 * Tracks down index documents belonging to a particular page and
	 * removes them from the index and the Index Queue.
	 *
	 * @param string $table The record's table name.
	 * @param integer $uid The record's uid.
	 */
	protected function collectPageGarbage($table, $uid) {
		$indexQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');

		switch ($table) {
			case 'tt_content':
				$contentElement = t3lib_BEfunc::getRecord('tt_content', $uid, 'uid, pid', '', FALSE);

				$table = 'pages';
				$uid   = $contentElement['pid'];

				$this->deleteIndexDocuments($table, $uid);
					// only a content element was removed, now update/re-index the page
				$indexQueue->updateItem($table, $uid);
				break;
			case 'pages_language_overlay':
				$pageOverlayRecord = t3lib_BEfunc::getRecord('pages_language_overlay', $uid, 'uid, pid', '', FALSE);

				$table = 'pages';
				$uid   = $pageOverlayRecord['pid'];

				$this->deleteIndexDocuments($table, $uid);
					// only a page overlay was removed, now update/re-index the page
				$indexQueue->updateItem($table, $uid);
				break;
			case 'pages':
				$this->deleteIndexDocuments($table, $uid);
				$indexQueue->deleteItem($table, $uid);
				break;
		}
	}

	/**
	 * Deletes index documents for a given record identification.
	 *
	 * @param string $table The record's table name.
	 * @param integer $uid The record's uid.
	 */
	protected function deleteIndexDocuments($table, $uid) {
		$indexQueue        = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');
		$connectionManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_ConnectionManager');

			// record can be indexed for multiple sites
		$indexQueueItems = $indexQueue->getItems($table, $uid);

		foreach ($indexQueueItems as $indexQueueItem) {
			$site = $indexQueueItem->getSite();

				// a site can have multiple connections (cores / languages)
			$solrConnections = $connectionManager->getConnectionsBySite($site);
			foreach ($solrConnections as $solr) {
				$solr->deleteByQuery('type:' . $table . ' AND uid:' . intval($uid));
				$solr->commit(FALSE, FALSE, FALSE);
			}
		}
	}

	/**
	 * Makes sure that "empty" frontend group fields are always the same value.
	 *
	 * @param string $table The record's table name.
	 * @param integer $record The record's uid.
	 * @return array The cleaned record
	 */
	protected function normalizeFrontendGroupField($table, $record) {

		if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'])) {
			$frontendGroupsField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'];

			if ($record[$frontendGroupsField] == '') {
				$record[$frontendGroupsField] = '0';
			}
		}

		return $record;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/GarbageCollector.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/GarbageCollector.php']);
}

?>