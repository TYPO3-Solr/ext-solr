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
	 * Hooks into TCE main and tracks record deletions.
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
		$recordTable = $table;
		$recordUid   = $uid;
		$recordPid   = 0;

		if ($status == 'new') {
			$recordUid = $tceMain->substNEWwithIDs[$recordUid];
		}

		if ($status == 'update' && !isset($fields['pid'])) {
			$recordPid = $tceMain->getPID($recordTable, $recordUid);
		} else {
			$recordPid = $fields['pid'];
		}

		$monitoredTables = $this->getMonitoredTables($recordPid);
		$indexQueue = t3lib_div::makeInstance('tx_solr_indexqueue_Queue');

		if (in_array($recordTable, $monitoredTables)) {
				// FIXME must respect the indexer's additionalWhereClause option: must not add items to the index queue which are excluded through additionalWhereClause

			$indexQueue->updateItem($recordTable, $recordUid);
		}

			// when a content element changes we need to updated the page instead
		if ($recordTable == 'tt_content' && in_array('pages', $monitoredTables)) {
			$indexQueue->updateItem('pages', $recordPid);
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

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_recordmonitor.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_recordmonitor.php']);
}

?>