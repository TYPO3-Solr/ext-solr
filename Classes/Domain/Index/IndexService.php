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
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask;
use RuntimeException;
use Solarium\Exception\HttpException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException;

/**
 * Service to perform indexing operations
 *
 * @author Timo Hund <timo.schmidt@dkd.de>
 */
class IndexService
{
    /**
     * @var Site
     */
    protected Site $site;

    /**
     * @var IndexQueueWorkerTask|null
     */
    protected ?IndexQueueWorkerTask $contextTask = null;

    /**
     * @var Queue
     */
    protected Queue $indexQueue;

    /**
     * @var Dispatcher
     */
    protected $signalSlotDispatcher;

    /**
     * @var SolrLogManager
     */
    protected SolrLogManager $logger;

    /**
     * IndexService constructor.
     * @param Site $site
     * @param Queue|null $queue
     * @param Dispatcher|null $dispatcher
     * @param SolrLogManager|null $solrLogManager
     */
    public function __construct(
        Site $site,
        Queue $queue = null,
        Dispatcher $dispatcher = null,
        SolrLogManager $solrLogManager = null
    ) {
        $this->site = $site;
        $this->indexQueue = $queue ?? GeneralUtility::makeInstance(Queue::class);
        $this->signalSlotDispatcher = $dispatcher ?? GeneralUtility::makeInstance(Dispatcher::class);
        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
    }

    /**
     * @param IndexQueueWorkerTask $contextTask
     */
    public function setContextTask(IndexQueueWorkerTask $contextTask)
    {
        $this->contextTask = $contextTask;
    }

    /**
     * @return IndexQueueWorkerTask
     */
    public function getContextTask(): ?IndexQueueWorkerTask
    {
        return $this->contextTask;
    }

    /**
     * Indexes items from the Index Queue.
     *
     * @param int $limit
     * @return bool
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     */
    public function indexItems(int $limit): bool
    {
        $errors     = 0;
        $indexRunId = uniqid();
        $configurationToUse = $this->site->getSolrConfiguration();
        $enableCommitsSetting = $configurationToUse->getEnableCommits();

        // get items to index
        $itemsToIndex = $this->indexQueue->getItemsToIndex($this->site, $limit);

        $this->emitSignal('beforeIndexItems', [$itemsToIndex, $this->getContextTask(), $indexRunId]);

        foreach ($itemsToIndex as $itemToIndex) {
            try {
                // try indexing
                $this->emitSignal('beforeIndexItem', [$itemToIndex, $this->getContextTask(), $indexRunId]);
                $this->indexItem($itemToIndex, $configurationToUse);
                $this->emitSignal('afterIndexItem', [$itemToIndex, $this->getContextTask(), $indexRunId]);
            } catch (Throwable $e) {
                $errors++;
                $this->indexQueue->markItemAsFailed($itemToIndex, $e->getCode() . ': ' . $e->__toString());
                $this->generateIndexingErrorLog($itemToIndex, $e);
            }
        }

        $this->emitSignal('afterIndexItems', [$itemsToIndex, $this->getContextTask(), $indexRunId]);

        if ($enableCommitsSetting && count($itemsToIndex) > 0) {
            $solrServers = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionsBySite($this->site);
            foreach ($solrServers as $solrServer) {
                try {
                    $solrServer->getWriteService()->commit(false, false);
                } catch (HttpException $e) {
                    $errors++;
                }
            }
        }

        return $errors === 0;
    }

    /**
     * Generates a message in the error log when an error occurred.
     *
     * @param Item $itemToIndex
     * @param Throwable $e
     */
    protected function generateIndexingErrorLog(Item $itemToIndex, Throwable $e)
    {
        $message = 'Failed indexing Index Queue item ' . $itemToIndex->getIndexQueueUid();
        $data = ['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'item' => (array)$itemToIndex];

        $this->logger->log(
            SolrLogManager::ERROR,
            $message,
            $data
        );
    }

    /**
     * Builds an emits a signal for the IndexService.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     */
    protected function emitSignal(string $name, array $arguments = [])
    {
        return $this->signalSlotDispatcher->dispatch(__CLASS__, $name, $arguments);
    }

    /**
     * Indexes an item from the Index Queue.
     *
     * @param Item $item An index queue item to index
     * @param TypoScriptConfiguration $configuration
     * @return bool TRUE if the item was successfully indexed, FALSE otherwise
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
        } catch (Throwable $e) {
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
     *
     * @param string $indexingConfigurationName Indexing configuration name.
     * @param TypoScriptConfiguration $configuration
     * @return Indexer
     */
    protected function getIndexerByItem(
        string $indexingConfigurationName,
        TypoScriptConfiguration $configuration
    ): Indexer {
        $indexerClass = $configuration->getIndexQueueIndexerByConfigurationName($indexingConfigurationName);
        $indexerConfiguration = $configuration->getIndexQueueIndexerConfigurationByConfigurationName($indexingConfigurationName);

        $indexer = GeneralUtility::makeInstance($indexerClass, /** @scrutinizer ignore-type */ $indexerConfiguration);
        if (!($indexer instanceof Indexer)) {
            throw new RuntimeException(
                'The indexer class "' . $indexerClass . '" for indexing configuration "' . $indexingConfigurationName . '" is not a valid indexer. Must be a subclass of ApacheSolrForTypo3\Solr\IndexQueue\Indexer.',
                1260463206
            );
        }

        return $indexer;
    }

    /**
     * Gets the indexing progress.
     *
     * @return float Indexing progress as a two decimal precision float. f.e. 44.87
     */
    public function getProgress(): float
    {
        return $this->indexQueue->getStatisticsBySite($this->site)->getSuccessPercentage();
    }

    /**
     * Returns the amount of failed queue items for the current site.
     *
     * @return int
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
     * @param Item $item Index Queue item to use to determine the host.
     * @param
     */
    protected function initializeHttpServerEnvironment(Item $item)
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

    /**
     * @param string|null $originalHttpHost
     */
    protected function restoreOriginalHttpHost(?string $originalHttpHost)
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
