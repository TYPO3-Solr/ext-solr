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
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\IndexQueue\QueueInterface;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception as DBALException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service to perform indexing operations
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
     * @throws ConnectionException
     * @throws DBALException
     */
    public function indexItems(int $limit): bool
    {
        $errors     = 0;
        $indexRunId = uniqid();
        $configurationToUse = $this->site->getSolrConfiguration();
        $enableCommitsSetting = $configurationToUse->getEnableCommits();

        // get items to index
        $itemsToIndex = $this->indexQueue->getItemsToIndex($this->site, $limit);

        $beforeIndexItemsEvent = new BeforeItemsAreIndexedEvent($itemsToIndex, $this->getContextTask(), $indexRunId);
        $beforeIndexItemsEvent = $this->eventDispatcher->dispatch($beforeIndexItemsEvent);
        $itemsToIndex = $beforeIndexItemsEvent->getItems();

        foreach ($itemsToIndex as $itemToIndex) {
            try {
                // try indexing
                $beforeIndexItemEvent = new BeforeItemIsIndexedEvent($itemToIndex, $this->getContextTask(), $indexRunId);
                $beforeIndexItemEvent = $this->eventDispatcher->dispatch($beforeIndexItemEvent);
                $itemToIndex = $beforeIndexItemEvent->getItem();
                $this->indexItem($itemToIndex, $configurationToUse);
                $afterIndexItemEvent = new AfterItemHasBeenIndexedEvent($itemToIndex, $this->getContextTask(), $indexRunId);
                $this->eventDispatcher->dispatch($afterIndexItemEvent);
            } catch (Throwable $e) {
                $errors++;
                $this->indexQueue->markItemAsFailed($itemToIndex, $e->getCode() . ': ' . $e->__toString());
                $this->generateIndexingErrorLog($itemToIndex, $e);
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
     * Generates a message in the error log when an error occurred.
     */
    protected function generateIndexingErrorLog(Item $itemToIndex, Throwable $e): void
    {
        $message = 'Failed indexing Index Queue item ' . $itemToIndex->getIndexQueueUid();
        $data = ['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'item' => (array)$itemToIndex];

        $this->logger->error($message, $data);
    }

    /**
     * Indexes an item from the Index Queue.
     *
     * @return bool TRUE if the item was successfully indexed, FALSE otherwise
     *
     * @throws Throwable
     */
    protected function indexItem(Item $item, TypoScriptConfiguration $configuration): bool
    {
        $indexer = $this->getIndexerByItem($item->getIndexingConfigurationName(), $configuration);
        // Remember original http host value
        $originalHttpHost = $_SERVER['HTTP_HOST'] ?? null;

        $itemChangedDate = $item->getChanged();
        $itemChangedDateAfterIndex = 0;

        try {
            $this->initializeHttpServerEnvironment($item);
            $itemIndexed = $indexer->index($item);

            // update IQ item so that the IQ can determine what's been indexed already
            if ($itemIndexed) {
                $this->indexQueue->updateIndexTimeByItem($item);
                $itemChangedDateAfterIndex = $item->getChanged();
            }

            if ($itemChangedDateAfterIndex > $itemChangedDate && $itemChangedDateAfterIndex > time()) {
                $this->indexQueue->setForcedChangeTimeByItem($item, $itemChangedDateAfterIndex);
            }
        } catch (Throwable $e) { // @todo: wrap with EX:solr exception
            $this->restoreOriginalHttpHost($originalHttpHost);
            throw $e;
        }

        $this->restoreOriginalHttpHost($originalHttpHost);

        return $itemIndexed;
    }

    /**
     * A factory method to get an indexer depending on an item's configuration.
     *
     * By default, all items are indexed using the default indexer
     * (ApacheSolrForTypo3\Solr\IndexQueue\Indexer) coming with EXT:solr. Pages by default are
     * configured to be indexed through a dedicated indexer
     * (ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer). In all other cases a dedicated indexer
     * can be specified through TypoScript if needed.
     */
    protected function getIndexerByItem(
        string $indexingConfigurationName,
        TypoScriptConfiguration $configuration,
    ): Indexer {
        $indexerClass = $configuration->getIndexQueueIndexerByConfigurationName($indexingConfigurationName);
        $indexerConfiguration = $configuration->getIndexQueueIndexerConfigurationByConfigurationName($indexingConfigurationName);

        $indexer = GeneralUtility::makeInstance($indexerClass, $indexerConfiguration);
        if (!($indexer instanceof Indexer)) {
            throw new RuntimeException(
                'The indexer class "' . $indexerClass . '" for indexing configuration "' . $indexingConfigurationName . '" is not a valid indexer. Must be a subclass of ApacheSolrForTypo3\Solr\IndexQueue\Indexer.',
                1260463206,
            );
        }

        return $indexer;
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

    /**
     * Initializes the $_SERVER['HTTP_HOST'] environment variable in CLI
     * environments dependent on the Index Queue item's root page.
     *
     * When the Index Queue Worker task is executed by a cron job there is no
     * HTTP_HOST since we are in a CLI environment. RealURL needs the host
     * information to generate a proper URL though. Using the Index Queue item's
     * root page information we can determine the correct host although being
     * in a CLI environment.
     *
     * @throws DBALException
     */
    protected function initializeHttpServerEnvironment(Item $item): void
    {
        static $hosts = [];
        $rootPageId = $item->getRootPageUid();
        $hostFound = !empty($hosts[$rootPageId]);

        if (!$hostFound) {
            $hosts[$rootPageId] = $item->getSite()->getDomain();
        }

        $_SERVER['HTTP_HOST'] = $hosts[$rootPageId];

        // needed since TYPO3 7.5
        GeneralUtility::flushInternalRuntimeCaches();
    }

    protected function restoreOriginalHttpHost(?string $originalHttpHost): void
    {
        if (!is_null($originalHttpHost)) {
            $_SERVER['HTTP_HOST'] = $originalHttpHost;
        } else {
            unset($_SERVER['HTTP_HOST']);
        }

        // needed since TYPO3 7.5
        GeneralUtility::flushInternalRuntimeCaches();
    }
}
