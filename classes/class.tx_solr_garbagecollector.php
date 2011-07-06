<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Ingo Renner <ingo@typo3.org>
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
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_GarbageCollector {

	protected $trackedRecords = array();

	/**
	 * Hooks into TCE main and tracks record deletions.
	 *
	 * @param	string	The command.
	 * @param	string	The table the record belongs to
	 * @param	integer	The record's uid
	 * @param	string
	 * @param	t3lib_TCEmain	TYPO3 Core Engine parent object
	 */
	public function processCmdmap_preProcess($command, $table, $uid, $value, t3lib_TCEmain $tceMain) {
		if ($command == 'delete') {
			$this->collectGarbage($table, $uid);
		}
	}

	/**
	 * Hooks into TCE main and tracks changed records. In this case the current
	 * record's values are stored to do a change comparison later on for fields
	 * like fe_group.
	 *
	 * @param	array	An array of incoming fields, new or changed
	 * @param	string	The table the record belongs to
	 * @param	mixed	The record's uid, [integer] or [string] (like 'NEW...')
	 * @param	t3lib_TCEmain	TYPO3 Core Engine parent object
	 */
	public function processDatamap_preProcessFieldArray($incomingFields, $table, $uid, t3lib_TCEmain $tceMain) {
		if (!is_int($uid)) {
				// a newly created record, skip
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
	 * @param	string	Status of the current operation, 'new' or 'update'
	 * @param	string	The table the record belongs to
	 * @param	mixed	The record's uid, [integer] or [string] (like 'NEW...')
	 * @param	array	The record's data
	 * @param	t3lib_TCEmain	TYPO3 Core Engine parent object
	 */
	public function processDatamap_afterDatabaseOperations($status, $table, $uid, array $fields, t3lib_TCEmain $tceMain) {
		if ($status == 'new') {
				// a newly created record, skip
			return;
		}

		$visibilityAffectingFields = $this->getVisibilityAffectingFieldsByTable($table);

		$record = t3lib_BEfunc::getRecord($table, $uid, $visibilityAffectingFields, '', FALSE);
		$record = $this->normalizeFrontendGroupField($table, $record);

		if ($this->isHidden($table, $record)
			|| $this->isStartTimeInFuture($table, $record)
			|| $this->isEndTimeInPast($table, $record)
			|| $this->hasFrontendGroupsRemoved($table, $record)
			|| ($table == 'pages' && $this->isExcludedFromSearch($table, $record))
		) {
			$this->collectGarbage($table, $uid);
		}
	}

	/**
	 * Compiles a list of visibility affecting fields of a table so that it can
	 * be used in SQL queries.
	 *
	 * @param	string	Table name to retrieve visibility affecting fields for
	 * @return	string	Comma separated list of field names that affect the visibility of a record on the website
	 */
	protected function getVisibilityAffectingFieldsByTable($table) {
		static $visibilityAffectingFields;

		if (!isset($visibilityAffectingFields[$table])) {
				// we always want to get the uid and pid although they do not affect visibility
			$fields = array('uid', 'pid');
			if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])) {
				$fields = array_merge($fields, $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']);
			}

			if (isset($GLOBALS['TCA'][$table]['delete'])) {
				$fields[] = $GLOBALS['TCA'][$table]['delete'];
			}

			if ($table == 'pages') {
				$fields[] = 'no_search';
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
	 * @param	string	The table name.
	 * @param	array	An array with record fields that may affect visibility.
	 * @return	boolean	True if the record is hidden, FALSE otherwise.
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
	 * @param	string	The table name.
	 * @param	array	An array with record fields that may affect visibility.
	 * @return	boolean	True if the record's start time is in the future, FALSE otherwise.
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
	 * Checks whether an endtime field exists for the record's table and if so
	 * determines if a time is set and whether that time is in the past, making
	 * the record invisible on the website.
	 *
	 * @param	string	The table name.
	 * @param	array	An array with record fields that may affect visibility.
	 * @return	boolean	True if the record's end time is in the past, FALSE otherwise.
	 */
	protected function isEndTimeInPast($table, $record) {
		$endTimeInPast = FALSE;

		if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['endtime'])) {
			$endTimeField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['endtime'];
			$endTimeInPast = ($record[$endTimeField] > 0 && $record[$endTimeField] <= time());
		}

		return $endTimeInPast;
	}

	/**
	 * Checks whether the a frontend group field exists for the record and if so
	 * whether groups have been removed from accessing the record thus making
	 * the record invisible to at least some people.
	 *
	 * @param	string	The table name.
	 * @param	array	An array with record fields that may affect visibility.
	 * @return	boolean	True if frontend groups have been removed from access to the record, FALSE otherwise.
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
	 * Checks whether the record is a page and if so, whether it has been
	 * excluded from searching.
	 *
	 * @param	string	The table name.
	 * @param	array	An array with record fields that may affect visibility.
	 * @return	boolean	True if the page has been excluded from searching, FALSE otherwise
	 */
	protected function isExcludedFromSearch($table, $record) {
		if ($table != 'pages') {
				// ignore tables other than 'pages'
			return FALSE;
		}

		return (boolean) $record['no_search'];
	}

	// TODO add methods to support workspaces, check for swap, unpublish, ...

	/**
	 * Tracks down index documents belonging to a particular record and removes
	 * them from the index and the index queue.
	 *
	 * @param	string	$table The record's table name.
	 * @param	integer	$uid The record's uid.
	 * @return	void
	 */
	public function collectGarbage($table, $uid) {
		$record          = t3lib_BEfunc::getRecord($table, $uid, 'pid', '', FALSE);
		$pageId          = $record['pid'];
		$removeFromQueue = TRUE;

		if ($pageId == 0) {
				// can't get a connection on root, don't index records from root
			return;
		}

			// determine document id
		switch ($table) {
			case 'tt_content':
				$pageRecord = t3lib_BEfunc::getRecord('pages', $record['pid'], 'uid, pid', '', FALSE);

					// overwrite table, uid, and pid
					// to remove the content element's page document
				$table  = 'pages';
				$uid    = $pageRecord['uid'];
				$pageId = $pageRecord['pid'];

					// Do not remove the content element's page's Index Queue
					// entry. We need to update the page's index document now.
				$removeFromQueue = FALSE;
			case 'pages':
				$site = tx_solr_Site::getSiteByPageId($uid);
				$recordDocumentId = $site->getSiteHash()
					. '/' . $table . '/' . $pageId . '/' . $uid . '/*';
				break;
			default:
				$recordDocumentId = tx_solr_Util::getDocumentId($table, $pageId, $uid);
		}

		try {
			if ($removeFromQueue) {
					// remove the item from Index Queue
				t3lib_div::makeInstance('tx_solr_indexqueue_Queue')->deleteItem($table, $uid);
			}

				// get connection
			$solr = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getConnectionByPageId($pageId);

			if ($solr->ping()) {
					// delete document(s) from index, directly commit
				$solr->deleteByQuery('id:' . $recordDocumentId);
				$solr->commit();
			}
		} catch (tx_solr_NoSolrConnectionFoundException $e) {
			t3lib_div::devLog(
				'Failed to get Solr connection while trying to collect garbage documents.',
				'solr',
				3,
				array(
					'Exception Message'  => $e->getMessage(),
					'Exception Code'     => $e->getCode(),
					'Exception Trace'    => $e->getTrace(),
					'Record'             => $table . ':' . $uid,
					'Page ID used to get Solr Connection' => $pageId,
					'Record Document ID' => $recordDocumentId
				)
			);
		}
	}

	/**
	 * Makes sure that "empty" frontend group fields are always the same value.
	 *
	 * @param	string	The record's table name.
	 * @param	integer	The record's uid.
	 * @return	array	The cleaned record
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


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_garbagecollector.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_garbagecollector.php']);
}

?>