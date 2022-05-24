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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic\QueueStatistic;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic\QueueStatisticsRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Indexing Queue. It allows us to decouple from frontend indexing and
 * reacting to the changes faster.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Queue
{
    /**
     * @var RootPageResolver
     */
    protected RootPageResolver $rootPageResolver;

    /**
     * @var ConfigurationAwareRecordService
     */
    protected ConfigurationAwareRecordService $recordService;

    /**
     * @var SolrLogManager
     */
    protected SolrLogManager $logger;

    /**
     * @var QueueItemRepository
     */
    protected QueueItemRepository $queueItemRepository;

    /**
     * @var QueueStatisticsRepository
     */
    protected QueueStatisticsRepository $queueStatisticsRepository;

    /**
     * @var QueueInitializationService
     */
    protected QueueInitializationService $queueInitializationService;

    /**
     * @var FrontendEnvironment
     */
    protected FrontendEnvironment $frontendEnvironment;

    /**
     * Queue constructor.
     * @param RootPageResolver|null $rootPageResolver
     * @param ConfigurationAwareRecordService|null $recordService
     * @param QueueItemRepository|null $queueItemRepository
     * @param QueueStatisticsRepository|null $queueStatisticsRepository
     * @param QueueInitializationService|null $queueInitializationService
     */
    public function __construct(
        RootPageResolver $rootPageResolver = null,
        ConfigurationAwareRecordService $recordService = null,
        QueueItemRepository $queueItemRepository = null,
        QueueStatisticsRepository $queueStatisticsRepository = null,
        QueueInitializationService $queueInitializationService = null,
        FrontendEnvironment $frontendEnvironment = null
    ) {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
        $this->rootPageResolver = $rootPageResolver ?? GeneralUtility::makeInstance(RootPageResolver::class);
        $this->recordService = $recordService ?? GeneralUtility::makeInstance(ConfigurationAwareRecordService::class);
        $this->queueItemRepository = $queueItemRepository ?? GeneralUtility::makeInstance(QueueItemRepository::class);
        $this->queueStatisticsRepository = $queueStatisticsRepository ??  GeneralUtility::makeInstance(QueueStatisticsRepository::class);
        $this->queueInitializationService = $queueInitializationService ?? GeneralUtility::makeInstance(QueueInitializationService::class, /** @scrutinizer ignore-type */ $this);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
    }

    // FIXME some of the methods should be renamed to plural forms
    // FIXME singular form methods should deal with exactly one item only

    /**
     * Returns the timestamp of the last indexing run.
     *
     * @param int $rootPageId The root page uid for which to get
     *      the last indexed item id
     * @return int Timestamp of last index run.
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function getLastIndexTime(int $rootPageId): int
    {
        $lastIndexTime = 0;

        $lastIndexedRow = $this->queueItemRepository->findLastIndexedRow($rootPageId);

        if ($lastIndexedRow[0]['indexed']) {
            $lastIndexTime = $lastIndexedRow[0]['indexed'];
        }

        return $lastIndexTime;
    }

    /**
     * Returns the uid of the last indexed item in the queue
     *
     * @param int $rootPageId The root page uid for which to get
     *      the last indexed item id
     * @return int The last indexed item's ID.
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function getLastIndexedItemId(int $rootPageId): int
    {
        $lastIndexedItemId = 0;

        $lastIndexedItemRow = $this->queueItemRepository->findLastIndexedRow($rootPageId);
        if ($lastIndexedItemRow[0]['uid']) {
            $lastIndexedItemId = $lastIndexedItemRow[0]['uid'];
        }

        return $lastIndexedItemId;
    }

    /**
     * @return QueueInitializationService
     */
    public function getInitializationService(): QueueInitializationService
    {
        return $this->queueInitializationService;
    }

    /**
     * Marks an item as needing (re)indexing.
     *
     * Like with Solr itself, there's no add method, just a simple update method
     * that handles the adds, too.
     *
     * The method creates or updates the index queue items for all related rootPageIds.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a different value for non-database-record types.
     * @param int $forcedChangeTime The change time for the item if set, otherwise value from getItemChangedTime() is used.
     * @return int Number of updated/created items
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     * @throws Throwable
     */
    public function updateItem(string $itemType, $itemUid, int $forcedChangeTime = 0): int
    {
        $updateCount = $this->updateOrAddItemForAllRelatedRootPages($itemType, $itemUid, $forcedChangeTime);
        return $this->postProcessIndexQueueUpdateItem($itemType, $itemUid, $updateCount, $forcedChangeTime);
    }

    /**
     * Updates or adds the item for all relevant root pages.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a different value for non-database-record types.
     * @param int $forcedChangeTime The change time for the item if set, otherwise value from getItemChangedTime() is used.
     * @return int
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     * @throws Throwable
     */
    protected function updateOrAddItemForAllRelatedRootPages(string $itemType, $itemUid, int $forcedChangeTime): int
    {
        $updateCount = 0;
        $rootPageIds = $this->rootPageResolver->getResponsibleRootPageIds($itemType, $itemUid);
        foreach ($rootPageIds as $rootPageId) {
            $skipInvalidRootPage = $rootPageId === 0;
            if ($skipInvalidRootPage) {
                continue;
            }

            /* @var SiteRepository $siteRepository */
            $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
            $solrConfiguration = $siteRepository->getSiteByRootPageId($rootPageId)->getSolrConfiguration();
            $indexingConfiguration = $this->recordService->getIndexingConfigurationName($itemType, $itemUid, $solrConfiguration);
            if ($indexingConfiguration === null) {
                continue;
            }
            $itemInQueueForRootPage = $this->containsItemWithRootPageId($itemType, $itemUid, $rootPageId);
            if ($itemInQueueForRootPage) {
                // update changed time if that item is in the queue already
                $changedTime = ($forcedChangeTime > 0) ? $forcedChangeTime : $this->getItemChangedTime($itemType, $itemUid);
                $updatedRows = $this->queueItemRepository->updateExistingItemByItemTypeAndItemUidAndRootPageId($itemType, $itemUid, $rootPageId, $changedTime, $indexingConfiguration);
            } else {
                // add the item since it's not in the queue yet
                $updatedRows = $this->addNewItem($itemType, $itemUid, $indexingConfiguration, $rootPageId);
            }

            $updateCount += $updatedRows;
        }

        return $updateCount;
    }

    /**
     * Executes the updateItem post-processing hook.
     *
     * @param string $itemType
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a different value for non-database-record types.
     * @param int $updateCount
     * @param int $forcedChangeTime
     * @return int
     */
    protected function postProcessIndexQueueUpdateItem(
        string $itemType,
        $itemUid,
        int $updateCount,
        int $forcedChangeTime = 0
    ): int {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueUpdateItem'] ?? null)) {
            return $updateCount;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueUpdateItem'] as $classReference) {
            $updateHandler = $this->getHookImplementation($classReference);
            $updateCount = $updateHandler->postProcessIndexQueueUpdateItem($itemType, $itemUid, $updateCount, $forcedChangeTime);
        }

        return $updateCount;
    }

    /**
     * @param string $classReference
     * @return object
     */
    protected function getHookImplementation(string $classReference): object
    {
        return GeneralUtility::makeInstance($classReference);
    }

    /**
     * Finds indexing errors for the current site
     *
     * @param Site $site
     * @return array Error items for the current site's Index Queue
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function getErrorsBySite(Site $site): array
    {
        return $this->queueItemRepository->findErrorsBySite($site);
    }

    /**
     * Resets all the errors for all index queue items.
     *
     * @return mixed
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function resetAllErrors()
    {
        return $this->queueItemRepository->flushAllErrors();
    }

    /**
     * Resets the errors in the index queue for a specific site
     *
     * @param Site $site
     * @return mixed
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function resetErrorsBySite(Site $site)
    {
        return $this->queueItemRepository->flushErrorsBySite($site);
    }

    /**
     * Resets the error in the index queue for a specific item
     *
     * @param Item $item
     * @return mixed
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function resetErrorByItem(Item $item)
    {
        return $this->queueItemRepository->flushErrorByItem($item);
    }

    /**
     * Adds an item to the index queue.
     *
     * Not meant for public use.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param string $indexingConfiguration The item's indexing configuration to use.
     *      Optional, overwrites existing / determined configuration.
     * @param int $rootPageId
     * @return int
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    private function addNewItem(
        string $itemType,
        $itemUid,
        string $indexingConfiguration,
        int $rootPageId
    ): int {
        $additionalRecordFields = '';
        if ($itemType === 'pages') {
            $additionalRecordFields = ', doktype, uid';
        }

        $record = $this->getRecordCached($itemType, $itemUid, $additionalRecordFields);

        if (empty($record) || ($itemType === 'pages' && !$this->frontendEnvironment->isAllowedPageType($record, $indexingConfiguration))) {
            return 0;
        }

        $changedTime = $this->getItemChangedTime($itemType, $itemUid);

        return $this->queueItemRepository->add($itemType, $itemUid, $rootPageId, $changedTime, $indexingConfiguration);
    }

    /**
     * Get record to be added in addNewItem
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param string $additionalRecordFields for sql-query
     *
     * @return array|null
     */
    protected function getRecordCached(string $itemType, $itemUid, string $additionalRecordFields): ?array
    {
        $cache = GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'runtime');
        $cacheId = md5('Queue' . ':' . 'getRecordCached' . ':' . $itemType . ':' . $itemUid . ':' . 'pid' . $additionalRecordFields);

        $record = $cache->get($cacheId);
        if (empty($record)) {
            $record = BackendUtility::getRecord($itemType, $itemUid, 'pid' . $additionalRecordFields);
            $cache->set($cacheId, $record);
        }

        return $record;
    }

    /**
     * Determines the time for when an item should be indexed. This timestamp
     * is then stored in the changed column in the Index Queue.
     *
     * The changed timestamp usually is now - time(). For records which are set
     * to published at a later time, this timestamp is the start time. So if a
     * future start time has been set, that will be used to delay indexing
     * of an item.
     *
     * @param string $itemType The item's table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @return int Timestamp of the item's changed time or future start time
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    protected function getItemChangedTime(string $itemType, $itemUid): int
    {
        $itemTypeHasStartTimeColumn = false;
        $changedTimeColumns = $GLOBALS['TCA'][$itemType]['ctrl']['tstamp'];
        $startTime = 0;
        $pageChangedTime = 0;

        if (!empty($GLOBALS['TCA'][$itemType]['ctrl']['enablecolumns']['starttime'])) {
            $itemTypeHasStartTimeColumn = true;
            $changedTimeColumns .= ', ' . $GLOBALS['TCA'][$itemType]['ctrl']['enablecolumns']['starttime'];
        }
        if ($itemType === 'pages') {
            // does not carry time information directly, but needed to support
            // canonical pages
            $changedTimeColumns .= ', content_from_pid';
        }

        $record = BackendUtility::getRecord($itemType, $itemUid, $changedTimeColumns);
        $itemChangedTime = $record[$GLOBALS['TCA'][$itemType]['ctrl']['tstamp']];

        if ($itemTypeHasStartTimeColumn) {
            $startTime = $record[$GLOBALS['TCA'][$itemType]['ctrl']['enablecolumns']['starttime']];
        }

        if ($itemType === 'pages') {
            $record['uid'] = $itemUid;
            // overrule the page's last changed time with the most recent
            //content element change
            $pageChangedTime = $this->getPageItemChangedTime($record);
        }

        $localizationsChangedTime = $this->queueItemRepository->getLocalizableItemChangedTime($itemType, (int)$itemUid);

        // if start time exists and start time is higher than last changed timestamp
        // then set changed to the future start time to make the item
        // indexed at a later time
        return (int)max(
            $itemChangedTime,
            $pageChangedTime,
            $localizationsChangedTime,
            $startTime
        );
    }

    /**
     * Gets the most recent changed time of a page's content elements
     *
     * @param array $page Partial page record
     * @return int Timestamp of the most recent content element change
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    protected function getPageItemChangedTime(array $page): int
    {
        if (!empty($page['content_from_pid'])) {
            // canonical page, get the original page's last changed time
            return $this->queueItemRepository->getPageItemChangedTimeByPageUid((int)$page['content_from_pid']);
        }
        return $this->queueItemRepository->getPageItemChangedTimeByPageUid((int)$page['uid']) ?? 0;
    }

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @return bool TRUE if the item is found in the queue, FALSE otherwise
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function containsItem(string $itemType, $itemUid): bool
    {
        return $this->queueItemRepository->containsItem($itemType, (int)$itemUid);
    }

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param int $rootPageId
     * @return bool TRUE if the item is found in the queue, FALSE otherwise
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function containsItemWithRootPageId(string $itemType, $itemUid, int $rootPageId): bool
    {
        return $this->queueItemRepository->containsItemWithRootPageId($itemType, (int)$itemUid, $rootPageId);
    }

    /**
     * Checks whether the Index Queue contains a specific item that has been
     * marked as indexed.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @return bool TRUE if the item is found in the queue and marked as
     *      indexed, FALSE otherwise
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function containsIndexedItem(string $itemType, $itemUid): bool
    {
        return $this->queueItemRepository->containsIndexedItem($itemType, (int)$itemUid);
    }

    /**
     * Removes an item from the Index Queue.
     *
     * @param string $itemType The type of the item to remove, usually a table name.
     * @param int|string $itemUid The uid of the item to remove
     * @throws ConnectionException
     * @throws DBALException
     * @throws Throwable
     */
    public function deleteItem(string $itemType, $itemUid)
    {
        $this->queueItemRepository->deleteItem($itemType, (int)$itemUid);
    }

    /**
     * Removes all items of a certain type from the Index Queue.
     *
     * @param string $itemType The type of items to remove, usually a table name.
     * @throws ConnectionException
     * @throws DBALException
     * @throws Throwable
     */
    public function deleteItemsByType(string $itemType)
    {
        $this->queueItemRepository->deleteItemsByType($itemType);
    }

    /**
     * Removes all items of a certain site from the Index Queue. Accepts an
     * optional parameter to limit the deleted items by indexing configuration.
     *
     * @param Site $site The site to remove items for.
     * @param string $indexingConfigurationName Name of a specific indexing
     *      configuration
     * @throws ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws Throwable
     */
    public function deleteItemsBySite(Site $site, string $indexingConfigurationName = '')
    {
        $this->queueItemRepository->deleteItemsBySite($site, $indexingConfigurationName);
    }

    /**
     * Removes all items from the Index Queue.
     */
    public function deleteAllItems()
    {
        $this->queueItemRepository->deleteAllItems();
    }

    /**
     * Gets a single Index Queue item by its uid.
     *
     * @param int $itemId Index Queue item uid
     * @return Item|null The request Index Queue item or NULL if no item with $itemId was found
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function getItem(int $itemId): ?Item
    {
        return $this->queueItemRepository->findItemByUid($itemId);
    }

    /**
     * Gets Index Queue items by type and uid.
     *
     * @param string $itemType item type, usually  the table name
     * @param int|string $itemUid item uid
     * @return Item[] An array of items matching $itemType and $itemUid
     * @throws ConnectionException
     * @throws DBALDriverException
     * @throws DBALException
     * @throws Throwable
     */
    public function getItems(string $itemType, $itemUid): array
    {
        return $this->queueItemRepository->findItemsByItemTypeAndItemUid($itemType, (int)$itemUid);
    }

    /**
     * Returns all items in the queue.
     *
     * @return Item[] An array of items
     * @throws ConnectionException
     * @throws DBALDriverException
     * @throws DBALException
     * @throws Throwable
     */
    public function getAllItems(): array
    {
        return $this->queueItemRepository->findAll();
    }

    /**
     * Returns the number of items for all queues.
     *
     * @return int
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function getAllItemsCount(): int
    {
        return $this->queueItemRepository->count();
    }

    /**
     * Extracts the number of pending, indexed and erroneous items from the
     * Index Queue.
     *
     * @param Site $site
     * @param string $indexingConfigurationName
     *
     * @return QueueStatistic
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function getStatisticsBySite(Site $site, string $indexingConfigurationName = ''): QueueStatistic
    {
        return $this->queueStatisticsRepository
            ->findOneByRootPidAndOptionalIndexingConfigurationName(
                $site->getRootPageId(),
                $indexingConfigurationName
            );
    }

    /**
     * Gets $limit number of items to index for a particular $site.
     *
     * @param Site $site TYPO3 site
     * @param int $limit Number of items to get from the queue
     * @return Item[] Items to index to the given solr server
     * @throws ConnectionException
     * @throws DBALDriverException
     * @throws DBALException
     * @throws Throwable
     */
    public function getItemsToIndex(Site $site, int $limit = 50): array
    {
        return $this->queueItemRepository->findItemsToIndex($site, $limit);
    }

    /**
     * Marks an item as failed and causes the indexer to skip the item in the
     * next run.
     *
     * @param int|Item $item Either the item's Index Queue uid or the complete item
     * @param string $errorMessage Error message
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function markItemAsFailed($item, string $errorMessage = '')
    {
        $this->queueItemRepository->markItemAsFailed($item, $errorMessage);
    }

    /**
     * Sets the timestamp of when an item last has been indexed.
     *
     * @param Item $item
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function updateIndexTimeByItem(Item $item)
    {
        $this->queueItemRepository->updateIndexTimeByItem($item);
    }

    /**
     * Sets the change timestamp of an item.
     *
     * @param Item $item
     * @param int $forcedChangeTime The change time for the item
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function setForcedChangeTimeByItem(Item $item, int $forcedChangeTime = 0)
    {
        $this->queueItemRepository->updateChangedTimeByItem($item, $forcedChangeTime);
    }
}
