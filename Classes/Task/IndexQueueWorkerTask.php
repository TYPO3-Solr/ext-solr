<?php
namespace ApacheSolrForTypo3\Solr\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\ProgressProviderInterface;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * A worker indexing the items in the index queue. Needs to be set up as one
 * task per root page.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class IndexQueueWorkerTask extends AbstractTask implements ProgressProviderInterface
{

    /**
     * The site this task is indexing.
     *
     * @var Site
     */
    protected $site;

    protected $documentsToIndexLimit;
    protected $configuration;


    /**
     * Works through the indexing queue and indexes the queued items into Solr.
     *
     * @return boolean Returns TRUE on success, FALSE if no items were indexed or none were found.
     */
    public function execute()
    {
        $executionSucceeded = false;

        $this->configuration = Util::getSolrConfigurationFromPageId($this->site->getRootPageId());
        $this->indexItems();
        $executionSucceeded = true;

        return $executionSucceeded;
    }

    /**
     * Indexes items from the Index Queue.
     *
     * @return void
     */
    protected function indexItems()
    {
        $limit = $this->documentsToIndexLimit;
        $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');

        // get items to index
        $itemsToIndex = $indexQueue->getItemsToIndex($this->site, $limit);
        foreach ($itemsToIndex as $itemToIndex) {
            try {
                // try indexing
                $itemIndexed = $this->indexItem($itemToIndex);
            } catch (\Exception $e) {
                $indexQueue->markItemAsFailed(
                    $itemToIndex,
                    $e->getCode() . ': ' . $e->__toString()
                );

                GeneralUtility::devLog(
                    'Failed indexing Index Queue item ' . $itemToIndex->getIndexQueueUid(),
                    'solr',
                    3,
                    array(
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTrace(),
                        'item' => (array)$itemToIndex
                    )
                );
            }
        }
        $this->emitAfterIndexItemsSignal($itemsToIndex);
    }

    /**
     * Emits a signal after all items was indexed
     *
     * @param array $itemsToIndex
     */
    protected function emitAfterIndexItemsSignal($itemsToIndex)
    {
        $signalSlotDispatcher = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\SignalSlot\\Dispatcher');
        $signalSlotDispatcher->dispatch(__CLASS__, 'afterIndexItems', array($itemsToIndex, $this));
    }

    /**
     * Indexes an item from the Index Queue.
     *
     * @param Item $item An index queue item to index
     * @return boolean TRUE if the item was successfully indexed, FALSE otherwise
     */
    protected function indexItem(Item $item)
    {
        $itemIndexed = false;
        $indexer = $this->getIndexerByItem($item->getIndexingConfigurationName());

        // Remember original http host value
        $originalHttpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
        // Overwrite http host
        $this->initializeHttpHost($item);

        $itemIndexed = $indexer->index($item);

        // update IQ item so that the IQ can determine what's been indexed already
        if ($itemIndexed) {
            $item->updateIndexedTime();
        }

        // restore http host
        if (!is_null($originalHttpHost)) {
            $_SERVER['HTTP_HOST'] = $originalHttpHost;
        } else {
            unset($_SERVER['HTTP_HOST']);
        }
        if (version_compare(TYPO3_branch, '7.5', '>=')) {
            GeneralUtility::flushInternalRuntimeCaches();
        }

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
     * @return Indexer An instance of ApacheSolrForTypo3\Solr\IndexQueue\Indexer or a sub class of it.
     */
    protected function getIndexerByItem($indexingConfigurationName)
    {
        $indexerClass = 'ApacheSolrForTypo3\\Solr\\IndexQueue\\Indexer';
        $indexerOptions = array();

        // allow to overwrite indexers per indexing configuration
        if (isset($this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer'])) {
            $indexerClass = $this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer'];
        }

        // get indexer options
        if (isset($this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer.'])
            && !empty($this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer.'])
        ) {
            $indexerOptions = $this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer.'];
        }

        $indexer = GeneralUtility::makeInstance($indexerClass, $indexerOptions);
        if (!($indexer instanceof Indexer)) {
            throw new \RuntimeException(
                'The indexer class "' . $indexerClass . '" for indexing configuration "' . $indexingConfigurationName . '" is not a valid indexer. Must be a subclass of ApacheSolrForTypo3\Solr\IndexQueue\Indexer.',
                1260463206
            );
        }

        return $indexer;
    }

    /**
     * Returns some additional information about indexing progress, shown in
     * the scheduler's task overview list.
     *
     * @return string Information to display
     */
    public function getAdditionalInformation()
    {
        $itemsIndexedPercentage = $this->getProgress();

        $message = 'Site: ' . $this->site->getLabel();

        $failedItemsCount = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
            'uid',
            'tx_solr_indexqueue_item',
            'root = ' . $this->site->getRootPageId() . ' AND errors != \'\''
        );
        if ($failedItemsCount) {
            $message .= ' Failures: ' . $failedItemsCount;
        }

        return $message;
    }

    /**
     * Gets the indexing progress.
     *
     * @return float Indexing progress as a two decimal precision float. f.e. 44.87
     */
    public function getProgress()
    {
        $itemsIndexedPercentage = 0.0;

        $totalItemsCount = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
            'uid',
            'tx_solr_indexqueue_item',
            'root = ' . $this->site->getRootPageId()
        );
        $remainingItemsCount = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
            'uid',
            'tx_solr_indexqueue_item',
            'changed > indexed AND root = ' . $this->site->getRootPageId()
        );
        $itemsIndexedCount = $totalItemsCount - $remainingItemsCount;

        if ($totalItemsCount > 0) {
            $itemsIndexedPercentage = $itemsIndexedCount * 100 / $totalItemsCount;
            $itemsIndexedPercentage = round($itemsIndexedPercentage, 2);
        }

        return $itemsIndexedPercentage;
    }

    /**
     * Gets the site / the site's root page uid this task is indexing.
     *
     * @return Site The site's root page uid this task is indexing
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Sets the task's site to indexing.
     *
     * @param Site $site The site to index by this task
     * @return void
     */
    public function setSite(Site $site)
    {
        $this->site = $site;
    }

    public function getDocumentsToIndexLimit()
    {
        return $this->documentsToIndexLimit;
    }

    public function setDocumentsToIndexLimit($limit)
    {
        $this->documentsToIndexLimit = $limit;
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
     */
    protected function initializeHttpHost(Item $item)
    {
        static $hosts = array();

        $rootpageId = $item->getRootPageUid();
        $hostFound = !empty($hosts[$rootpageId]);

        if (!$hostFound) {
            $rootline = BackendUtility::BEgetRootLine($rootpageId);
            $host = BackendUtility::firstDomainRecord($rootline);

            $hosts[$rootpageId] = $host;
        }

        $_SERVER['HTTP_HOST'] = $hosts[$rootpageId];
        if (version_compare(TYPO3_branch, '7.5', '>=')) {
            GeneralUtility::flushInternalRuntimeCaches();
        }
    }
}
