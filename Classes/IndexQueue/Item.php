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
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Representation of an index queue item, carrying metadata and the record to be
 * indexed.
 *
 * @todo: Loose coupling from Repos
 */
class Item implements ItemInterface, MountPointAwareItemInterface
{
    /**
     * The item's uid in the index queue (tx_solr_indexqueue_item.uid)
     */
    protected int $indexQueueUid;

    /**
     * The root page uid of the tree the item is located in (tx_solr_indexqueue_item.root)
     */
    protected int $rootPageUid;

    /**
     * The record's type, usually a table name, but could also be a file type (tx_solr_indexqueue_item.item_type)
     */
    protected string $type;

    /**
     * The name of the indexing configuration that should be used when indexing (tx_solr_indexqueue_item.indexing_configuration)
     * the item.
     */
    protected string $indexingConfigurationName;

    /**
     * The unix timestamp when the record was last changed (tx_solr_indexqueue_item.changed)
     */
    protected int $changed;

    /**
     * The unix timestamp when the record was last indexed (tx_solr_indexqueue_item.indexed)
     */
    protected int $indexed;

    /**
     * Indexing properties to provide additional information for the item's
     * indexer / how to index the item.
     */
    protected array $indexingProperties = [];

    /**
     * Flag for lazy loading indexing properties.
     */
    protected bool $indexingPropertiesLoaded = false;

    /**
     * Flag, whether indexing properties exits for this item.
     */
    protected bool $hasIndexingProperties = false;

    /**
     * The record's uid.
     */
    protected int $recordUid;

    /**
     * The indexing priority
     *
     * @var int
     */
    protected int $indexingPriority = 0;

    /**
     * The record itself
     */
    protected ?array $record = null;

    /**
     * Mount point identifier.
     */
    protected string $mountPointIdentifier;

    /**
     * The Items errors
     */
    protected string $errors = '';

    protected IndexQueueIndexingPropertyRepository $indexQueueIndexingPropertyRepository;

    protected QueueItemRepository $queueItemRepository;

    /**
     * Constructor, takes item metadata information and resolves that to the full record.
     *
     * @param array $itemMetaData Metadata describing the item to index using the index queue. Is expected to contain a record from table tx_solr_indexqueue_item
     * @param array $fullRecord Optional full record for the item. If provided, can save some SQL queries.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        array $itemMetaData,
        array $fullRecord = [],
        ?IndexQueueIndexingPropertyRepository $indexQueueIndexingPropertyRepository = null,
        ?QueueItemRepository $queueItemRepository = null,
    ) {
        if (!isset($itemMetaData['uid']) || (int)$itemMetaData['uid'] <= 0) {
            throw new InvalidArgumentException(
                'Index-Queue-Item must contain "uid" key with value > 0.',
                1770719565,
            );
        }
        if (!isset($itemMetaData['root']) || (int)$itemMetaData['root'] <= 0) {
            throw new InvalidArgumentException(
                'Index-Queue-Item with UID: ' . $itemMetaData['uid'] . ' must contain "root" key with value > 0.',
                1770719566,
            );
        }
        if (!isset($itemMetaData['item_type']) || trim($itemMetaData['item_type']) === '') {
            throw new InvalidArgumentException(
                'Index-Queue-Item with UID: ' . $itemMetaData['uid'] . ' must contain "item_type" key with non-empty value.',
                1770719567,
            );
        }
        if (!isset($itemMetaData['item_uid']) || (int)$itemMetaData['item_uid'] <= 0) {
            throw new InvalidArgumentException(
                'Index-Queue-Item with UID: ' . $itemMetaData['uid'] . ' must contain "item_uid" key with value > 0.',
                1770719568,
            );
        }
        if (!isset($itemMetaData['changed']) || (int)$itemMetaData['changed'] <= 0) {
            throw new InvalidArgumentException(
                'Index-Queue-Item with UID: ' . $itemMetaData['uid'] . ' must contain "changed" key with value > 0.',
                1770719569,
            );
        }
        $this->indexQueueUid = (int)$itemMetaData['uid'];
        $this->rootPageUid = (int)$itemMetaData['root'];
        $this->type = $itemMetaData['item_type'];
        $this->recordUid = (int)$itemMetaData['item_uid'];
        $this->mountPointIdentifier = (string)empty($itemMetaData['pages_mountidentifier']) ? '' : $itemMetaData['pages_mountidentifier'];
        $this->changed = (int)$itemMetaData['changed'];
        $this->indexed = (int)($itemMetaData['indexed'] ?? 0);
        $this->errors = (string)empty($itemMetaData['errors']) ? '' : $itemMetaData['errors'];

        $this->indexingConfigurationName = $itemMetaData['indexing_configuration'] ?? '';
        $this->hasIndexingProperties = (bool)($itemMetaData['has_indexing_properties'] ?? false);
        $this->indexingPriority = (int)($itemMetaData['indexing_priority'] ?? 0);

        if (!empty($fullRecord)) {
            $this->record = $fullRecord;
        }

        $this->indexQueueIndexingPropertyRepository = $indexQueueIndexingPropertyRepository ?? GeneralUtility::makeInstance(IndexQueueIndexingPropertyRepository::class);
        $this->queueItemRepository = $queueItemRepository ?? GeneralUtility::makeInstance(QueueItemRepository::class);
    }

    /**
     * Getter for Index Queue UID
     */
    public function getIndexQueueUid(): int
    {
        return $this->indexQueueUid;
    }

