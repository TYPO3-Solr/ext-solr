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
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Exception\RootPageRecordNotFoundException;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic\QueueStatistic;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic\QueueStatisticsRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Event\IndexQueue\AfterIndexQueueItemHasBeenMarkedForReindexingEvent;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Doctrine\DBAL\Exception as DBALException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Queue makes it possible to decouple the direct indexing of changed records and index them time-delayed in standalone process.
 */
class Queue implements QueueInterface, QueueInitializationServiceAwareInterface
{
    protected RootPageResolver $rootPageResolver;

    protected ConfigurationAwareRecordService $recordService;

    protected SolrLogManager $logger;

    protected QueueItemRepository $queueItemRepository;

    protected QueueStatisticsRepository $queueStatisticsRepository;

    protected QueueInitializationService $queueInitializationService;

    protected FrontendEnvironment $frontendEnvironment;

    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ?RootPageResolver $rootPageResolver = null,
        ?ConfigurationAwareRecordService $recordService = null,
        ?QueueItemRepository $queueItemRepository = null,
        ?QueueStatisticsRepository $queueStatisticsRepository = null,
        ?FrontendEnvironment $frontendEnvironment = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
        $this->rootPageResolver = $rootPageResolver ?? GeneralUtility::makeInstance(RootPageResolver::class);
        $this->recordService = $recordService ?? GeneralUtility::makeInstance(ConfigurationAwareRecordService::class);
        $this->queueItemRepository = $queueItemRepository ?? GeneralUtility::makeInstance(QueueItemRepository::class);
        $this->queueStatisticsRepository = $queueStatisticsRepository ??  GeneralUtility::makeInstance(QueueStatisticsRepository::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Returns the timestamp of the last indexing run.
     *
     * @throws DBALException
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
     * @throws DBALException
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

    public function setQueueInitializationService(QueueInitializationService $queueInitializationService): void
    {
        $this->queueInitializationService = $queueInitializationService;
    }

    /**
     * Returns the QueueInitializationService
     */
    public function getQueueInitializationService(): QueueInitializationService
    {
        if (!isset($this->queueInitializationService)) {
            trigger_error(
                'queueInitializationService is no longer initalized automatically, till EXT:solr supports DI'
                . ' the QueueInitializationService has to be set manually, fallback will be removed in v13.',
                E_USER_DEPRECATED,
            );
            $this->queueInitializationService = GeneralUtility::makeInstance(QueueInitializationService::class);
        }

        return $this->queueInitializationService;
    }

    /**
     * @deprecated Queue->getInitializationService is deprecated and will be removed in v12.
     *             Use Queue->getQueueInitializationService instead or create a fresh instance.
     */
    public function getInitializationService(): QueueInitializationService
    {
        trigger_error(
            'Queue->getInitializationService is deprecated and will be removed in v13.'
            . ' Use Queue->getQueueInitializationService instead or create a fresh instance.',
            E_USER_DEPRECATED,
        );

        return $this->getQueueInitializationService();
    }

    /**
     * Marks an item as needing (re)indexing.
     *
     * Like with Solr itself, there's no add method, just a simple update method
     * that handles the adds, too.
     *
     * The method creates or updates the index queue items for all related rootPageIds.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function updateItem(
        string $itemType,
        int|string $itemUid,
        int $forcedChangeTime = 0,
        ?array $validLanguageUids = null,
    ): int {
        $updateCount = $this->updateOrAddItemForAllRelatedRootPages($itemType, $itemUid, $forcedChangeTime);
        $event = new AfterIndexQueueItemHasBeenMarkedForReindexingEvent($itemType, $itemUid, $forcedChangeTime, $updateCount, $validLanguageUids);
        $event = $this->eventDispatcher->dispatch($event);
        return $event->getUpdateCount();
    }

    /**
     * Updates or adds the item for all relevant root pages.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function updateOrAddItemForAllRelatedRootPages(
        string $itemType,
        int $itemUid,
        int $forcedChangeTime,
    ): int {
        $updateCount = 0;
        try {
            $rootPageIds = $this->rootPageResolver->getResponsibleRootPageIds($itemType, (int)$itemUid);
        } catch (RootPageRecordNotFoundException $e) {
            $this->deleteItem($itemType, $itemUid);
            return 0;
        }

        /** @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        foreach ($rootPageIds as $rootPageId) {
            $skipInvalidRootPage = $rootPageId === 0;
            if ($skipInvalidRootPage) {
                continue;
            }

            $site = $siteRepository->getSiteByRootPageId($rootPageId);
            if ($site === null) {
                continue;
            }

            $solrConfiguration = $site->getSolrConfiguration();
            $indexingConfiguration = $this->recordService->getIndexingConfigurationName($itemType, $itemUid, $solrConfiguration);
            if ($indexingConfiguration === null) {
                continue;
            }
            $indexingPriority = $solrConfiguration->getIndexQueueIndexingPriorityByConfigurationName($indexingConfiguration);
            $itemInQueueForRootPage = $this->containsItemWithRootPageId($itemType, $itemUid, $rootPageId, $indexingConfiguration);
            if ($itemInQueueForRootPage) {
                // update changed time if that item is in the queue already
                $changedTime = ($forcedChangeTime > 0) ? $forcedChangeTime : $this->getItemChangedTime($itemType, (int)$itemUid);
                $updatedRows = $this->queueItemRepository->updateExistingItemByItemTypeAndItemUidAndRootPageId($itemType, (int)$itemUid, $rootPageId, $changedTime, $indexingConfiguration, $indexingPriority);
            } else {
                // add the item since it's not in the queue yet
                $updatedRows = $this->addNewItem($itemType, $itemUid, $indexingConfiguration, $rootPageId, $indexingPriority);
            }

            $updateCount += $updatedRows;
        }

        return $updateCount;
    }

    /**
     * Returns errored item records for given site.
     *
     * @throws DBALException
     */
    public function getErrorsBySite(Site $site): array
    {
        return $this->queueItemRepository->findErrorsBySite($site);
    }

    /**
     * Resets all the errors for all index queue items.
     */
    public function resetAllErrors(): int
    {
        return $this->queueItemRepository->flushAllErrors();
    }

    /**
     * Resets the errors in the index queue for a specific site
     */
    public function resetErrorsBySite(Site $site): int
    {
        return $this->queueItemRepository->flushErrorsBySite($site);
    }

    /**
     * Resets the error in the index queue for a specific item
     */
    public function resetErrorByItem(ItemInterface $item): int
    {
        return $this->queueItemRepository->flushErrorByItem($item);
    }

    /**
     * Adds an item to the index queue.
     * Not meant for public use.
     *
     * @throws DBALException
     */
    private function addNewItem(
        string $itemType,
        int $itemUid,
        string $indexingConfiguration,
        int $rootPageId,
        int $indexingPriority = 0,
    ): int {
        $additionalRecordFields = '';
        if ($itemType === 'pages') {
            $additionalRecordFields = ', doktype, uid';
        }

        $record = $this->getRecordCached($itemType, (int)$itemUid, $additionalRecordFields);

        if (empty($record) || ($itemType === 'pages' && !$this->frontendEnvironment->isAllowedPageType($record, $indexingConfiguration))) {
            return 0;
        }

        $changedTime = $this->getItemChangedTime($itemType, (int)$itemUid);

        return $this->queueItemRepository->add($itemType, (int)$itemUid, $rootPageId, $changedTime, $indexingConfiguration, $indexingPriority);
    }

    /**
     * Get record to be added in addNewItem
     */
    protected function getRecordCached(
        string $itemType,
        int $itemUid,
        string $additionalRecordFields,
    ): ?array {
        $cache = GeneralUtility::makeInstance(TwoLevelCache::class, 'runtime');
        $cacheId = hash('md5', 'Queue' . ':' . 'getRecordCached' . ':' . $itemType . ':' . (string)$itemUid . ':' . 'pid' . $additionalRecordFields);

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
     * @throws DBALException
     */
    protected function getItemChangedTime(
        string $itemType,
        int $itemUid,
    ): int {
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

        $localizationsChangedTime = $this->queueItemRepository->getLocalizableItemChangedTime($itemType, $itemUid);

        // if start time exists and start time is higher than last changed timestamp
        // then set changed to the future start time to make the item
        // indexed at a later time
        return (int)max(
            $itemChangedTime,
            $pageChangedTime,
            $localizationsChangedTime,
            $startTime,
        );
    }

    /**
     * Gets the most recent changed time of a page's content elements
     *
     * @throws DBALException
     */
    protected function getPageItemChangedTime(array $page): int
    {
        if (!empty($page['content_from_pid'])) {
            // canonical page, get the original page's last changed time
            return $this->queueItemRepository->getPageItemChangedTimeByPageUid((int)$page['content_from_pid']) ?? 0;
        }
        return $this->queueItemRepository->getPageItemChangedTimeByPageUid((int)$page['uid']) ?? 0;
    }

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @throws DBALException
     */
    public function containsItem(
        string $itemType,
        int|string $itemUid,
    ): bool {
        return $this->queueItemRepository->containsItem($itemType, (int)$itemUid);
    }

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @throws DBALException
     */
    public function containsItemWithRootPageId(
        string $itemType,
        int|string $itemUid,
        int $rootPageId,
        string $indexingConfiguration,
    ): bool {
        return $this->queueItemRepository->containsItemWithRootPageId($itemType, (int)$itemUid, $rootPageId, $indexingConfiguration);
    }

    /**
     * Checks whether the Index Queue contains a specific item that has been
     * marked as indexed.
     *
     * @throws DBALException
     */
    public function containsIndexedItem(
        string $itemType,
        int|string $itemUid,
    ): bool {
        return $this->queueItemRepository->containsIndexedItem($itemType, (int)$itemUid);
    }

    /**
     * Removes an item from the Index Queue.
     *
     * @throws DBALException
     */
    public function deleteItem(
        string $itemType,
        int|string $itemUid,
    ): void {
        $this->queueItemRepository->deleteItem($itemType, (int)$itemUid);
    }

    /**
     * Removes all items of a certain type from the Index Queue.
     *
     * @throws DBALException
     */
    public function deleteItemsByType(string $itemType): void
    {
        $this->queueItemRepository->deleteItemsByType($itemType);
    }

    /**
     * Removes all items of a certain site from the Index Queue. Accepts an
     * optional parameter to limit the deleted items by indexing configuration.
     *
     * @throws DBALException
     */
    public function deleteItemsBySite(
        Site $site,
        string $indexingConfigurationName = '',
    ): void {
        $this->queueItemRepository->deleteItemsBySite($site, $indexingConfigurationName);
    }

    /**
     * Removes all items from the Index Queue.
     */
    public function deleteAllItems(): int
    {
        return $this->queueItemRepository->deleteAllItems();
    }

    /**
     * Gets a single Index Queue item by its uid.
     *
     * @throws DBALException
     */
    public function getItem(int|string $itemId): ?Item
    {
        return $this->queueItemRepository->findItemByUid($itemId);
    }

    /**
     * Gets Index Queue items by type and uid.
     *
     * @return Item[] An array of items matching $itemType and $itemUid
     *
     * @throws DBALException
     */
    public function getItems(
        string $itemType,
        int|string $itemUid,
    ): array {
        return $this->queueItemRepository->findItemsByItemTypeAndItemUid($itemType, (int)$itemUid);
    }

    /**
     * Returns all items in the queue.
     *
     * @return Item[] An array of items
     *
     * @throws DBALException
     */
    public function getAllItems(): array
    {
        return $this->queueItemRepository->findAll();
    }

    /**
     * Returns the number of items for all queues.
     *
     * @throws DBALException
     */
    public function getAllItemsCount(): int
    {
        return $this->queueItemRepository->count();
    }

    /**
     * Extracts the number of pending, indexed and erroneous items from the Index Queue.
     *
     * @throws DBALException
     */
    public function getStatisticsBySite(Site $site, string $indexingConfigurationName = ''): QueueStatistic
    {
        return $this->queueStatisticsRepository
            ->findOneByRootPidAndOptionalIndexingConfigurationName(
                $site->getRootPageId(),
                $indexingConfigurationName,
            );
    }

    /**
     * Gets $limit number of items to index for a particular $site.
     *
     * @return Item[] Items to index to the given solr server
     *
     * @throws DBALException
     */
    public function getItemsToIndex(Site $site, int $limit = 50): array
    {
        return $this->queueItemRepository->findItemsToIndex($site, $limit);
    }

    /**
     * Marks an item as failed and causes the indexer to skip the item in the
     * next run.
     */
    public function markItemAsFailed(ItemInterface|int|null $item, string $errorMessage = ''): int
    {
        return $this->queueItemRepository->markItemAsFailed($item, $errorMessage);
    }

    /**
     * Sets the timestamp of when an item last has been indexed.
     */
    public function updateIndexTimeByItem(ItemInterface $item): int
    {
        return $this->queueItemRepository->updateIndexTimeByItem($item);
    }

    /**
     * Sets the change timestamp of an item.
     */
    public function setForcedChangeTimeByItem(ItemInterface $item, int $forcedChangeTime = 0): int
    {
        return $this->queueItemRepository->updateChangedTimeByItem($item, $forcedChangeTime);
    }
}
