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
use ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * An implementation ob a garbage remover strategy is responsible to remove all garbage from the index queue and
 * the solr server for a certain table and uid combination.
 */
abstract class AbstractStrategy
{

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var ConnectionManager
     */
    protected $connectionManager;

    /**
     * AbstractStrategy constructor.
     * @param Queue|null $queue
     * @param ConnectionManager|null $connectionManager
     */
    public function __construct(Queue $queue = null, ConnectionManager $connectionManager = null)
    {
        $this->queue = $queue ?? GeneralUtility::makeInstance(Queue::class);
        $this->connectionManager = $connectionManager ?? GeneralUtility::makeInstance(ConnectionManager::class);
    }

    /**
     * Call's the removal of the strategy and afterwards the garbagecollector post processing hook.
     *
     * @param string $table
     * @param int $uid
     * @return mixed
     */
    public function removeGarbageOf($table, $uid)
    {
        $this->removeGarbageOfByStrategy($table, $uid);
        $this->callPostProcessGarbageCollectorHook($table, $uid);
    }

    /**
     * A implementation of the GarbageCollection strategy is responsible to remove the garbage from
     * the indexqueue and from the solr server.
     *
     * @param string $table
     * @param int $uid
     */
    abstract protected function removeGarbageOfByStrategy($table, $uid);

    /**
     * Deletes a document from solr and from the index queue.
     *
     * @param string $table
     * @param integer $uid
     */
    protected function deleteInSolrAndRemoveFromIndexQueue($table, $uid)
    {
        $this->deleteIndexDocuments($table, $uid);
        $this->queue->deleteItem($table, $uid);
    }

    /**
     * Deletes a document from solr and updates the item in the index queue (e.g. on page content updates).
     *
     * @param string $table
     * @param integer $uid
     */
    protected function deleteInSolrAndUpdateIndexQueue($table, $uid)
    {
        $this->deleteIndexDocuments($table, $uid);
        $this->queue->updateItem($table, $uid);
    }

    /**
     * Deletes index documents for a given record identification.
     *
     * @param string $table The record's table name.
     * @param int $uid The record's uid.
     */
    protected function deleteIndexDocuments($table, $uid, $language = 0)
    {
        // record can be indexed for multiple sites
        $indexQueueItems = $this->queue->getItems($table, $uid);
        foreach ($indexQueueItems as $indexQueueItem) {
            $site = $indexQueueItem->getSite();
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
     *
     * @param string $table
     * @param int $uid
     * @param SolrConnection[] $solrConnections
     * @param string $siteHash
     * @param boolean $enableCommitsSetting
     */
    protected function deleteRecordInAllSolrConnections($table, $uid, $solrConnections, $siteHash, $enableCommitsSetting)
    {
        foreach ($solrConnections as $solr) {
            $solr->getWriteService()->deleteByQuery('type:' . $table . ' AND uid:' . (int)$uid . ' AND siteHash:' . $siteHash);
            if ($enableCommitsSetting) {
                $solr->getWriteService()->commit(false, false);
            }
        }
    }

    /**
     * Calls the registered post processing hooks after the garbageCollection.
     *
     * @param string $table
     * @param int $uid
     */
    protected function callPostProcessGarbageCollectorHook($table, $uid)
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
                throw new \UnexpectedValueException($message, 1345807460);
            }
        }
    }
}
