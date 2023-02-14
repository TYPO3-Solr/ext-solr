<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\IndexQueueIndexingPropertyRepository;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use InvalidArgumentException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Representation of an index queue item, carrying metadata and the record to be
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
     * @var int|null
     */
    protected ?int $indexQueueUid = null;

    /**
     * The root page uid of the tree the item is located in (tx_solr_indexqueue_item.root)
     *
     * @var int|null
     */
    protected ?int $rootPageUid;

    /**
     * The record's type, usually a table name, but could also be a file type (tx_solr_indexqueue_item.item_type)
     *
     * @var string|null
     */
    protected ?string $type;

    /**
     * The name of the indexing configuration that should be used when indexing (tx_solr_indexqueue_item.indexing_configuration)
     * the item.
     *
     * @var string
     */
    protected string $indexingConfigurationName;

    /**
     * The unix timestamp when the record was last changed (tx_solr_indexqueue_item.changed)
     *
     * @var int|null
     */
    protected ?int $changed = null;

    /**
     * The unix timestamp when the record was last indexed (tx_solr_indexqueue_item.indexed)
     *
     * @var int|null
     */
    protected ?int $indexed = null;

    /**
     * Indexing properties to provide additional information for the item's
     * indexer / how to index the item.
     *
     * @var array
     */
    protected array $indexingProperties = [];

    /**
     * Flag for lazy loading indexing properties.
     *
     * @var bool
     */
    protected bool $indexingPropertiesLoaded = false;

    /**
     * Flag, whether indexing properties exits for this item.
     *
     * @var bool
     */
    protected bool $hasIndexingProperties = false;

    /**
     * The record's uid.
     *
     * @var int|null
     */
    protected ?int $recordUid = null;

    /**
     * The record itself
     *
     * @var array
     */
    protected array $record;

    /**
     * Mount point identifier.
     *
     * @var string|null
     */
    protected ?string $mountPointIdentifier = null;

    /**
     * @var string
     */
    protected string $errors = '';

    /**
     * @var IndexQueueIndexingPropertyRepository
     */
    protected IndexQueueIndexingPropertyRepository $indexQueueIndexingPropertyRepository;

    /**
     * @var QueueItemRepository
     */
    protected QueueItemRepository $queueItemRepository;

    /**
     * Constructor, takes item metadata information and resolves that to the full record.
     *
     * @param array $itemMetaData Metadata describing the item to index using the index queue. Is expected to contain a record from table tx_solr_indexqueue_item
     * @param array $fullRecord Optional full record for the item. If provided, can save some SQL queries.
     * @param IndexQueueIndexingPropertyRepository|null $indexQueueIndexingPropertyRepository
     * @param QueueItemRepository|null $queueItemRepository
     */
    public function __construct(
        array $itemMetaData,
        array $fullRecord = [],
        IndexQueueIndexingPropertyRepository $indexQueueIndexingPropertyRepository = null,
        QueueItemRepository $queueItemRepository = null
    ) {
        $this->indexQueueUid = $itemMetaData['uid'] ?? null;
        $this->rootPageUid = $itemMetaData['root'] ?? null;
        $this->type = $itemMetaData['item_type'] ?? null;
        $this->recordUid = $itemMetaData['item_uid'] ?? null;
        $this->mountPointIdentifier = (string)empty($itemMetaData['pages_mountidentifier']) ? '' : $itemMetaData['pages_mountidentifier'];
        $this->changed = $itemMetaData['changed'] ?? null;
        $this->indexed = $itemMetaData['indexed'] ?? null;
        $this->errors = (string)empty($itemMetaData['errors']) ? '' : $itemMetaData['errors'];

        $this->indexingConfigurationName = $itemMetaData['indexing_configuration'] ?? '';
        $this->hasIndexingProperties = (boolean)($itemMetaData['has_indexing_properties'] ?? false);

        if (!empty($fullRecord)) {
            $this->record = $fullRecord;
        }

        $this->indexQueueIndexingPropertyRepository = $indexQueueIndexingPropertyRepository ?? GeneralUtility::makeInstance(IndexQueueIndexingPropertyRepository::class);
        $this->queueItemRepository = $queueItemRepository ?? GeneralUtility::makeInstance(QueueItemRepository::class);
    }

    /**
     * Getter for Index Queue UID
     *
     * @return int
     */
    public function getIndexQueueUid(): ?int
    {
        return $this->indexQueueUid;
    }

    /**
     * Gets the item's root page ID (uid)
     *
     * @return int|null root page ID
     */
    public function getRootPageUid(): ?int
    {
        return $this->rootPageUid;
    }

    /**
     * Returns mount point identifier
     *
     * @return string
     */
    public function getMountPointIdentifier(): ?string
    {
        return $this->mountPointIdentifier;
    }

    /**
     * @param int $uid
     */
    public function setRootPageUid(int $uid)
    {
        $this->rootPageUid = $uid;
    }

    /**
     * @return string
     */
    public function getErrors(): string
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function getHasErrors(): bool
    {
        return trim($this->errors) !== '';
    }

    /**
     * @return int
     */
    public function getState(): int
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
     * @return Site|null Site instance the item belongs to.
     * @throws DBALDriverException
     */
    public function getSite(): ?Site
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
    public function setIndexingConfigurationName(string $indexingConfigurationName)
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
    public function setChanged(int $changed)
    {
        $this->changed = $changed;
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
    public function getRecord(): array
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
    public function getRecordPageId(): int
    {
        $this->getRecord();

        return $this->record['pid'];
    }

    /**
     * Stores the indexing properties.
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function storeIndexingProperties()
    {
        $this->indexQueueIndexingPropertyRepository->removeByRootPidAndIndexQueueUid((int)($this->rootPageUid), (int)($this->indexQueueUid));

        if ($this->hasIndexingProperties()) {
            $this->writeIndexingProperties();
        }

        $this->queueItemRepository->updateHasIndexingPropertiesFlagByItemUid($this->indexQueueUid, $this->hasIndexingProperties);
    }

    /**
     * @return bool
     */
    public function hasIndexingProperties(): bool
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
                'property_value' => $propertyValue,
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
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function hasIndexingProperty(string $key): bool
    {
        $this->loadIndexingProperties();

        return array_key_exists($key, $this->indexingProperties);
    }

    /**
     * Loads the indexing properties for the item - if not already loaded.
     *
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function loadIndexingProperties()
    {
        if ($this->indexingPropertiesLoaded) {
            return;
        }

        $indexingProperties = $this->indexQueueIndexingPropertyRepository->findAllByIndexQueueUid((int)($this->indexQueueUid));
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
     *
     * @throws InvalidArgumentException when $value is not string, integer or float
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function setIndexingProperty(string $key, $value)
    {
        // make sure to not interfere with existing indexing properties
        $this->loadIndexingProperties();

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException(
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
     * @return string
     *
     * @throws InvalidArgumentException when the given $key does not exist.
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function getIndexingProperty(string $key): string
    {
        $this->loadIndexingProperties();

        if (!array_key_exists($key, $this->indexingProperties)) {
            throw new InvalidArgumentException(
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
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function getIndexingProperties(): array
    {
        $this->loadIndexingProperties();

        return $this->indexingProperties;
    }

    /**
     * Gets the names/keys of the item's indexing properties.
     *
     * @return array Array of indexing property names/keys
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function getIndexingPropertyKeys(): array
    {
        $this->loadIndexingProperties();

        return array_keys($this->indexingProperties);
    }
}
