<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo@typo3.org>
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
 * Representation of an index queue item, carying meta data and the record to be
 * indexed.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_Item {

	/**
	 * The item's uid in the index queue (tx_solr_indexqueue_item.uid)
	 *
	 * @var	integer
	 */
	protected $indexQueueUid;

	/**
	 * The root page uid of the tree the item is located in (tx_solr_indexqueue_item.root)
	 *
	 * @var	integer
	 */
	protected $rootPageUid;

	/**
	 * The record's type, usually a table name, but could also be a file type (tx_solr_indexqueue_item.item_type)
	 *
	 * @var	string
	 */
	protected $type;

	/**
	 * The name of the indexing configuration that should be used when indexing (tx_solr_indexqueue_item.indexing_configuration)
	 * the item.
	 *
	 * @var	string
	 */
	protected $indexingConfigurationName;

	/**
	 * The unix timestamp when the record was last changed (tx_solr_indexqueue_item.changed)
	 *
	 * @var	integer
	 */
	protected $changed;

	/**
	 * The record itself
	 *
	 * @var	array
	 */
	protected $record;


	/**
	 * Constructor for class tx_solr_indexqueue_Item, takes item meta data
	 * information and resolves that to the full record.
	 *
	 * @param	array	Metadata describing the item to index using the index queue. Is expected to contain a record from table tx_solr_indexqueue_item
	 * @param	array	Optional full record for the item. If provided, can save some SQL queries.
	 */
	public function __construct(array $itemMetaData, array $fullRecord = array()) {
		$this->indexQueueUid = $itemMetaData['uid'];
		$this->rootPageUid   = $itemMetaData['root'];
		$this->type          = $itemMetaData['item_type'];
		$this->indexingConfigurationName = $itemMetaData['indexing_configuration'];
		$this->changed       = $itemMetaData['changed'];

		if (!empty($fullRecord)) {
			$this->record = $fullRecord;
		} else {
			$this->record = t3lib_BEfunc::getRecord(
				$this->type,
				$this->uid,
				'*',
				'',
				FALSE
			);
		}
	}

	public function getIndexQueueUid() {
		return $this->indexQueueUid;
	}

	/**
	 * Gets the item's root page ID (uid)
	 *
	 * @return	integer	root page ID
	 */
	public function getRootPageUid() {
		return $this->rootPageUid;
	}

	public function setRootPageUid($uid) {
		$this->rootPageUid = intval($uid);
	}

	public function getType() {
		return $this->type;
	}

	public function setType($type) {
		$this->type = $type;
	}

	public function getIndexingConfigurationName() {
		return $this->indexingConfigurationName;
	}

	public function setIndexingConfigurationName($indexingConfigurationName) {
		$this->indexingConfigurationName = $indexingConfigurationName;
	}

	public function getChanged() {
		return $this->changed;
	}

	public function setChanged($changed) {
		$this->changed = intval($changed);
	}

	/**
	 * Sets the timestamp of when an item has been indexed.
	 *
	 * @return	void
	 */
	public function updateIndexedTime() {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			'tx_solr_indexqueue_item',
			'uid = ' . (int) $this->indexQueueUid,
			array('indexed' => time())
		);
	}

	/**
	 * Gets the item's full record.
	 *
	 * @return	array	The item's DB record.
	 */
	public function getRecord() {
		return $this->record;
	}

	public function setRecord(array $record) {
		$this->record = $record;
	}

	public function getRecordUid() {
		return $this->record['uid'];
	}

	public function getRecordPageId() {
		return $this->record['pid'];
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_item.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_item.php']);
}

?>