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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Event\IndexQueue\AfterRecordsForIndexQueueItemsHaveBeenRetrievedEvent;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use Doctrine\DBAL\Exception as DBALException;
use PDO;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class QueueItemRepository
 * Handles all CRUD operations to tx_solr_indexqueue_item table
 */
class QueueItemRepository extends AbstractRepository
{
    protected string $table = 'tx_solr_indexqueue_item';

    protected SolrLogManager $logger;
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(SolrLogManager $logManager = null, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->logger = $logManager ?? GeneralUtility::makeInstance(
            SolrLogManager::class,
            __CLASS__
        );
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Fetches the last indexed row for given root page
     *
     * @throws DBALException
     */
    public function findLastIndexedRow(int $rootPageId): array
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select('uid', 'indexed')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('root', $rootPageId)
            )
            ->andWhere(
                $queryBuilder->expr()->neq('indexed', 0)
            )
            ->orderBy('indexed', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Finds errored item records for given site.
     *
     * @throws DBALException
     */
    public function findErrorsBySite(Site $site): array
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select('uid', 'item_type', 'item_uid', 'errors')
            ->from($this->table)
            ->andWhere(
                $queryBuilder->expr()->notLike('errors', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->eq('root', $site->getRootPageId())
            )->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Resets all the errors for all index queue items.
     */
    public function flushAllErrors(): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return $this->getPreparedFlushErrorQuery($queryBuilder)
            ->executeStatement();
    }

    /**
     * Flushes the errors for a single site.
     */
    public function flushErrorsBySite(Site $site): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return $this->getPreparedFlushErrorQuery($queryBuilder)
            ->andWhere(
                $queryBuilder->expr()->eq('root', $site->getRootPageId())
            )
            ->executeStatement();
    }

