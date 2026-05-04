<?php

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

namespace ApacheSolrForTypo3\Solr\Domain\Index;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Event\Indexing\AfterItemHasBeenIndexedEvent;
use ApacheSolrForTypo3\Solr\Event\Indexing\AfterItemsHaveBeenIndexedEvent;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeItemIsIndexedEvent;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeItemsAreIndexedEvent;
use ApacheSolrForTypo3\Solr\Exception\InvalidConnectionException;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingService;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\IndexQueue\QueueInterface;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service to perform indexing operations.
 *
 * This is the top-level orchestration layer. It fetches items from the queue,
 * groups them by item_pid for batched processing, dispatches before/after events,
 * and delegates the actual indexing to IndexingService via sub-requests.
 */
class IndexService
{
    protected Site $site;

    protected ?IndexQueueWorkerTask $contextTask = null;

    protected QueueInterface $indexQueue;

    protected EventDispatcherInterface $eventDispatcher;

    protected SolrLogManager $logger;

    public function __construct(
        Site $site,
        ?QueueInterface $queue = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?SolrLogManager $solrLogManager = null,
    ) {
        $this->site = $site;
        $this->indexQueue = $queue ?? GeneralUtility::makeInstance(Queue::class);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
    }

    public function setContextTask(IndexQueueWorkerTask $contextTask): void
    {
        $this->contextTask = $contextTask;
    }

    public function getContextTask(): ?IndexQueueWorkerTask
    {
        return $this->contextTask;
    }

    /**
     * Indexes items from the Index Queue.
     *
     * Groups items by item_pid for batched sub-request processing.
     * Pages are processed individually (one sub-request per page).
     * Records sharing the same pid are batched into a single sub-request.
     *
     * @throws ContainerExceptionInterface
     * @throws DBALException
     * @throws InvalidConnectionException
     * @throws NotFoundExceptionInterface
     */
    public function indexItems(int $limit): bool
    {
        $errors = 0;
        $indexRunId = uniqid();
        $configurationToUse = $this->site->getSolrConfiguration();
        $enableCommitsSetting = $configurationToUse->getEnableCommits();

        // get items to index
        $itemsToIndex = $this->indexQueue->getItemsToIndex($this->site, $limit);

        $beforeIndexItemsEvent = new BeforeItemsAreIndexedEvent($itemsToIndex, $this->getContextTask(), $indexRunId);
        $beforeIndexItemsEvent = $this->eventDispatcher->dispatch($beforeIndexItemsEvent);
        $itemsToIndex = $beforeIndexItemsEvent->getItems();

        // Group items by item_pid for batched processing
        $groupedItems = $this->groupItemsByPid($itemsToIndex);

        /** @var IndexingService $indexingService */
        $indexingService = GeneralUtility::getContainer()->get(IndexingService::class);

        foreach ($groupedItems as $groupKey => $groupItems) {
            foreach ($groupItems as $itemToIndex) {
                try {
                    // Dispatch per-item event
                    $beforeIndexItemEvent = new BeforeItemIsIndexedEvent($itemToIndex, $this->getContextTask(), $indexRunId);
                    $beforeIndexItemEvent = $this->eventDispatcher->dispatch($beforeIndexItemEvent);
                    $itemToIndex = $beforeIndexItemEvent->getItem();

                    $itemIndexed = $indexingService->indexItems([$itemToIndex]);

                    if ($itemIndexed) {
                        $this->indexQueue->updateIndexTimeByItem($itemToIndex);
                        $itemChangedDateAfterIndex = $itemToIndex->getChanged();
                        if ($itemChangedDateAfterIndex > $itemToIndex->getChanged() && $itemChangedDateAfterIndex > time()) {
                            $this->indexQueue->setForcedChangeTimeByItem($itemToIndex, $itemChangedDateAfterIndex);
                        }
                    }

                    $afterIndexItemEvent = new AfterItemHasBeenIndexedEvent($itemToIndex, $this->getContextTask(), $indexRunId);
                    $this->eventDispatcher->dispatch($afterIndexItemEvent);
                } catch (Throwable $e) {
                    $errors++;
                    $this->indexQueue->markItemAsFailed($itemToIndex, $e->getCode() . ': ' . $e->__toString());
                    $this->generateIndexingErrorLog($itemToIndex, $e);
                }
            }
        }

        $afterIndexItemsEvent = new AfterItemsHaveBeenIndexedEvent($itemsToIndex, $this->getContextTask(), $indexRunId);
        $this->eventDispatcher->dispatch($afterIndexItemsEvent);

        if ($enableCommitsSetting && count($itemsToIndex) > 0) {
            $solrServers = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionsBySite($this->site);
            foreach ($solrServers as $solrServer) {
                $response = $solrServer->getWriteService()->commit(false, false);
                if ($response->getHttpStatus() !== 200) {
                    $errors++;
                }
            }
        }

        return $errors === 0;
    }

    /**
     * Groups items by their item_pid value for batched processing.
     * Pages are grouped by their own uid (one per group).
     * Records are grouped by their pid.
     *
     * @param Item[] $items
     * @return array<string, Item[]> Items grouped by a key based on type and pid
     */
    protected function groupItemsByPid(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            if ($item->getType() === 'pages') {
                // Pages are always processed individually
                $key = 'pages:' . $item->getRecordUid();
            } else {
                // Records are grouped by their item_pid
                $pid = $item->getItemPid();
                if ($pid === 0) {
                    // Fallback for items without item_pid set
                    $pid = $item->getRecordPageId() ?? 0;
                }
                $key = $item->getType() . ':pid:' . $pid;
            }
            $groups[$key][] = $item;
        }
        return $groups;
    }

    /**
     * Generates a message in the error log when an error occurred.
     */
    protected function generateIndexingErrorLog(Item $itemToIndex, Throwable $e): void
    {
        $message = 'Failed indexing Index Queue item ' . $itemToIndex->getIndexQueueUid();
        $data = ['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'item' => (array)$itemToIndex];

        $this->logger->error($message, $data);
    }

    /**
     * Gets the indexing progress as a two decimal precision float. f.e. 44.87
     *
     * @throws DBALException
     */
    public function getProgress(): float
    {
        return $this->indexQueue->getStatisticsBySite($this->site)->getSuccessPercentage();
    }

    /**
     * Returns the amount of failed queue items for the current site.
     *
     * @throws DBALException
     */
    public function getFailCount(): int
    {
        return $this->indexQueue->getStatisticsBySite($this->site)->getFailedCount();
    }
}
