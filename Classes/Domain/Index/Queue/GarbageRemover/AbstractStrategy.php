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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Doctrine\DBAL\Exception as DBALException;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

/**
 * An implementation ob a garbage remover strategy is responsible to remove all garbage from the index queue and
 * the solr server for a certain table and uid combination.
 */
abstract class AbstractStrategy
{
    protected Queue $queue;

    protected ConnectionManager $connectionManager;

    public function __construct(
        Queue $queue = null,
        ConnectionManager $connectionManager = null,
    ) {
        $this->queue = $queue ?? GeneralUtility::makeInstance(Queue::class);
        $this->connectionManager = $connectionManager ?? GeneralUtility::makeInstance(ConnectionManager::class);
    }

    /**
     * Call's the removal of the strategy and afterwards the garbage-collector post-processing hook.
     */
    public function removeGarbageOf(string $table, int $uid): void
    {
        $this->removeGarbageOfByStrategy($table, $uid);
        $this->callPostProcessGarbageCollectorHook($table, $uid);
    }

    /**
     * An implementation of the GarbageCollection strategy is responsible to remove the garbage from
     * the indexqueue and from the solr server.
     */
    abstract protected function removeGarbageOfByStrategy(string $table, int $uid): void;

    /**
     * Deletes a document from solr and from the index queue.
     *
     * @throws DBALException
     */
    protected function deleteInSolrAndRemoveFromIndexQueue(string $table, int $uid): void
    {
        $this->deleteIndexDocuments($table, $uid);
        $this->queue->deleteItem($table, $uid);
    }

    /**
     * Deletes a document from solr and updates the item in the index queue (e.g. on page content updates).
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function deleteInSolrAndUpdateIndexQueue(string $table, int $uid): void
    {
        $this->deleteIndexDocuments($table, $uid);
        $this->queue->updateItem($table, $uid);
    }

    /**
     * Deletes index documents for a given record identification.
     *
     *
     * @throws DBALException
     */
    protected function deleteIndexDocuments(string $table, int $uid, int $language = 0): void
    {
        // record can be indexed for multiple sites
        $indexQueueItems = $this->queue->getItems($table, $uid);
        foreach ($indexQueueItems as $indexQueueItem) {
            try {
                $site = $indexQueueItem->getSite();
            } catch (InvalidArgumentException) {
                $this->queue->deleteItem($indexQueueItem->getType(), $indexQueueItem->getIndexQueueUid());
                continue;
            }

            $enableCommitsSetting = $site->getSolrConfiguration()->getEnableCommits();
            $siteHash = $site->getSiteHash();
            // a site can have multiple connections (cores / languages)
            $solrConnections = $this->connectionManager->getConnectionsBySite($site);
            if ($language > 0 && isset($solrConnections[$language])) {
                $solrConnections = [$language => $solrConnections[$language]];
            }
            $this->deleteRecordInAllSolrConnections($table, $uid, $solrConnections, $siteHash, $enableCommitsSetting);
        }
    }

    /**
     * Deletes the record in all solr connections from that site.
     */
    protected function deleteRecordInAllSolrConnections(
        string $table,
        int $uid,
        array $solrConnections,
        string $siteHash,
        bool $enableCommitsSetting,
    ): void {
        foreach ($solrConnections as $solr) {
            $query = 'type:' . $table . ' AND uid:' . $uid . ' AND siteHash:' . $siteHash;
            $response = $solr->getWriteService()->deleteByQuery($query);

            if ($response->getHttpStatus() !== 200) {
                $logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
                $logger->error(
                    'Couldn\'t delete index document',
                    [
                        'status' => $response->getHttpStatus(),
                        'msg' => $response->getHttpStatusMessage(),
                        'core' => $solr->getWriteService()->getCorePath(),
                        'query' => $query,
                    ]
                );

                // @todo: Ensure index is updated later on, e.g. via a new index queue status
                continue;
            }

            if ($enableCommitsSetting) {
                $solr->getWriteService()->commit(false, false);
            }
        }
    }

    /**
     * Calls the registered post-processing hooks after the garbageCollection.
     */
    protected function callPostProcessGarbageCollectorHook(string $table, int $uid): void
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessGarbageCollector'] ?? null)) {
            return;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessGarbageCollector'] as $classReference) {
            $garbageCollectorPostProcessor = GeneralUtility::makeInstance($classReference);

            if ($garbageCollectorPostProcessor instanceof GarbageCollectorPostProcessor) {
                $garbageCollectorPostProcessor->postProcessGarbageCollector($table, $uid);
            } else {
                $message = get_class($garbageCollectorPostProcessor) . ' must implement interface ' .
                    GarbageCollectorPostProcessor::class;
                throw new UnexpectedValueException($message, 1345807460);
            }
        }
    }
}