    /**
     * Flushes the error for a single item.
     */
    public function flushErrorByItem(Item $item): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return $this->getPreparedFlushErrorQuery($queryBuilder)
            ->andWhere(
                $queryBuilder->expr()->eq('uid', $item->getIndexQueueUid())
            )
            ->executeStatement();
    }

    /**
     * Initializes the QueryBuilder with a query the resets the error field for items that have an error.
     */
    private function getPreparedFlushErrorQuery(QueryBuilder $queryBuilder): QueryBuilder
    {
        return $queryBuilder
            ->update($this->table)
            ->set('errors', '')
            ->where(
                $queryBuilder->expr()->notLike('errors', $queryBuilder->createNamedParameter(''))
            );
    }

    /**
     * Updates an existing queue entry by $itemType $itemUid and $rootPageId.
     */
    public function updateExistingItemByItemTypeAndItemUidAndRootPageId(
        string $itemType,
        int $itemUid,
        int $rootPageId,
        int $changedTime,
        string $indexingConfiguration = '',
        int $indexingPriority = 0
    ): int {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->update($this->table)
            ->set('changed', $changedTime)
            ->set('indexing_priority', $indexingPriority)
            ->andWhere(
                $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter($itemType)),
                $queryBuilder->expr()->eq('item_uid', $itemUid),
                $queryBuilder->expr()->eq('root', $rootPageId)
            );

        if (!empty($indexingConfiguration)) {
            $queryBuilder->set('indexing_configuration', $indexingConfiguration);
        }

        return $queryBuilder->executeStatement();
    }

    /**
     * Adds an item to the index queue.
     *
     * Not meant for public use.
     */
    public function add(
        string $itemType,
        int $itemUid,
        int $rootPageId,
        int $changedTime,
        string $indexingConfiguration,
        int $indexingPriority = 0
    ): int {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->insert($this->table)
            ->values([
                'root' => $rootPageId,
                'item_type' => $itemType,
                'item_uid' => $itemUid,
                'changed' => $changedTime,
                'errors' => '',
                'indexing_configuration' => $indexingConfiguration,
            ])->executeStatement();
    }

    /**
     * Retrieves the count of items that match certain filters. Each filter is passed as parts of the where claus combined with AND.
     *
     * @throws DBALException
     */
    public function countItems(
        array $sites = [],
        array $indexQueueConfigurationNames = [],
        array $itemTypes = [],
        array $itemUids = [],
        array $uids = []
    ): int {
        $rootPageIds = SiteUtility::getRootPageIdsFromSites($sites);
        $indexQueueConfigurationList = implode(',', $indexQueueConfigurationNames);
        $itemTypeList = implode(',', $itemTypes);
        $itemUids = array_map('intval', $itemUids);
        $uids = array_map('intval', $uids);

        $queryBuilderForCountingItems = $this->getQueryBuilder();
        $queryBuilderForCountingItems->count('uid')->from($this->table);
        $queryBuilderForCountingItems = $this->addItemWhereClauses(
            $queryBuilderForCountingItems,
            $rootPageIds,
            $indexQueueConfigurationList,
            $itemTypeList,
            $itemUids,
            $uids
        );

        return (int)$queryBuilderForCountingItems
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Gets the most recent changed time(Timestamp) of a page's content elements change or null if nothing is found.
     *
     * @throws DBALException
     */
    public function getPageItemChangedTimeByPageUid(int $pageUid): ?int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $pageContentLastChangedTime = $queryBuilder
            ->add('select', $queryBuilder->expr()->max('tstamp', 'changed_time'))
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $pageUid)
            )
            ->executeQuery()
            ->fetchAssociative();

        return is_array($pageContentLastChangedTime) ? $pageContentLastChangedTime['changed_time'] : null;
    }

    /**
     * Gets the most recent changed time for an item taking into account localized records.
     *
     * @throws DBALException
     */
    public function getLocalizableItemChangedTime(string $itemType, int $itemUid): int
    {
        $localizedChangedTime = 0;

        if (isset($GLOBALS['TCA'][$itemType]['ctrl']['transOrigPointerField'])) {
            // table is localizable
            $translationOriginalPointerField = $GLOBALS['TCA'][$itemType]['ctrl']['transOrigPointerField'];
            $timeStampField = $GLOBALS['TCA'][$itemType]['ctrl']['tstamp'];

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($itemType);
            $queryBuilder->getRestrictions()->removeAll();
            $localizedChangedTime = $queryBuilder
                ->add('select', $queryBuilder->expr()->max($timeStampField, 'changed_time'))
                ->from($itemType)
                ->orWhere(
                    $queryBuilder->expr()->eq('uid', $itemUid),
                    $queryBuilder->expr()->eq($translationOriginalPointerField, $itemUid)
                )
                ->executeQuery()
                ->fetchOne();
        }
        return (int)$localizedChangedTime;
    }

    /**
     * Returns prepared QueryBuilder for contains* methods in this repository
     */
    protected function getQueryBuilderForContainsMethods(string $itemType, int $itemUid): QueryBuilder
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder->count('uid')->from($this->table)
            ->andWhere(
                $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter($itemType)),
                $queryBuilder->expr()->eq('item_uid', $itemUid)
            );
    }

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @throws DBALException
     */
    public function containsItem(string $itemType, int $itemUid): bool
    {
        return (bool)$this->getQueryBuilderForContainsMethods($itemType, $itemUid)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @throws DBALException
     */
    public function containsItemWithRootPageId(string $itemType, int $itemUid, int $rootPageId): bool
    {
        $queryBuilder = $this->getQueryBuilderForContainsMethods($itemType, $itemUid);
        return (bool)$queryBuilder
            ->andWhere($queryBuilder->expr()->eq('root', $rootPageId))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Checks whether the Index Queue contains a specific item that has been marked as indexed.
     *
     * @throws DBALException
     */
    public function containsIndexedItem(string $itemType, int $itemUid): bool
    {
        $queryBuilder = $this->getQueryBuilderForContainsMethods($itemType, $itemUid);
        return (bool)$queryBuilder
            ->andWhere($queryBuilder->expr()->gt('indexed', 0))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Removes an item from the Index Queue.
     *
     * @throws DBALException
     */
    public function deleteItem(string $itemType, int $itemUid = null): void
    {
        $itemUids = empty($itemUid) ? [] : [$itemUid];
        $this->deleteItems([], [], [$itemType], $itemUids);
    }

    /**
     * Removes all items of a certain type from the Index Queue.
     *
     * @throws DBALException
     */
    public function deleteItemsByType(string $itemType): void
    {
        $this->deleteItem($itemType);
    }

    /**
     * Removes all items of a certain site from the Index Queue. Accepts an
     * optional parameter to limit the deleted items by indexing configuration.
     *
     * @throws DBALException
     */
    public function deleteItemsBySite(Site $site, string $indexingConfigurationName = ''): void
    {
        $indexingConfigurationNames = empty($indexingConfigurationName) ? [] : [$indexingConfigurationName];
        $this->deleteItems([$site], $indexingConfigurationNames);
    }

    /**
     * Removes items in the index queue filtered by the passed arguments.
     *
     * @throws DBALException
     */
    public function deleteItems(
        array $sites = [],
        array $indexQueueConfigurationNames = [],
        array $itemTypes = [],
        array $itemUids = [],
        array $uids = [],
    ): void {
        $rootPageIds = SiteUtility::getRootPageIdsFromSites($sites);
        $indexQueueConfigurationList = implode(',', $indexQueueConfigurationNames);
        $itemTypeList = implode(',', $itemTypes);
        $itemUids = array_map('intval', $itemUids);
        $uids = array_map('intval', $uids);

        $queryBuilderForDeletingItems = $this->getQueryBuilder();
        $queryBuilderForDeletingItems->delete($this->table);
        $queryBuilderForDeletingItems = $this->addItemWhereClauses($queryBuilderForDeletingItems, $rootPageIds, $indexQueueConfigurationList, $itemTypeList, $itemUids, $uids);

        $queryBuilderForDeletingProperties = $this->buildQueryForPropertyDeletion($queryBuilderForDeletingItems, $rootPageIds, $indexQueueConfigurationList, $itemTypeList, $itemUids, $uids);

        $queryBuilderForDeletingItems->getConnection()->beginTransaction();
        try {
            $queryBuilderForDeletingItems->executeStatement();
            $queryBuilderForDeletingProperties->executeStatement();

            $queryBuilderForDeletingItems->getConnection()->commit();
        } catch (DBALException $e) {
            $queryBuilderForDeletingItems->getConnection()->rollback();
            throw $e;
        }
    }

    /**
     * Initializes the query builder to delete items in the index queue filtered by the passed arguments.
     */
    private function addItemWhereClauses(
        QueryBuilder $queryBuilderForDeletingItems,
        array $rootPageIds,
        string $indexQueueConfigurationList,
        string $itemTypeList,
        array $itemUids,
        array $uids,
    ): QueryBuilder {
        if (!empty($rootPageIds)) {
            $queryBuilderForDeletingItems->andWhere(
                $queryBuilderForDeletingItems->expr()->in('root', $rootPageIds)
            );
        }

        if (!empty($indexQueueConfigurationList)) {
            $queryBuilderForDeletingItems->andWhere(
                $queryBuilderForDeletingItems->expr()->in(
                    'indexing_configuration',
                    $queryBuilderForDeletingItems->createNamedParameter($indexQueueConfigurationList)
                )
            );
        }

        if (!empty($itemTypeList)) {
            $queryBuilderForDeletingItems->andWhere(
                $queryBuilderForDeletingItems->expr()->in(
                    'item_type',
                    $queryBuilderForDeletingItems->createNamedParameter($itemTypeList)
                )
            );
        }

        if (!empty($itemUids)) {
            $queryBuilderForDeletingItems->andWhere(
                $queryBuilderForDeletingItems->expr()->in('item_uid', $itemUids)
            );
        }

        if (!empty($uids)) {
            $queryBuilderForDeletingItems->andWhere(
                $queryBuilderForDeletingItems->expr()->in('uid', $uids)
            );
        }

        return $queryBuilderForDeletingItems;
    }

    /**
     * Initializes a query builder to delete the indexing properties of an item by the passed conditions.
     *
     * @throws DBALException
     */
    private function buildQueryForPropertyDeletion(
        QueryBuilder $queryBuilderForDeletingItems,
        array $rootPageIds,
        string $indexQueueConfigurationList,
        string $itemTypeList,
        array $itemUids,
        array $uids
    ): QueryBuilder {
        $queryBuilderForSelectingProperties = $queryBuilderForDeletingItems->getConnection()->createQueryBuilder();
        $queryBuilderForSelectingProperties
            ->select('items.uid')
            ->from('tx_solr_indexqueue_indexing_property', 'properties')
            ->innerJoin(
                'properties',
                $this->table,
                'items',
                (string)$queryBuilderForSelectingProperties->expr()->and(
                    $queryBuilderForSelectingProperties->expr()->eq('items.uid', $queryBuilderForSelectingProperties->quoteIdentifier('properties.item_id')),
                    empty($rootPageIds) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.root', $rootPageIds),
                    empty($indexQueueConfigurationList) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.indexing_configuration', $queryBuilderForSelectingProperties->createNamedParameter($indexQueueConfigurationList)),
                    empty($itemTypeList) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.item_type', $queryBuilderForSelectingProperties->createNamedParameter($itemTypeList)),
                    empty($itemUids) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.item_uid', $itemUids),
                    empty($uids) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.uid', $uids)
                )
            );
        $propertyEntriesToDelete = implode(
            ',',
            array_column(
                $queryBuilderForSelectingProperties
                    ->executeQuery()
                    ->fetchAllAssociative(),
                'uid'
            )
        );

        $queryBuilderForDeletingProperties = $queryBuilderForDeletingItems->getConnection()->createQueryBuilder();

        // make sure executing the property deletion query doesn't fail if there are no properties to delete
        if (empty($propertyEntriesToDelete)) {
            $propertyEntriesToDelete = '0';
        }

        $queryBuilderForDeletingProperties->delete('tx_solr_indexqueue_indexing_property')->where(
            $queryBuilderForDeletingProperties->expr()->in('item_id', $propertyEntriesToDelete)
        );

        return $queryBuilderForDeletingProperties;
    }

    /**
     * Removes all items from the Index Queue.
     */
    public function deleteAllItems(): int
    {
        return $this->getQueryBuilder()->getConnection()->truncate($this->table);
    }

    /**
     * Gets a single Index Queue item by its uid.
     *
     * @throws DBALException
     */
    public function findItemByUid(int $uid): ?Item
    {
        $queryBuilder = $this->getQueryBuilder();
        $indexQueueItemRecord = $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()
            ->fetchAssociative();

        if (!isset($indexQueueItemRecord['uid'])) {
            return null;
        }

        return GeneralUtility::makeInstance(Item::class, $indexQueueItemRecord);
    }

    /**
     * Gets Index Queue items matching $itemType and $itemUid
     *
     * @return Item[]
     *
     * @throws DBALException
     */
    public function findItemsByItemTypeAndItemUid(string $itemType, int $itemUid): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $compositeExpression = $queryBuilder->expr()->and(
            $queryBuilder->expr()->eq('item_type', $queryBuilder->getConnection()->quote($itemType, PDO::PARAM_STR)),
            $queryBuilder->expr()->eq('item_uid', $itemUid)
        );
        return $this->getItemsByCompositeExpression($compositeExpression, $queryBuilder);
    }

    /**
     * Returns a collection of items by CompositeExpression.
     *
     * @return Item[]
     *
     * @throws DBALException
     */
    protected function getItemsByCompositeExpression(
        CompositeExpression $expression = null,
        QueryBuilder $queryBuilder = null
    ): array {
        if (!$queryBuilder instanceof QueryBuilder) {
            $queryBuilder = $this->getQueryBuilder();
        }

        $queryBuilder->select('*')->from($this->table);
        if (isset($expression)) {
            $queryBuilder->where($expression);
        }

        $indexQueueItemRecords = $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
        return $this->getIndexQueueItemObjectsFromRecords($indexQueueItemRecords);
    }

    /**
     * Returns all items in the queue without restrictions
     *
     * @return Item[]
     *
     * @throws DBALException
     */
    public function findAll(): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $allRecords = $queryBuilder
            ->select('*')
            ->from($this->table)
            ->executeQuery()
            ->fetchAllAssociative();
        return $this->getIndexQueueItemObjectsFromRecords($allRecords);
    }

    /**
     * Gets indexable queue items for given site.
     *
     * @return Item[]
     *
     * @throws DBALException
     */
    public function findItemsToIndex(Site $site, int $limit = 50): array
    {
        $queryBuilder = $this->getQueryBuilder();
        // determine which items to index with this run
        $indexQueueItemRecords = $queryBuilder
            ->select('*')
            ->from($this->table)
            ->andWhere(
                $queryBuilder->expr()->eq('root', $site->getRootPageId()),
                $queryBuilder->expr()->gt('changed', 'indexed'),
                $queryBuilder->expr()->lte('changed', time()),
                $queryBuilder->expr()->eq('errors', $queryBuilder->createNamedParameter(''))
            )
            ->orderBy('indexing_priority', 'DESC')
            ->addOrderBy('changed', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->getIndexQueueItemObjectsFromRecords($indexQueueItemRecords);
    }

    /**
     * Retrieves the count of items that match certain filters. Each filter is passed as parts of the where claus combined with AND.
     *
     * @return Item[]
     *
     * @throws DBALException
     */
    public function findItems(
        array $sites = [],
        array $indexQueueConfigurationNames = [],
        array $itemTypes = [],
        array $itemUids = [],
        array $uids = [],
        int $start = 0,
        int $limit = 50,
    ): array {
        $rootPageIds = SiteUtility::getRootPageIdsFromSites($sites);
        $indexQueueConfigurationList = implode(',', $indexQueueConfigurationNames);
        $itemTypeList = implode(',', $itemTypes);
        $itemUids = array_map('intval', $itemUids);
        $uids = array_map('intval', $uids);
        $itemQueryBuilder = $this->getQueryBuilder()->select('*')->from($this->table);
        $itemQueryBuilder = $this->addItemWhereClauses($itemQueryBuilder, $rootPageIds, $indexQueueConfigurationList, $itemTypeList, $itemUids, $uids);
        $itemRecords = $itemQueryBuilder->setFirstResult($start)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
        return $this->getIndexQueueItemObjectsFromRecords($itemRecords);
    }

    /**
     * Creates an array of ApacheSolrForTypo3\Solr\IndexQueue\Item objects from an array of
     * index queue records.
     *
     * @return Item[]
     *
     * @throws DBALException
     */
    protected function getIndexQueueItemObjectsFromRecords(array $indexQueueItemRecords): array
    {
        $tableRecords = $this->getAllQueueItemRecordsByUidsGroupedByTable($indexQueueItemRecords);
        return $this->getQueueItemObjectsByRecords($indexQueueItemRecords, $tableRecords);
    }

    /**
     * Returns the records for suitable item type.
     *
     * @throws DBALException
     */
    protected function getAllQueueItemRecordsByUidsGroupedByTable(array $indexQueueItemRecords): array
    {
        $tableUids = [];
        $tableRecords = [];
        // grouping records by table
        foreach ($indexQueueItemRecords as $indexQueueItemRecord) {
            $tableUids[$indexQueueItemRecord['item_type']][] = $indexQueueItemRecord['item_uid'];
        }

        // fetching records by table, saves us a lot of single queries
        foreach ($tableUids as $table => $uids) {
            $uidList = implode(',', $uids);

            $queryBuilderForRecordTable = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilderForRecordTable->getRestrictions()->removeAll();
            $resultsFromRecordTable = $queryBuilderForRecordTable
                ->select('*')
                ->from($table)
                ->where($queryBuilderForRecordTable->expr()->in('uid', $uidList))
                ->executeQuery();
            $records = [];
            while ($record = $resultsFromRecordTable->fetchAssociative()) {
                $records[$record['uid']] = $record;
            }

            $tableRecords[$table] = $records;
            $event = $this->eventDispatcher->dispatch(new AfterRecordsForIndexQueueItemsHaveBeenRetrievedEvent($table, $uids, $records));
            $tableRecords[$table] = $event->getRecords();
        }

        return $tableRecords;
    }

    /**
     * Instantiates a list of Item objects from database records.
     *
     * @return Item[]
     *
     * @throws DBALException
     */
    protected function getQueueItemObjectsByRecords(array $indexQueueItemRecords, array $tableRecords): array
    {
        $indexQueueItems = [];
        foreach ($indexQueueItemRecords as $indexQueueItemRecord) {
            if (isset($tableRecords[$indexQueueItemRecord['item_type']][$indexQueueItemRecord['item_uid']])) {
                $indexQueueItems[] = GeneralUtility::makeInstance(
                    Item::class,
                    $indexQueueItemRecord,
                    $tableRecords[$indexQueueItemRecord['item_type']][$indexQueueItemRecord['item_uid']]
                );
            } else {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Record missing for Index Queue item. Item removed.',
                    [
                        $indexQueueItemRecord,
                    ]
                );
                $this->deleteItem(
                    $indexQueueItemRecord['item_type'],
                    $indexQueueItemRecord['item_uid']
                );
            }
        }

        return $indexQueueItems;
    }

    /**
     * Marks an item as failed and causes the indexer to skip the item in the
     * next run.
     */
    public function markItemAsFailed(Item|int|null $item, string $errorMessage = ''): int
    {
        $itemUid = ($item instanceof Item) ? $item->getIndexQueueUid() : (int)$item;
        $errorMessage = empty($errorMessage) ? '1' : $errorMessage;

        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->update($this->table)
            ->set('errors', $errorMessage)
            ->where($queryBuilder->expr()->eq('uid', $itemUid))
            ->executeStatement();
    }

    /**
     * Sets the timestamp of when an item last has been indexed.
     */
    public function updateIndexTimeByItem(Item $item): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->update($this->table)
            ->set('indexed', time())
            ->where($queryBuilder->expr()->eq('uid', $item->getIndexQueueUid()))
            ->executeStatement();
    }

    /**
     * Sets the change timestamp of an item.
     */
    public function updateChangedTimeByItem(Item $item, int $changedTime = 0): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->update($this->table)
            ->set('changed', $changedTime)
            ->where($queryBuilder->expr()->eq('uid', $item->getIndexQueueUid()))
            ->executeStatement();
    }

    /**
     * Initializes Queue by given sql
     *
     * Note: Do not use platform specific functions!
     *
     * @throws DBALException
     */
    public function initializeByNativeSQLStatement(string $sqlStatement): int
    {
        return $this->getQueryBuilder()
            ->getConnection()
            ->executeStatement($sqlStatement);
    }

    /**
     * Retrieves an array of pageIds from mountPoints that already have a queue entry.
     *
     * @throws DBALException
     */
    public function findPageIdsOfExistingMountPagesByMountIdentifier(string $mountPointIdentifier): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $resultSet = $queryBuilder
            ->select('item_uid')
            ->add('select', $queryBuilder->expr()->count('*', 'queueItemCount'), true)
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('pages_mountidentifier', $queryBuilder->createNamedParameter($mountPointIdentifier))
            )
            ->groupBy('item_uid')
            ->executeQuery();

        $mountedPagesIdsWithQueueItems = [];
        while ($record = $resultSet->fetchAssociative()) {
            if ($record['queueItemCount'] > 0) {
                $mountedPagesIdsWithQueueItems[] = $record['item_uid'];
            }
        }

        return $mountedPagesIdsWithQueueItems;
    }

    /**
     * Retrieves an array of items for mount destinations matched by root page ID, Mount Identifier and a list of mounted page IDs.
     *
     * @throws DBALException
     */
    public function findAllIndexQueueItemsByRootPidAndMountIdentifierAndMountedPids(
        int $rootPid,
        string $mountPointIdentifier,
        array $mountedPids,
    ): array {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('root', $queryBuilder->createNamedParameter($rootPid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->in('item_uid', $mountedPids),
                $queryBuilder->expr()->eq('has_indexing_properties', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('pages_mountidentifier', $queryBuilder->createNamedParameter($mountPointIdentifier))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Updates has_indexing_properties field for given Item
     */
    public function updateHasIndexingPropertiesFlagByItemUid(int $itemUid, bool $hasIndexingPropertiesFlag): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->update($this->table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($itemUid, PDO::PARAM_INT))
            )
            ->set(
                'has_indexing_properties',
                $queryBuilder->createNamedParameter($hasIndexingPropertiesFlag, PDO::PARAM_INT),
                false
            )->executeStatement();
    }
}
