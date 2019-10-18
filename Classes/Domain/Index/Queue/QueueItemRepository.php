<?php declare(strict_types = 1);
namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class QueueItemRepository
 * Handles all CRUD operations to tx_solr_indexqueue_item table
 *
 */
class QueueItemRepository extends AbstractRepository
{
    /**
     * @var string
     */
    protected $table = 'tx_solr_indexqueue_item';

    /**
     * @var SolrLogManager
     */
    protected $logger;

    /**
     * QueueItemRepository constructor.
     *
     * @param SolrLogManager|null $logManager
     */
    public function __construct(SolrLogManager $logManager = null)
    {
        $this->logger = $logManager ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
    }

    /**
     * Fetches the last indexed row
     *
     * @param int $rootPageId The root page uid for which to get the last indexed row
     * @return array
     */
    public function findLastIndexedRow(int $rootPageId) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        $row = $queryBuilder
            ->select('uid', 'indexed')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('root', $rootPageId))
            ->andWhere($queryBuilder->expr()->neq('indexed', 0))
            ->orderBy('indexed', 'DESC')
            ->setMaxResults(1)
            ->execute()->fetchAll();

        return $row;
    }

    /**
     * Finds indexing errors for the current site
     *
     * @param Site $site
     * @return array Error items for the current site's Index Queue
     */
    public function findErrorsBySite(Site $site) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        $errors = $queryBuilder
            ->select('uid', 'item_type', 'item_uid', 'errors')
            ->from($this->table)
            ->andWhere(
                $queryBuilder->expr()->notLike('errors', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->eq('root', $site->getRootPageId())
            )
            ->execute()->fetchAll();

        return $errors;
    }

    /**
     * Resets all the errors for all index queue items.
     *
     * @return int affected rows
     */
    public function flushAllErrors() : int
    {
        $queryBuilder = $this->getQueryBuilder();
        $affectedRows = $this->getPreparedFlushErrorQuery($queryBuilder)->execute();
        return $affectedRows;
    }

    /**
     * Flushes the errors for a single site.
     *
     * @param Site $site
     * @return int
     */
    public function flushErrorsBySite(Site $site) : int
    {
        $queryBuilder = $this->getQueryBuilder();
        $affectedRows = $this->getPreparedFlushErrorQuery($queryBuilder)
            ->andWhere(
                $queryBuilder->expr()->eq('root', (int)$site->getRootPageId())
            )
            ->execute();
        return $affectedRows;
    }

    /**
     * Flushes the error for a single item.
     *
     * @param Item $item
     * @return int affected rows
     */
    public function flushErrorByItem(Item $item) : int
    {
        $queryBuilder = $this->getQueryBuilder();
        $affectedRows = $this->getPreparedFlushErrorQuery($queryBuilder)
            ->andWhere(
                $queryBuilder->expr()->eq('uid', $item->getIndexQueueUid())
            )
            ->execute();
        return $affectedRows;
    }

    /**
     * Initializes the QueryBuilder with a query the resets the error field for items that have an error.
     *
     * @return QueryBuilder
     */
    private function getPreparedFlushErrorQuery(QueryBuilder $queryBuilder)
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
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param string $indexingConfiguration The name of the related indexConfiguration
     * @param int $rootPageId The uid of the rootPage
     * @param int $changedTime The forced change time that should be used for updating
     * @return int affected rows
     */
    public function updateExistingItemByItemTypeAndItemUidAndRootPageId(string $itemType, int $itemUid, int $rootPageId, int $changedTime, string $indexingConfiguration = '') : int
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->update($this->table)
            ->set('changed', $changedTime)
            ->andWhere(
                $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter($itemType)),
                $queryBuilder->expr()->eq('item_uid', $itemUid),
                $queryBuilder->expr()->eq('root', $rootPageId)
            );

        if (!empty($indexingConfiguration)) {
            $queryBuilder->set('indexing_configuration', $indexingConfiguration);
        }

        return $queryBuilder->execute();
    }

    /**
     * Adds an item to the index queue.
     *
     * Not meant for public use.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int $itemUid The item's uid, usually an integer uid, could be a different value for non-database-record types.
     * @param int $rootPageId
     * @param int $changedTime
     * @param string $indexingConfiguration The item's indexing configuration to use. Optional, overwrites existing / determined configuration.
     * @return int the number of inserted rows, which is typically 1
     */
    public function add(string $itemType, int $itemUid, int $rootPageId, int $changedTime, string $indexingConfiguration) : int
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->insert($this->table)
            ->values([
                'root' => $rootPageId,
                'item_type' => $itemType,
                'item_uid' => $itemUid,
                'changed' => $changedTime,
                'errors' => '',
                'indexing_configuration' => $indexingConfiguration
            ])
            ->execute();

    }

    /**
     * Retrieves the count of items that match certain filters. Each filter is passed as parts of the where claus combined with AND.
     *
     * @param array $sites
     * @param array $indexQueueConfigurationNames
     * @param array $itemTypes
     * @param array $itemUids
     * @param array $uids
     * @return int
     */
    public function countItems(array $sites = [], array $indexQueueConfigurationNames = [], array $itemTypes = [], array $itemUids = [], array $uids = []): int
    {
        $rootPageIds = Site::getRootPageIdsFromSites($sites);
        $indexQueueConfigurationList = implode(",", $indexQueueConfigurationNames);
        $itemTypeList = implode(",", $itemTypes);
        $itemUids = array_map("intval", $itemUids);
        $uids = array_map("intval", $uids);

        $queryBuilderForCountingItems = $this->getQueryBuilder();
        $queryBuilderForCountingItems->count('uid')->from($this->table);
        $queryBuilderForCountingItems = $this->addItemWhereClauses($queryBuilderForCountingItems, $rootPageIds, $indexQueueConfigurationList, $itemTypeList, $itemUids, $uids);

        return (int)$queryBuilderForCountingItems->execute()->fetchColumn(0);
    }

    /**
     * Gets the most recent changed time of a page's content elements
     *
     * @param int $pageUid
     * @return int|null Timestamp of the most recent content element change or null if nothing is found.
     */
    public function getPageItemChangedTimeByPageUid(int $pageUid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $pageContentLastChangedTime = $queryBuilder
            ->add('select', $queryBuilder->expr()->max('tstamp', 'changed_time'))
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $pageUid)
            )
            ->execute()->fetch();

        return $pageContentLastChangedTime['changed_time'];
    }

    /**
     * Gets the most recent changed time for an item taking into account
     * localized records.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int $itemUid The item's uid
     * @return int Timestamp of the most recent content element change
     */
    public function getLocalizableItemChangedTime(string $itemType, int $itemUid) : int
    {
        $localizedChangedTime = 0;

        if (isset($GLOBALS['TCA'][$itemType]['ctrl']['transOrigPointerField'])) {
            // table is localizable
            $translationOriginalPointerField = $GLOBALS['TCA'][$itemType]['ctrl']['transOrigPointerField'];

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($itemType);
            $queryBuilder->getRestrictions()->removeAll();
            $localizedChangedTime = $queryBuilder
                ->add('select', $queryBuilder->expr()->max('tstamp', 'changed_time'))
                ->from($itemType)
                ->orWhere(
                    $queryBuilder->expr()->eq('uid', $itemUid),
                    $queryBuilder->expr()->eq($translationOriginalPointerField, $itemUid)
                )->execute()->fetchColumn(0);
        }

        return (int)$localizedChangedTime;
    }

    /**
     * Returns prepared QueryBuilder for contains* methods in this repository
     *
     * @param string $itemType
     * @param int $itemUid
     * @return QueryBuilder
     */
    protected function getQueryBuilderForContainsMethods(string $itemType, int $itemUid) : QueryBuilder
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
     * @param string $itemType The item's type, usually a table name.
     * @param int $itemUid The item's uid
     * @return bool TRUE if the item is found in the queue, FALSE otherwise
     */
    public function containsItem(string $itemType, int $itemUid) : bool
    {
        return (bool)$this->getQueryBuilderForContainsMethods($itemType, $itemUid)->execute()->fetchColumn(0);
    }

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int $itemUid The item's uid
     * @param integer $rootPageId
     * @return bool TRUE if the item is found in the queue, FALSE otherwise
     */
    public function containsItemWithRootPageId(string $itemType, int $itemUid, int $rootPageId) : bool
    {
        $queryBuilder = $this->getQueryBuilderForContainsMethods($itemType, $itemUid);
        return (bool)$queryBuilder
            ->andWhere($queryBuilder->expr()->eq('root', $rootPageId))
            ->execute()->fetchColumn(0);
    }

    /**
     * Checks whether the Index Queue contains a specific item that has been
     * marked as indexed.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int $itemUid The item's uid
     * @return bool TRUE if the item is found in the queue and marked as
     *      indexed, FALSE otherwise
     */
    public function containsIndexedItem(string $itemType, int $itemUid) : bool
    {
        $queryBuilder = $this->getQueryBuilderForContainsMethods($itemType, $itemUid);
        return (bool)$queryBuilder
            ->andWhere($queryBuilder->expr()->gt('indexed', 0))
            ->execute()->fetchColumn(0);
    }

    /**
     * Removes an item from the Index Queue.
     *
     * @param string $itemType The type of the item to remove, usually a table name.
     * @param int $itemUid The uid of the item to remove
     */
    public function deleteItem(string $itemType, int $itemUid = null)
    {
        $itemUids = empty($itemUid) ? [] : [$itemUid];
        $this->deleteItems([], [], [$itemType], $itemUids);
    }

    /**
     * Removes all items of a certain type from the Index Queue.
     *
     * @param string $itemType The type of items to remove, usually a table name.
     */
    public function deleteItemsByType(string $itemType)
    {
        $this->deleteItem($itemType);
    }

    /**
     * Removes all items of a certain site from the Index Queue. Accepts an
     * optional parameter to limit the deleted items by indexing configuration.
     *
     * @param Site $site The site to remove items for.
     * @param string $indexingConfigurationName Name of a specific indexing configuration
     * @throws \Exception
     */
    public function deleteItemsBySite(Site $site, string $indexingConfigurationName = '')
    {
        $indexingConfigurationNames = empty($indexingConfigurationName) ? [] : [$indexingConfigurationName];
        $this->deleteItems([$site], $indexingConfigurationNames);
    }

    /**
     * Removes items in the index queue filtered by the passed arguments.
     *
     * @param array $sites
     * @param array $indexQueueConfigurationNames
     * @param array $itemTypes
     * @param array $itemUids
     * @param array $uids
     * @throws \Exception
     */
    public function deleteItems(array $sites = [], array $indexQueueConfigurationNames = [], array $itemTypes = [], array $itemUids = [], array $uids = [])
    {
        $rootPageIds = Site::getRootPageIdsFromSites($sites);
        $indexQueueConfigurationList = implode(",", $indexQueueConfigurationNames);
        $itemTypeList = implode(",", $itemTypes);
        $itemUids = array_map("intval", $itemUids);
        $uids = array_map("intval", $uids);

        $queryBuilderForDeletingItems = $this->getQueryBuilder();
        $queryBuilderForDeletingItems->delete($this->table);
        $queryBuilderForDeletingItems = $this->addItemWhereClauses($queryBuilderForDeletingItems, $rootPageIds, $indexQueueConfigurationList, $itemTypeList, $itemUids, $uids);

        $queryBuilderForDeletingProperties = $this->buildQueryForPropertyDeletion($queryBuilderForDeletingItems, $rootPageIds, $indexQueueConfigurationList, $itemTypeList, $itemUids, $uids);

        $queryBuilderForDeletingItems->getConnection()->beginTransaction();
        try {
            $queryBuilderForDeletingItems->execute();
            $queryBuilderForDeletingProperties->execute();

            $queryBuilderForDeletingItems->getConnection()->commit();
        } catch (\Exception $e) {
            $queryBuilderForDeletingItems->getConnection()->rollback();
            throw $e;
        }
    }

    /**
     * Initializes the query builder to delete items in the index queue filtered by the passed arguments.
     *
     * @param array $rootPageIds filter on a set of rootPageUids.
     * @param string $indexQueueConfigurationList
     * @param string $itemTypeList
     * @param array $itemUids filter on a set of item uids
     * @param array $uids filter on a set of queue item uids
     * @return QueryBuilder
     */
    private function addItemWhereClauses(QueryBuilder $queryBuilderForDeletingItems, array $rootPageIds, string $indexQueueConfigurationList, string $itemTypeList, array $itemUids, array $uids): QueryBuilder
    {

        if (!empty($rootPageIds)) {
            $queryBuilderForDeletingItems->andWhere($queryBuilderForDeletingItems->expr()->in('root', $rootPageIds));
        };

        if (!empty($indexQueueConfigurationList)) {
            $queryBuilderForDeletingItems->andWhere($queryBuilderForDeletingItems->expr()->in('indexing_configuration', $queryBuilderForDeletingItems->createNamedParameter($indexQueueConfigurationList)));
        }

        if (!empty($itemTypeList)) {
            $queryBuilderForDeletingItems->andWhere($queryBuilderForDeletingItems->expr()->in('item_type', $queryBuilderForDeletingItems->createNamedParameter($itemTypeList)));
        }

        if (!empty($itemUids)) {
            $queryBuilderForDeletingItems->andWhere($queryBuilderForDeletingItems->expr()->in('item_uid', $itemUids));
        }

        if (!empty($uids)) {
            $queryBuilderForDeletingItems->andWhere($queryBuilderForDeletingItems->expr()->in('uid', $uids));
        }

        return $queryBuilderForDeletingItems;
    }

    /**
     * Initializes a query builder to delete the indexing properties of an item by the passed conditions.
     *
     * @param QueryBuilder $queryBuilderForDeletingItems
     * @param array $rootPageIds
     * @param string $indexQueueConfigurationList
     * @param string $itemTypeList
     * @param array $itemUids
     * @param array $uids
     * @return QueryBuilder
     */
    private function buildQueryForPropertyDeletion(QueryBuilder $queryBuilderForDeletingItems, array $rootPageIds, string $indexQueueConfigurationList, string $itemTypeList, array $itemUids, array $uids): QueryBuilder
    {
        $queryBuilderForSelectingProperties = $queryBuilderForDeletingItems->getConnection()->createQueryBuilder();
        $queryBuilderForSelectingProperties->select('items.uid')->from('tx_solr_indexqueue_indexing_property', 'properties')->innerJoin(
            'properties',
            $this->table,
            'items',
            (string)$queryBuilderForSelectingProperties->expr()->andX(
                $queryBuilderForSelectingProperties->expr()->eq('items.uid', $queryBuilderForSelectingProperties->quoteIdentifier('properties.item_id')),
                empty($rootPageIds) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.root', $rootPageIds),
                empty($indexQueueConfigurationList) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.indexing_configuration', $queryBuilderForSelectingProperties->createNamedParameter($indexQueueConfigurationList)),
                empty($itemTypeList) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.item_type', $queryBuilderForSelectingProperties->createNamedParameter($itemTypeList)),
                empty($itemUids) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.item_uid', $itemUids),
                empty($uids) ? '' : $queryBuilderForSelectingProperties->expr()->in('items.uid', $uids)
            )
        );
        $propertyEntriesToDelete = implode(',', array_column($queryBuilderForSelectingProperties->execute()->fetchAll(), 'uid'));

        $queryBuilderForDeletingProperties = $queryBuilderForDeletingItems->getConnection()->createQueryBuilder();

        // make sure executing the propety deletion query doesn't fail if there are no properties to delete
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
     *
     * @return int The number of affected rows. For a truncate this is unreliable as theres no meaningful information.
     */
    public function deleteAllItems()
    {
        return $this->getQueryBuilder()->getConnection()->truncate($this->table);
    }

    /**
     * Gets a single Index Queue item by its uid.
     *
     * @param int $uid Index Queue item uid
     * @return Item|null The request Index Queue item or NULL if no item with $itemId was found
     */
    public function findItemByUid(int $uid)
    {
        $queryBuilder = $this->getQueryBuilder();
        $indexQueueItemRecord = $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->execute()->fetch();

        if (!isset($indexQueueItemRecord['uid'])) {
            return null;
        }

        /** @var Item $item*/
        $item = GeneralUtility::makeInstance(Item::class, /** @scrutinizer ignore-type */ $indexQueueItemRecord);
        return $item;
    }

    /**
     * Gets Index Queue items by type and uid.
     *
     * @param string $itemType item type, usually  the table name
     * @param int $itemUid item uid
     * @return Item[] An array of items matching $itemType and $itemUid
     */
    public function findItemsByItemTypeAndItemUid(string $itemType, int $itemUid) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        $compositeExpression = $queryBuilder->expr()->andX(
            $queryBuilder->expr()->eq('item_type', $queryBuilder->getConnection()->quote($itemType, \PDO::PARAM_STR)),
            $queryBuilder->expr()->eq('item_uid', $itemUid)
        );
        return $this->getItemsByCompositeExpression($compositeExpression, $queryBuilder);
    }

    /**
     * Returns a collection of items by CompositeExpression.
     * D
     *
     * @param CompositeExpression|null $expression Optional expression to filter records.
     * @param QueryBuilder|null $queryBuilder QueryBuilder to use
     * @return array
     */
    protected function getItemsByCompositeExpression(CompositeExpression $expression = null, QueryBuilder $queryBuilder = null) : array
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            $queryBuilder = $this->getQueryBuilder();
        }

        $queryBuilder->select('*')->from($this->table);
        if (isset($expression)) {
            $queryBuilder->where($expression);
        }

        $indexQueueItemRecords = $queryBuilder->execute()->fetchAll();
        return $this->getIndexQueueItemObjectsFromRecords($indexQueueItemRecords);
    }

    /**
     * Returns all items in the queue.
     *
     * @return Item[] all Items from Queue without restrictions
     */
    public function findAll() : array
    {
        $queryBuilder = $this->getQueryBuilder();
        $allRecords = $queryBuilder
            ->select('*')
            ->from($this->table)
            ->execute()->fetchAll();
        return $this->getIndexQueueItemObjectsFromRecords($allRecords);
    }

    /**
     * Gets $limit number of items to index for a particular $site.
     *
     * @param Site $site TYPO3 site
     * @param int $limit Number of items to get from the queue
     * @return Item[] Items to index to the given solr server
     */
    public function findItemsToIndex(Site $site, int $limit = 50) : array
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
            ->execute()->fetchAll();

        return $this->getIndexQueueItemObjectsFromRecords($indexQueueItemRecords);
    }

    /**
     * Retrieves the count of items that match certain filters. Each filter is passed as parts of the where claus combined with AND.
     *
     * @param array $sites
     * @param array $indexQueueConfigurationNames
     * @param array $itemTypes
     * @param array $itemUids
     * @param array $uids
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function findItems(array $sites = [], array $indexQueueConfigurationNames = [], array $itemTypes = [], array $itemUids = [], array $uids = [], $start = 0, $limit = 50): array
    {
        $rootPageIds = Site::getRootPageIdsFromSites($sites);
        $indexQueueConfigurationList = implode(",", $indexQueueConfigurationNames);
        $itemTypeList = implode(",", $itemTypes);
        $itemUids = array_map("intval", $itemUids);
        $uids = array_map("intval", $uids);
        $itemQueryBuilder = $this->getQueryBuilder()->select('*')->from($this->table);
        $itemQueryBuilder = $this->addItemWhereClauses($itemQueryBuilder, $rootPageIds, $indexQueueConfigurationList, $itemTypeList, $itemUids, $uids);
        $itemRecords = $itemQueryBuilder->setFirstResult($start)->setMaxResults($limit)->execute()->fetchAll();
        return $this->getIndexQueueItemObjectsFromRecords($itemRecords);
    }

    /**
     * Creates an array of ApacheSolrForTypo3\Solr\IndexQueue\Item objects from an array of
     * index queue records.
     *
     * @param array $indexQueueItemRecords Array of plain index queue records
     * @return array Array of ApacheSolrForTypo3\Solr\IndexQueue\Item objects
     */
    protected function getIndexQueueItemObjectsFromRecords(array $indexQueueItemRecords) : array
    {
        $tableRecords = $this->getAllQueueItemRecordsByUidsGroupedByTable($indexQueueItemRecords);
        return $this->getQueueItemObjectsByRecords($indexQueueItemRecords, $tableRecords);
    }

    /**
     * Returns the records for suitable item type.
     *
     * @param array $indexQueueItemRecords
     * @return array
     */
    protected function getAllQueueItemRecordsByUidsGroupedByTable(array $indexQueueItemRecords) : array
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
                ->execute();
            $records = [];
            while ($record = $resultsFromRecordTable->fetch()) {
                $records[$record['uid']] = $record;
            }

            $tableRecords[$table] = $records;
            $this->hookPostProcessFetchRecordsForIndexQueueItem($table, $uids, $tableRecords);
        }

        return $tableRecords;
    }

    /**
     * Calls defined in postProcessFetchRecordsForIndexQueueItem hook method.
     *
     * @param string $table
     * @param array $uids
     * @param array $tableRecords
     *
     * @return void
     */
    protected function hookPostProcessFetchRecordsForIndexQueueItem(string $table, array $uids, array &$tableRecords)
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessFetchRecordsForIndexQueueItem'])) {
            return;
        }
        $params = ['table' => $table, 'uids' => $uids, 'tableRecords' => &$tableRecords];
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessFetchRecordsForIndexQueueItem'] as $reference) {
            GeneralUtility::callUserFunction($reference, $params, $this);
        }
    }

    /**
     * Instantiates a list of Item objects from database records.
     *
     * @param array $indexQueueItemRecords records from database
     * @param array $tableRecords
     * @return array
     */
    protected function getQueueItemObjectsByRecords(array $indexQueueItemRecords, array $tableRecords) : array
    {
        $indexQueueItems = [];
        foreach ($indexQueueItemRecords as $indexQueueItemRecord) {
            if (isset($tableRecords[$indexQueueItemRecord['item_type']][$indexQueueItemRecord['item_uid']])) {
                $indexQueueItems[] = GeneralUtility::makeInstance(
                    Item::class,
                    /** @scrutinizer ignore-type */ $indexQueueItemRecord,
                    /** @scrutinizer ignore-type */ $tableRecords[$indexQueueItemRecord['item_type']][$indexQueueItemRecord['item_uid']]
                );
            } else {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Record missing for Index Queue item. Item removed.',
                    [
                        $indexQueueItemRecord
                    ]
                );
                $this->deleteItem($indexQueueItemRecord['item_type'],
                    $indexQueueItemRecord['item_uid']);
            }
        }

        return $indexQueueItems;
    }

    /**
     * Marks an item as failed and causes the indexer to skip the item in the
     * next run.
     *
     * @param int|Item $item Either the item's Index Queue uid or the complete item
     * @param string $errorMessage Error message
     * @return int affected rows
     */
    public function markItemAsFailed($item, string $errorMessage = ''): int
    {
        $itemUid = ($item instanceof Item) ? $item->getIndexQueueUid() : (int)$item;
        $errorMessage = empty($errorMessage) ? '1' : $errorMessage;

        $queryBuilder = $this->getQueryBuilder();
        return (int)$queryBuilder
            ->update($this->table)
            ->set('errors', $errorMessage)
            ->where($queryBuilder->expr()->eq('uid', $itemUid))
            ->execute();
    }

    /**
     * Sets the timestamp of when an item last has been indexed.
     *
     * @param Item $item
     * @return int affected rows
     */
    public function updateIndexTimeByItem(Item $item) : int
    {
        $queryBuilder = $this->getQueryBuilder();
        return (int)$queryBuilder
            ->update($this->table)
            ->set('indexed', time())
            ->where($queryBuilder->expr()->eq('uid', $item->getIndexQueueUid()))
            ->execute();
    }

    /**
     * Sets the change timestamp of an item.
     *
     * @param Item $item
     * @param int $changedTime
     * @return int affected rows
     */
    public function updateChangedTimeByItem(Item $item, int $changedTime) : int
    {
        $queryBuilder = $this->getQueryBuilder();
        return (int)$queryBuilder
            ->update($this->table)
            ->set('changed', $changedTime)
            ->where($queryBuilder->expr()->eq('uid', $item->getIndexQueueUid()))
            ->execute();
    }

    /**
     * Initializes Queue by given sql
     *
     * Note: Do not use platform specific functions!
     *
     * @param string $sqlStatement Native SQL statement
     * @return int The number of affected rows.
     * @internal
     * @throws DBALException
     */
    public function initializeByNativeSQLStatement(string $sqlStatement) : int
    {
        return $this->getQueryBuilder()->getConnection()->exec($sqlStatement);
    }

    /**
     * Retrieves an array of pageIds from mountPoints that allready have a queue entry.
     *
     * @param string $identifier identifier of the mount point
     * @return array pageIds from mountPoints that allready have a queue entry
     */
    public function findPageIdsOfExistingMountPagesByMountIdentifier(string $identifier) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        $resultSet = $queryBuilder
            ->select('item_uid')
            ->add('select', $queryBuilder->expr()->count('*', 'queueItemCount'), true)
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('pages_mountidentifier', $queryBuilder->createNamedParameter($identifier))
            )
            ->groupBy('item_uid')
            ->execute();

        $mountedPagesIdsWithQueueItems = [];
        while ($record = $resultSet->fetch()) {
            if ($record['queueItemCount'] > 0) {
                $mountedPagesIdsWithQueueItems[] = $record['item_uid'];
            }
        }

        return $mountedPagesIdsWithQueueItems;
    }

    /**
     * Retrieves an array of items for mount destinations mathed by root page ID, Mount Identifier and a list of mounted page IDs.
     *
     * @param int $rootPid
     * @param string $identifier identifier of the mount point
     * @param array $mountedPids An array of mounted page IDs
     * @return array
     */
    public function findAllIndexQueueItemsByRootPidAndMountIdentifierAndMountedPids(int $rootPid, string $identifier, array $mountedPids) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('root', $queryBuilder->createNamedParameter($rootPid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('item_type', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->in('item_uid', $mountedPids),
                $queryBuilder->expr()->eq('has_indexing_properties', $queryBuilder->createNamedParameter(1, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('pages_mountidentifier', $queryBuilder->createNamedParameter($identifier))
            )
            ->execute()->fetchAll();
    }

    /**
     * Updates has_indexing_properties field for given Item
     *
     * @param int $itemUid
     * @param bool $hasIndexingPropertiesFlag
     * @return int number of affected rows, 1 on success
     */
    public function updateHasIndexingPropertiesFlagByItemUid(int $itemUid, bool $hasIndexingPropertiesFlag): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->update($this->table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($itemUid, \PDO::PARAM_INT)))
            ->set('has_indexing_properties', $queryBuilder->createNamedParameter($hasIndexingPropertiesFlag, \PDO::PARAM_INT), false)
            ->execute();
    }
}