    /**
     * Gets the item's root page ID (uid)
     */
    public function getRootPageUid(): int
    {
        return $this->rootPageUid;
    }

    /**
     * Returns mount point identifier
     */
    public function getMountPointIdentifier(): string
    {
        return $this->mountPointIdentifier;
    }

    public function setRootPageUid(int $uid): void
    {
        $this->rootPageUid = $uid;
    }

    public function getErrors(): string
    {
        return $this->errors;
    }

    public function getHasErrors(): bool
    {
        return trim($this->errors) !== '';
    }

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
     * @throws DBALException
     * @throws InvalidArgumentException
     */
    public function getSite(): ?Site
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        return $siteRepository->getSiteByRootPageId($this->rootPageUid);
    }

    /**
     * Returns the type/tablename of the queue record.
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Returns the name of the index configuration that was used to create this record.
     */
    public function getIndexingConfigurationName(): string
    {
        return $this->indexingConfigurationName;
    }

    public function setIndexingConfigurationName(string $indexingConfigurationName): void
    {
        $this->indexingConfigurationName = $indexingConfigurationName;
    }

    /**
     * Returns the timestamp when this queue item was changed.
     */
    public function getChanged(): int
    {
        return $this->changed;
    }

    /**
     * Returns the timestamp when this queue item was indexed.
     */
    public function getIndexed(): int
    {
        return $this->indexed;
    }

    /**
     * Used to set the timestamp when the related item was changed.
     */
    public function setChanged(int $changed): void
    {
        $this->changed = $changed;
    }

    /**
     * Returns the uid of related record (item_uid).
     */
    public function getRecordUid(): int
    {
        $this->getRecord();

        return (int)$this->record['uid'];
    }

    /**
     * Gets the item's full record.
     * Uses lazy loading.
     */
    public function getRecord(): ?array
    {
        if (empty($this->record)) {
            $this->record = BackendUtility::getRecord(
                $this->type,
                $this->recordUid,
                '*',
                '',
                false,
            );
        }

        return $this->record;
    }

    /**
     * Can be used to set the item's full record.
     */
    public function setRecord(array $record): void
    {
        $this->record = $record;
    }

    /**
     * Retrieves the page id where the related record is stored.
     */
    public function getRecordPageId(): ?int
    {
        if ($this->getRecord() === null) {
            return null;
        }
        return $this->record['pid'];
    }

    /**
     * Stores the indexing properties.
     */
    public function storeIndexingProperties(): void
    {
        $this->indexQueueIndexingPropertyRepository->removeByRootPidAndIndexQueueUid($this->rootPageUid, $this->indexQueueUid);

        if ($this->hasIndexingProperties()) {
            $this->writeIndexingProperties();
        }

        $this->queueItemRepository->updateHasIndexingPropertiesFlagByItemUid($this->indexQueueUid, $this->hasIndexingProperties);
    }

    /**
     * Checks whether item has indexing properties.
     */
    public function hasIndexingProperties(): bool
    {
        return $this->hasIndexingProperties;
    }

    /**
     * Writes all indexing properties.
     */
    protected function writeIndexingProperties(): void
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
     * Check whether property name exists on indexing properties.
     *
     * @throws DBALException
     */
    public function hasIndexingProperty(string $propertyName): bool
    {
        $this->loadIndexingProperties();

        return array_key_exists($propertyName, $this->indexingProperties);
    }

    /**
     * Loads the indexing properties for the item - if not already loaded.
     *
     * @throws DBALException
     */
    public function loadIndexingProperties(): void
    {
        if ($this->indexingPropertiesLoaded) {
            return;
        }

        $indexingProperties = $this->indexQueueIndexingPropertyRepository->findAllByIndexQueueUid($this->indexQueueUid);
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
     * @throws DBALException
     */
    public function setIndexingProperty(string $propertyName, string|int|float $value): void
    {
        // make sure to not interfere with existing indexing properties
        $this->loadIndexingProperties();

        $this->indexingProperties[$propertyName] = $value;
        $this->hasIndexingProperties = true;
    }

    /**
     * Gets a specific indexing property by its name.
     *
     * @throws InvalidArgumentException when the given $propertyName does not exist.
     * @throws DBALException
     */
    public function getIndexingProperty(string $propertyName): string
    {
        $this->loadIndexingProperties();

        if (!array_key_exists($propertyName, $this->indexingProperties)) {
            throw new InvalidArgumentException(
                'No indexing property "' . $propertyName . '".',
                1323174143,
            );
        }

        return $this->indexingProperties[$propertyName];
    }

    /**
     * Gets all indexing properties set for this item.
     *
     * @throws DBALException
     */
    public function getIndexingProperties(): array
    {
        $this->loadIndexingProperties();

        return $this->indexingProperties;
    }

    /**
     * Gets the names of the item's indexing properties.
     *
     * @throws DBALException
     */
    public function getIndexingPropertyNames(): array
    {
        $this->loadIndexingProperties();
        return array_keys($this->indexingProperties);
    }

    /**
     * Returns the index priority.
     */
    public function getIndexPriority(): int
    {
        return $this->indexingPriority;
    }
}
