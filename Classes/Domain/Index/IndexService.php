<?php

namespace ApacheSolrForTypo3\Solr\Domain\Index;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask;
use Solarium\Exception\HttpException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * Service to perform indexing operations
 *
 * @author Timo Hund <timo.schmidt@dkd.de>
 */
class IndexService
{
    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var Site
     */
    protected $site;

    /**
     * @var IndexQueueWorkerTask
     */
    protected $contextTask;

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @var Dispatcher
     */
    protected $signalSlotDispatcher;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * IndexService constructor.
     * @param Site $site
     * @param Queue|null $queue
     * @param Dispatcher|null $dispatcher
     * @param SolrLogManager|null $solrLogManager
     */
    public function __construct(Site $site, Queue $queue = null, Dispatcher $dispatcher = null, SolrLogManager $solrLogManager = null)
    {
        $this->site = $site;
        $this->indexQueue = $queue ?? GeneralUtility::makeInstance(Queue::class);
        $this->signalSlotDispatcher = $dispatcher ?? GeneralUtility::makeInstance(Dispatcher::class);
        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask $contextTask
     */
    public function setContextTask($contextTask)
    {
        $this->contextTask = $contextTask;
    }

    /**
     * @return \ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask
     */
    public function getContextTask()
    {
        return $this->contextTask;
    }

    /**
     * Indexes items from the Index Queue.
     *
     * @param int $limit
     * @return bool
     */
    public function indexItems($limit)
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
            } catch (\Exception $e) {
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
                    $solrServer->getWriteService()->commit(false, false, false);
                } catch (HttpException $e) {
                    $errors++;
                }
            }
        }

        return ($errors === 0);
    }

    /**
     * Generates a message in the error log when an error occured.
     *
     * @param Item $itemToIndex
     * @param \Exception  $e
     */
    protected function generateIndexingErrorLog(Item $itemToIndex, \Exception $e)
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
     * Builds an emits a singal for the IndexService.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    protected function emitSignal($name, $arguments)
    {
        return $this->signalSlotDispatcher->dispatch(__CLASS__, $name, $arguments);
    }

    /**
     * Indexes an item from the Index Queue.
     *
     * @param Item $item An index queue item to index
     * @param TypoScriptConfiguration $configuration
     * @return bool TRUE if the item was successfully indexed, FALSE otherwise
     */
    protected function indexItem(Item $item, TypoScriptConfiguration $configuration)
    {
        $indexer = $this->getIndexerByItem($item->getIndexingConfigurationName(), $configuration);

        // Remember original http host value
        $originalHttpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;

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
        } catch (\Exception $e) {
            $this->restoreOriginalHttpHost($originalHttpHost);
            throw $e;
        }

        $this->restoreOriginalHttpHost($originalHttpHost);

        return $itemIndexed;
    }

    /**
     * A factory method to get an indexer depending on an item's configuration.
     *
     * By default all items are indexed using the default indexer
     * (ApacheSolrForTypo3\Solr\IndexQueue\Indexer) coming with EXT:solr. Pages by default are
     * configured to be indexed through a dedicated indexer
     * (ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer). In all other cases a dedicated indexer
     * can be specified through TypoScript if needed.
     *
     * @param string $indexingConfigurationName Indexing configuration name.
     * @param TypoScriptConfiguration $configuration
     * @return Indexer
     */
    protected function getIndexerByItem($indexingConfigurationName, TypoScriptConfiguration $configuration)
    {
        $indexerClass = $configuration->getIndexQueueIndexerByConfigurationName($indexingConfigurationName);
        $indexerConfiguration = $configuration->getIndexQueueIndexerConfigurationByConfigurationName($indexingConfigurationName);

        $indexer = GeneralUtility::makeInstance($indexerClass, /** @scrutinizer ignore-type */ $indexerConfiguration);
        if (!($indexer instanceof Indexer)) {
            throw new \RuntimeException(
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
    public function getProgress()
    {
        return $this->indexQueue->getStatisticsBySite($this->site)->getSuccessPercentage();
    }

    /**
     * Returns the amount of failed queue items for the current site.
     *
     * @return int
     */
    public function getFailCount()
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
        $rootpageId = $item->getRootPageUid();
        $hostFound = !empty($hosts[$rootpageId]);

        if (!$hostFound) {
            $hosts[$rootpageId] = $item->getSite()->getDomain();
        }

        $_SERVER['HTTP_HOST'] = $hosts[$rootpageId];

        // needed since TYPO3 7.5
        GeneralUtility::flushInternalRuntimeCaches();
    }

    /**
     * @param string|null $originalHttpHost
     */
    protected function restoreOriginalHttpHost($originalHttpHost)
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
