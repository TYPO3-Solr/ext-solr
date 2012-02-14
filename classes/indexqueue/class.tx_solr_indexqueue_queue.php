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


/**
 * The Indexing Queue. It allows us to decouple from frontend indexing and
 * reacting to changes faster.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_Queue {


		// FIXME some of the methods should be renamed to plural forms
		// FIXME singular form methods should deal with exactly one item only


	/**
	 * Returns the timestamp of the last indexing run.
	 *
	 * @param	integer	The root page uid for which to get the last indexed item id
	 * @return	integer	Timestamp of last index run.
	 */
	public function getLastIndexTime($rootPageId) {
		$lastIndexTime = 0;

		$lastIndexedRow = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'indexed',
			'tx_solr_indexqueue_item',
			'root = ' . (int) $rootPageId,
			'',
			'indexed DESC',
			1
		);

		if ($lastIndexedRow[0]['indexed']) {
			$lastIndexTime = $lastIndexedRow[0]['indexed'];
		}

		return $lastIndexTime;
	}

	/**
	 * Returns the uid of the last indexed item in the queue
	 *
	 * @param	integer	The root page uid for which to get the last indexed item id
	 * @return	integer	The last indexed item's ID.
	 */
	public function getLastIndexedItemId($rootPageId) {
		$lastIndexedItemId = 0;

		$lastIndexedItemRow = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid',
			'tx_solr_indexqueue_item',
			'root = ' . (int) $rootPageId,
			'',
			'indexed DESC',
			1
		);
		if ($lastIndexedItemRow[0]['uid']) {
			$lastIndexedItemId = $lastIndexedItemRow[0]['uid'];
		}

		return $lastIndexedItemId;
	}

	/**
	 * Truncate and rebuild the tx_solr_indexqueue_item table. This is the most
	 * complete way to force reindexing, or to build the indexing table for
	 * the first time. The Index Queue initialization is site-specific.
	 *
	 * @param tx_solr_Site $site The site to initialize
	 * @return array An array of booleans, each representing whether the initialization for an indexing configuration was successful
	 */
	public function initialize(tx_solr_Site $site) {
		$initializationStatus = array();

			// clear queue
		$this->deleteItemsBySite($site);

		$solrConfiguration      = $site->getSolrConfiguration();
		$indexingConfigurations = $this->getTableIndexingConfigurations($solrConfiguration);

		foreach ($indexingConfigurations as $indexingConfigurationName) {
			$tableToIndex     = $this->resolveTableToIndex($solrConfiguration, $indexingConfigurationName);
			$initializerClass = $this->resolveInitializerClass($solrConfiguration, $indexingConfigurationName);

			$initializer = t3lib_div::makeInstance($initializerClass);
			$initializer->setSite($site);
			$initializer->setType($tableToIndex);
			$initializer->setIndexingConfigurationName($indexingConfigurationName);
			$initializer->setIndexingConfiguration($solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']);

			$initializationStatus[$indexingConfigurationName] = $initializer->initialize();
		}

		return $initializationStatus;
	}

	/**
	 * Gets the the name of the table to index.
	 *
	 * Usually the indexing configuration's name implicitly reflects the name of
	 * the tbale to index. However, this way it would not be possible to index
	 * the same table with different indexing configurations. Therefore it is
	 * possible to explicitly define the actual table name using the indexing
	 * configuration's "table" property.
	 *
	 * @param array $solrConfiguration Solr TypoScript configuration
	 * @param string $indexingConfigurationName Indexing configuration name
	 * @return string Name of the table to index
	 */
	protected function resolveTableToIndex($solrConfiguration, $indexingConfigurationName) {
		$tableToIndex = $indexingConfigurationName;

		if (!empty($solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'])) {
			// table has been set explicitly. Allows to index the same table with different configurations
			$tableToIndex = $solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'];
		}

		return $tableToIndex;
	}

	/**
	 * Gets the class name of the initializer class.
	 *
	 * For most cases the default initializer
	 * "tx_solr_indexqueue_initializer_Record" will be enough. For special cases
	 * like pages we need to do some more work though. In the case of pages we
	 * also need to take care of resolving mount pages and their mounted sub
	 * trees for example. For these cases it is possible to define a initializer
	 * class using the indexing configuration's "initialization" property.
	 *
	 * @param array $solrConfiguration Solr TypoScript configuration
	 * @param string $indexingConfigurationName Indexing configuration name
	 * @return string Name of the initializer class
	 */
	protected function resolveInitializerClass($solrConfiguration, $indexingConfigurationName) {
		$initializerClass = 'tx_solr_indexqueue_initializer_Record';

		if (!empty($solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['initialization'])) {
			$initializerClass = $solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['initialization'];
		}

		return $initializerClass;
	}

	/**
	 * Determines which tables to index according to the given configuration.
	 *
	 * @param	array	Solr configuration array.
	 * @return	array	An array of table names to index.
	 */
	public function getTableIndexingConfigurations(array $solrConfiguration) {
		$tablesToIndex = array();

		if (is_array($solrConfiguration['index.']['queue.'])) {
			foreach ($solrConfiguration['index.']['queue.'] as $tableName => $enableIndexing) {
				if (substr($tableName, -1) != '.' && $enableIndexing) {
					$tablesToIndex[] = $tableName;
				}
			}
		}

		return $tablesToIndex;
	}

	/**
	 * Gets the indexing configuration to use for an item.
	 * Sometimes, when there are multiple configurations for a certain item type
	 * (table) it can be hard or even impossible to find which one to use
	 * though.
	 * Currently selects the first indexing configuration where the name matches
	 * the itemType or where the configured tbale is the same as the itemType.
	 *
	 * !!! Might return incorrect results for complex configurations !!!
	 * Try to set the indexingConfiguration directly when using the updateItem()
	 * method in such situations.
	 *
	 * @param	string	The item's type, usually a table name.
	 * @param	string	The item's uid, usually an integer uid, could be a different value for non-database-record types.
	 * @param	integer	The configuration's page tree's root page id. Optional, not needed for all types.
	 * @return	string	The indexing configuration's name to use when indexing this item
	 */
	protected function getIndexingConfigurationByItem($itemType, $itemUid, $rootPageId = NULL) {
		$possibleIndexingConfigurationName = '';

		if (!is_null($rootPageId)) {
				// get configuration for the root's branch
			$solrConfiguration = tx_solr_Util::getSolrConfigurationFromPageId($rootPageId);
				// which configurations are there?
			$indexingConfigurations = $this->getTableIndexingConfigurations($solrConfiguration);

			foreach ($indexingConfigurations as $indexingConfigurationName) {
				if ($indexingConfigurationName == $itemType
					||
					(
						!empty($solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'])
						&&
						$solrConfiguration['index.']['queue.'][$indexingConfigurationName . '.']['table'] == $itemType
					)
				) {
					$possibleIndexingConfigurationName = $indexingConfigurationName;
					break;
				}
			}

		}

		return $possibleIndexingConfigurationName;
	}

	/**
	 * Generates a list of page IDs from a starting page ID. The list does not
	 * include the start page ID itself. The only pages excluded from the list
	 * are deleted pages.
	 *
	 * Should be used for Index Queue initialization only, thus private
	 *
	 * @param	integer		Start page id
	 * @param	integer		Maximum depth to decend into the tree
	 * @return	string		Returns the list with a comma in the end (if any pages selected!)
	 */
	private function getListOfPagesFromRoot($startPageId, $maxDepth = 999) {
		$pageList    = array();
		$startPageId = intval($startPageId);
		$maxDepth    = intval($maxDepth);

		if ($maxDepth > 0) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'uid',
				'pages',
				'pid = ' . $startPageId . ' ' . t3lib_BEfunc::deleteClause('pages')
			);

			while ($page = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$pageList[] = $page['uid'];

				if ($maxDepth > 1) {
					$pageList = array_merge(
						$pageList,
						$this->getListOfPagesFromRoot($page['uid'], $maxDepth - 1)
					);
				}
			}
		}

		return $pageList;
	}

	/**
	 * Marks an item as needing (re)indexing.
	 *
	 * Like with Solr itself, there's no add method, just a simple update method
	 * that handles the adds, too.
	 *
	 * @param	string	The item's type, usually a table name.
	 * @param	string	The item's uid, usually an integer uid, could be a different value for non-database-record types.
	 * @param	string	The item's indexing configuration to use. Optional, overwrites existing / determined configuration.
	 */
	public function updateItem($itemType, $itemUid, $indexingConfiguration = NULL) {
		$itemInQueue = $this->containsItem($itemType, $itemUid);

		if ($itemInQueue) {
				// update if that item is in the queue already
			$changes = array('changed' => time());

			if (!empty($indexingConfiguration)) {
				$changes['indexing_configuration'] = $indexingConfiguration;
			}

			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'tx_solr_indexqueue_item',
				'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType, 'tx_solr_indexqueue_item')
					. ' AND item_uid = ' . (int) $itemUid ,
				$changes
			);
		} else {
				// add the item since it's not in the queue yet
			$this->addItem($itemType, $itemUid, $indexingConfiguration);
		}
	}

	/**
	 * Adds an item to the index queue.
	 *
	 * Not meant for public use.
	 *
	 * @param	string	The item's type, usually a table name.
	 * @param	string	The item's uid, usually an integer uid, could be a different value for non-database-record types.
	 * @param	string	The item's indexing configuration to use. Optional, overwrites existing / determined configuration.
	 */
	private function addItem($itemType, $itemUid, $indexingConfiguration) {
		// FIXME must respect the indexer's additionalWhereClause option:
		// must not add items to the index queue which are excluded through additionalWhereClause
		// requires construction of additionalWhereClause through multiple options instead of just one

			# temporary until we have a query builder to take care of this
		$additionalRecordFields = '';
		if ($itemType == 'pages') {
			$additionalRecordFields = ', doktype';
		}

		$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
			'pid' . $additionalRecordFields,
			$itemType,
			'uid = ' . intval($itemUid) . t3lib_BEfunc::deleteClause($itemType)
		);

			# temporary until we have a query builder to take care of this
		if ($itemType == 'pages' && !$this->isAllowedPageType($record)) {
			return;
		}

		if ($record && $record['pid'] != 0) {
			$rootPageId = tx_solr_Util::getRootPageId($record['pid']);

			$item = array(
				'root'      => $rootPageId,
				'item_type' => $itemType,
				'item_uid'  => $itemUid,
				'changed'   => time()
			);

			if (!empty($indexingConfiguration)) {
				$item['indexing_configuration'] = $indexingConfiguration;
			} else {
					// best guess
				$item['indexing_configuration'] = $this->getIndexingConfigurationByItem(
					$itemType, $itemUid, $rootPageId
				);
			}

			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'tx_solr_indexqueue_item',
				$item
			);
		}
	}

	/**
	 * Checks whether the Index Queue contains a specific item.
	 *
	 * @param	string	The item's type, usually a table name.
	 * @param	string	The item's uid, usually an integer uid, could be a different value for non-database-record types.
	 */
	public function containsItem($itemType, $itemUid) {
		$itemIsInQueue = (boolean) $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
			'uid',
			'tx_solr_indexqueue_item',
			'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType, 'tx_solr_indexqueue_item')
				. ' AND '
				. 'item_uid = ' . (int) $itemUid
		);

		return $itemIsInQueue;
	}

	/**
	 * Removes an item from the Index Queue.
	 *
	 * @param	string	The type of the item to remove, usually a table name.
	 * @param	integer	The uid of the item to remove
	 */
	public function deleteItem($itemType, $itemUid) {
		$uidList = array();

			// get the item uids to use them in the deletes afterwards
		$items = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid',
			'tx_solr_indexqueue_item',
			'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType, 'tx_solr_indexqueue_item')
				. ' AND item_uid = ' . intval($itemUid)
		);
		foreach ($items as $item) {
			$uidList[] = $item['uid'];
		}

		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_solr_indexqueue_item',
			'uid IN(' . implode(',', $uidList) . ')'
		);
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_solr_indexqueue_indexing_property',
			'item_id IN(' . implode(',', $uidList) . ')'
		);

	}

	/**
	 * Removes all items of a certain type from the Index Queue.
	 *
	 * @param	string	The type of items to remove, usually a table name.
	 */
	public function deleteItemsByType($itemType) {
		$uidList = array();

			// get the item uids to use them in the deletes afterwards
		$items = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid',
			'tx_solr_indexqueue_item',
			'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr(
				$itemType,
				'tx_solr_indexqueue_item'
			)
		);
		foreach ($items as $item) {
			$uidList[] = $item['uid'];
		}

		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_solr_indexqueue_item',
			'uid IN(' . implode(',', $uidList) . ')'
		);
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_solr_indexqueue_indexing_property',
			'item_id IN(' . implode(',', $uidList) . ')'
		);
	}

	/**
	 * Removes all items of a certain site from the Index Queue.
	 *
	 * @param	tx_solr_Site	$site The site to remove items for.
	 */
	public function deleteItemsBySite(tx_solr_Site $site) {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_solr_indexqueue_item',
			'root = ' . $site->getRootPageId()
		);

		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_solr_indexqueue_indexing_property',
			'root = ' . $site->getRootPageId()
		);
	}

	/**
	 * Removes all items from the Index Queue.
	 *
	 */
	public function deleteAllItems() {
		$GLOBALS['TYPO3_DB']->exec_TRUNCATEquery('tx_solr_indexqueue_item', '');
	}

	/**
	 * Gets Index Queue items by type and uid.
	 *
	 * @param string $itemType item type, ususally  the table name
	 * @param integer $itemUid item uid
	 * @return array An array of items matching $itemType and $itemUid
	 */
	public function getItems($itemType, $itemUid) {
		$indexQueueItemRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_solr_indexqueue_item',
			'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType, 'tx_solr_indexqueue_item')
				. ' AND item_uid = ' . intval($itemUid)
		);

		return $this->getIndexQueueItemObjectsFromRecords($indexQueueItemRecords);
	}

	/**
	 * Gets a single Index Queue item by its uid.
	 *
	 * @param integer $itemId Index Queue item uid
	 * @return tx_solr_indexqueue_Item The request Index Queue item or NULL if no item with $itemId was found
	 */
	public function getItem($itemId) {
		$item = NULL;

		$indexQueueItemRecord = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_solr_indexqueue_item',
			'uid = ' . intval($itemId)
		);

		if (count($indexQueueItemRecord) == 1) {
			$indexQueueItemRecord = $indexQueueItemRecord[0];

			$item = t3lib_div::makeInstance(
				'tx_solr_indexqueue_Item',
				$indexQueueItemRecord
			);
		}

		return $item;
	}

	/**
	 * Gets Index Queue items by type and uid.
	 *
	 * @param string $itemType item type, ususally  the table name
	 * @param integer $itemUid item uid
	 * @return array An array of items matching $itemType and $itemUid
	 */
	public function getItems($itemType, $itemUid) {
		$indexQueueItemRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_solr_indexqueue_item',
			'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType, 'tx_solr_indexqueue_item')
				. ' AND item_uid = ' . intval($itemUid)
		);

		return $this->getIndexQueueItemObjectsFromRecords($indexQueueItemRecords);
	}

	/**
	 * Gets $limit number of items to index for a particular $site.
	 *
	 * @param	tx_solr_Site	$site TYPO3 site
	 * @param	integer	Number of items to get from the queue
	 * @return	array	Array of tx_solr_indexqueue_Item objects to index to the given solr server
	 */
	public function getItemsToIndex(tx_solr_Site $site, $limit = 50) {
		$itemsToIndex = array();

			// determine which items to index with this run
		$indexQueueItemRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_solr_indexqueue_item',
			'root = ' . $site->getRootPageId()
				. ' AND changed > indexed',
			'',
			'changed DESC, uid DESC',
			intval($limit)
		);

			// convert queued records to index queue item objects
		$itemsToIndex = $this->getIndexQueueItemObjectsFromRecords($indexQueueItemRecords);

		return $itemsToIndex;
	}

	/**
	 * Creates an array of tx_solr_indexqueue_Item objects from an array of index queue records.
	 *
	 * @param	array	Array of plain index queue records
	 * @return	array	Array of tx_solr_indexqueue_Item objects
	 */
	protected function getIndexQueueItemObjectsFromRecords(array $indexQueueItemRecords) {
		$indexQueueItems = array();
		$tableUids       = array();
		$tableRecords    = array();

			// grouping records by table
		foreach ($indexQueueItemRecords as $indexQueueItemRecord) {
			$tableUids[$indexQueueItemRecord['item_type']][] = $indexQueueItemRecord['item_uid'];
		}

			// fetching records by table, saves us a lot of single queries
		foreach ($tableUids as $table => $uids) {
			$uidList = implode(',', $uids);
			$records = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'*',
				$table,
				'uid IN(' . $uidList . ')',
				'', '', '', // group, order, limit
				'uid'
			);
			$tableRecords[$table] = $records;
		}

			// creating index queue item objects and assigning / mapping records to index queue items
		foreach ($indexQueueItemRecords as $indexQueueItemRecord) {
			$indexQueueItems[] = t3lib_div::makeInstance(
				'tx_solr_indexqueue_Item',
				$indexQueueItemRecord,
				$tableRecords[$indexQueueItemRecord['item_type']][$indexQueueItemRecord['item_uid']]
			);
		}

		return $indexQueueItems;
	}

	// temporary

	private function isAllowedPageType(array $pageRecord) {
		$isAllowedPageType = FALSE;
		$allowedPageTypes  = array(1, 7);

		if (in_array($pageRecord['doktype'], $allowedPageTypes)) {
			$isAllowedPageType = TRUE;
		}

		return $isAllowedPageType;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_queue.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_queue.php']);
}

?>