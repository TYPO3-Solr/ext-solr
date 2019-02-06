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
 *  the Free Software Foundation; either version 3 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\IndexQueueIndexingPropertyRepository;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Representation of an index queue item, carrying meta data and the record to be
 * indexed.
 *
 * @todo: Loose coupling from Repos
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Item
{
    const STATE_BLOCKED = -1;

    const STATE_PENDING = 0;

    const STATE_INDEXED = 1;

    /**
     * The item's uid in the index queue (tx_solr_indexqueue_item.uid)
     *
     * @var int
     */
    protected $indexQueueUid;

    /**
     * The root page uid of the tree the item is located in (tx_solr_indexqueue_item.root)
     *
     * @var int
     */
    protected $rootPageUid;

    /**
     * The record's type, usually a table name, but could also be a file type (tx_solr_indexqueue_item.item_type)
     *
     * @var string
     */
    protected $type;

    /**
     * The name of the indexing configuration that should be used when indexing (tx_solr_indexqueue_item.indexing_configuration)
     * the item.
     *
     * @var string
     */
    protected $indexingConfigurationName;

    /**
     * The unix timestamp when the record was last changed (tx_solr_indexqueue_item.changed)
     *
     * @var int
     */
    protected $changed;

    /**
     * The unix timestamp when the record was last indexed (tx_solr_indexqueue_item.indexed)
     *
     * @var int
     */
    protected $indexed;

    /**
     * Indexing properties to provide additional information for the item's
     * indexer / how to index the item.
     *
     * @var array
     */
    protected $indexingProperties = [];

    /**
     * Flag for lazy loading indexing properties.
     *
     * @var bool
     */
    protected $indexingPropertiesLoaded = false;

    /**
     * Flag, whether indexing properties exits for this item.
     *
     * @var bool
     */
    protected $hasIndexingProperties = false;

    /**
     * The record's uid.
     *
     * @var int
     */
    protected $recordUid = 0;

    /**
     * The record itself
     *
     * @var array
     */
    protected $record;

    /**
     * Moint point identifier.
     *
     * @var string
     */
    protected $mountPointIdentifier;

    /**
     * @var string
     */
    protected $errors = '';

    /**
     * @var IndexQueueIndexingPropertyRepository
     */
    protected $indexQueueIndexingPropertyRepository;

    /**
     * @var QueueItemRepository
     */
    protected $queueItemRepository;

    /**
     * Constructor, takes item meta data information and resolves that to the full record.
     *
     * @param array $itemMetaData Metadata describing the item to index using the index queue. Is expected to contain a record from table tx_solr_indexqueue_item
     * @param array $fullRecord Optional full record for the item. If provided, can save some SQL queries.
     * @param IndexQueueIndexingPropertyRepository|null $indexQueueIndexingPropertyRepository
     */
    public function __construct(array $itemMetaData, array $fullRecord = [], IndexQueueIndexingPropertyRepository $indexQueueIndexingPropertyRepository = null, QueueItemRepository $queueItemRepository = null)
    {
        $this->indexQueueUid = $itemMetaData['uid'];
        $this->rootPageUid = $itemMetaData['root'];
        $this->type = $itemMetaData['item_type'];
        $this->recordUid = $itemMetaData['item_uid'];
        $this->mountPointIdentifier = (string) empty($itemMetaData['pages_mountidentifier']) ? '' : $itemMetaData['pages_mountidentifier'];
        $this->changed = $itemMetaData['changed'];
        $this->indexed = $itemMetaData['indexed'];
        $this->errors = (string) empty($itemMetaData['errors']) ? '' : $itemMetaData['errors'];

        $this->indexingConfigurationName = $itemMetaData['indexing_configuration'];
        $this->hasIndexingProperties = (boolean)$itemMetaData['has_indexing_properties'];

        if (!empty($fullRecord)) {
            $this->record = $fullRecord;
        }

        $this->indexQueueIndexingPropertyRepository = $indexQueueIndexingPropertyRepository ?? GeneralUtility::makeInstance(IndexQueueIndexingPropertyRepository::class);
        $this->queueItemRepository = $queueItemRepository ?? GeneralUtility::makeInstance(QueueItemRepository::class);
    }

    /**
     * Getter for Index Queue UID
     *
     * @return integer
     */
    public function getIndexQueueUid()
    {
        return $this->indexQueueUid;
    }

    /**
     * Gets the item's root page ID (uid)
     *
     * @return int root page ID
     */
    public function getRootPageUid()
    {
        return $this->rootPageUid;
    }

    /**
     * Returns mount point identifier
     *
     * @return string
     */
    public function getMountPointIdentifier()
    {
        return $this->mountPointIdentifier;
    }

    /**
     * @param integer $uid
     */
    public function setRootPageUid($uid)
    {
        $this->rootPageUid = intval($uid);
    }

    /**
     * @return string
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return boolean
     */
    public function getHasErrors()
    {
        return trim($this->errors) !== '';
    }

    /**
     * @return int
     */
    public function getState()
    {
        if ($this->getHasErrors()) {
            return self::STATE_BLOCKED;
        }

        if ($this->getIndexed() > $this->getChanged()) {
            return self::STATE_INDEXED;
        }

        return self::STATE_PENDING;
    }

    /**
     * Gets the site the item belongs to.
     *
     * @return Site Site instance the item belongs to.
     */
    public function getSite()
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        return $siteRepository->getSiteByRootPageId($this->rootPageUid);
    }

    /**
     * Returns the type/tablename of the queue record.
     *
     * @return mixed|string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Returns the name of the index configuration that was used to create this record.
     *
     * @return mixed|string
     */
    public function getIndexingConfigurationName()
    {
        return $this->indexingConfigurationName;
    }

    /**
     * @param string $indexingConfigurationName
     */
    public function setIndexingConfigurationName($indexingConfigurationName)
    {
        $this->indexingConfigurationName = $indexingConfigurationName;
    }

    /**
     * Returns the timestamp when this queue item was changed.
     *
     * @return int|mixed
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * Returns the timestamp when this queue item was indexed.
     *
     * @return int|mixed
     */
    public function getIndexed()
    {
        return $this->indexed;
    }

    /**
     * Used to set the timestamp when the related item was changed.
     *
     * @param int $changed
     */
    public function setChanged($changed)
    {
        $this->changed = intval($changed);
    }

    /**
     * Returns the uid of related record (item_uid).
     *
     * @return mixed
     */
    public function getRecordUid()
    {
        $this->getRecord();

        return $this->record['uid'];
    }

    /**
     * Gets the item's full record.
     *
     * Uses lazy loading.
     *
     * @return array The item's DB record.
     */
    public function getRecord()
    {
        if (empty($this->record)) {
            $this->record = (array)BackendUtility::getRecord(
                $this->type,
                $this->recordUid,
                '*',
                '',
                false
            );
        }

        return $this->record;
    }

    /**
     * Can be used to set the related record.
     *
     * @param array $record
     */
    public function setRecord(array $record)
    {
        $this->record = $record;
    }

    /**
     * Retrieves the page id where the related record is stored.
     *
     * @return int
     */
    public function getRecordPageId()
    {
        $this->getRecord();

        return $this->record['pid'];
    }

    /**
     * Stores the indexing properties.
     *
     */
    public function storeIndexingProperties()
    {
        $this->indexQueueIndexingPropertyRepository->removeByRootPidAndIndexQueueUid(intval($this->rootPageUid), intval($this->indexQueueUid));

        if ($this->hasIndexingProperties()) {
            $this->writeIndexingProperties();
        }

        $this->queueItemRepository->updateHasIndexingPropertiesFlagByItemUid($this->indexQueueUid, $this->hasIndexingProperties);
    }

    /**
     * @return bool
     */
    public function hasIndexingProperties()
    {
        return $this->hasIndexingProperties;
    }

    /**
     * Writes all indexing properties.
     */
    protected function writeIndexingProperties()
    {
        $properties = [];
        foreach ($this->indexingProperties as $propertyKey => $propertyValue) {
            $properties[] = [
                'root' => $this->rootPageUid,
                'item_id' => $this->indexQueueUid,
                'property_key' => $propertyKey,
                'property_value' => $propertyValue
            ];
        }
        if (empty($properties)) {
            return;
        }
        $this->indexQueueIndexingPropertyRepository->bulkInsert($properties);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasIndexingProperty($key)
    {
        $this->loadIndexingProperties();

        return array_key_exists($key, $this->indexingProperties);
    }

    /**
     * Loads the indexing properties for the item - if not already loaded.
     */
    public function loadIndexingProperties()
    {
        if ($this->indexingPropertiesLoaded) {
            return;
        }

        $indexingProperties = $this->indexQueueIndexingPropertyRepository->findAllByIndexQueueUid(intval($this->indexQueueUid));
        $this->indexingPropertiesLoaded = true;
        if (empty($indexingProperties)) {
            return;
        }

        foreach ($indexingProperties as $indexingProperty) {
            $this->indexingProperties[$indexingProperty['property_key']] = $indexingProperty['property_value'];
        }
    }

    /**
     * Sets an indexing property for the item.
     *
     * @param string $key Indexing property name
     * @param string|int|float $value Indexing property value
     * @throws \InvalidArgumentException when $value is not string, integer or float
     */
    public function setIndexingProperty($key, $value)
    {
        // make sure to not interfere with existing indexing properties
        $this->loadIndexingProperties();

        $key = (string)$key; // Scalar typehints now!

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException(
                'Cannot set indexing property "' . $key
                . '", its value must be string, integer or float, '
                . 'type given was "' . gettype($value) . '"',
                1323173209
            );
        }

        $this->indexingProperties[$key] = $value;
        $this->hasIndexingProperties = true;
    }

    /**
     * Gets a specific indexing property by its name/key.
     *
     * @param string $key Indexing property name/key.
     * @throws \InvalidArgumentException when the given $key does not exist.
     * @return string
     */
    public function getIndexingProperty($key)
    {
        $this->loadIndexingProperties();

        if (!array_key_exists($key, $this->indexingProperties)) {
            throw new \InvalidArgumentException(
                'No indexing property "' . $key . '".',
                1323174143
            );
        }

        return $this->indexingProperties[$key];
    }

    /**
     * Gets all indexing properties set for this item.
     *
     * @return array Array of indexing properties.
     */
    public function getIndexingProperties()
    {
        $this->loadIndexingProperties();

        return $this->indexingProperties;
    }

    /**
     * Gets the names/keys of the item's indexing properties.
     *
     * @return array Array of indexing property names/keys
     */
    public function getIndexingPropertyKeys()
    {
        $this->loadIndexingProperties();

        return array_keys($this->indexingProperties);
    }
}
