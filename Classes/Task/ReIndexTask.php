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

namespace ApacheSolrForTypo3\Solr\Task;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use Doctrine\DBAL\ConnectionException as DBALConnectionException;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Scheduler task to empty the indexes of a site and re-initialize the
 * Solr Index Queue thus making the indexer re-index the site.
 *
 * @author Christoph Moeller <support@network-publishing.de>
 */
class ReIndexTask extends AbstractSolrTask
{
    /**
     * Indexing configurations to re-initialize.
     *
     * @var array
     */
    protected array $indexingConfigurationsToReIndex = [];

    /**
     * Purges/commits all Solr indexes, initializes the Index Queue
     * and returns TRUE if the execution was successful
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     *
     * @throws DBALConnectionException
     * @throws DBALDriverException
     * @throws DBALException
     * @throws Throwable
     *
     * @noinspection PhpMissingReturnTypeInspection See {@link \TYPO3\CMS\Scheduler\Task\AbstractTask::execute()}
     */
    public function execute()
    {
        // clean up
        $cleanUpResult = $this->cleanUpIndex();

        // initialize for re-indexing
        /* @var Queue $indexQueue */
        $indexQueue = GeneralUtility::makeInstance(Queue::class);
        $indexQueueInitializationResults = $indexQueue->getInitializationService()
            ->initializeBySiteAndIndexConfigurations($this->getSite(), $this->indexingConfigurationsToReIndex);

        return $cleanUpResult && !in_array(false, $indexQueueInitializationResults);
    }

    /**
     * Removes documents of the selected types from the index.
     *
     * @return bool TRUE if clean up was successful, FALSE on error
     * @throws DBALDriverException
     */
    protected function cleanUpIndex(): bool
    {
        $cleanUpResult = true;
        $solrConfiguration = $this->getSite()->getSolrConfiguration();
        $solrServers = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionsBySite($this->getSite());
        $typesToCleanUp = [];
        $enableCommitsSetting = $solrConfiguration->getEnableCommits();

        foreach ($this->indexingConfigurationsToReIndex as $indexingConfigurationName) {
            $type = $solrConfiguration->getIndexQueueTableNameOrFallbackToConfigurationName($indexingConfigurationName);
            $typesToCleanUp[] = $type;
        }

        foreach ($solrServers as $solrServer) {
            $deleteQuery = 'type:(' . implode(' OR ', $typesToCleanUp) . ')' . ' AND siteHash:' . $this->getSite()->getSiteHash();
            $solrServer->getWriteService()->deleteByQuery($deleteQuery);

            if (!$enableCommitsSetting) {
                // Do not commit
                continue;
            }

            $response = $solrServer->getWriteService()->commit(false, false);
            if ($response->getHttpStatus() != 200) {
                $cleanUpResult = false;
                break;
            }
        }

        return $cleanUpResult;
    }

    /**
     * Gets the indexing configurations to re-index.
     *
     * @return array
     */
    public function getIndexingConfigurationsToReIndex(): array
    {
        return $this->indexingConfigurationsToReIndex;
    }

    /**
     * Sets the indexing configurations to re-index.
     *
     * @param array $indexingConfigurationsToReIndex
     */
    public function setIndexingConfigurationsToReIndex(array $indexingConfigurationsToReIndex)
    {
        $this->indexingConfigurationsToReIndex = $indexingConfigurationsToReIndex;
    }

    /**
     * This method is designed to return some additional information about the task,
     * that may help to set it apart from other tasks from the same class
     * This additional information is used - for example - in the Scheduler's BE module
     * This method should be implemented in most task classes
     *
     * @return string Information to display
     *
     * @throws DBALDriverException
     * @noinspection PhpMissingReturnTypeInspection See {@link \TYPO3\CMS\Scheduler\Task\AbstractTask::getAdditionalInformation()}
     */
    public function getAdditionalInformation()
    {
        $site = $this->getSite();
        if (is_null($site)) {
            return 'Invalid site configuration for scheduler please re-create the task!';
        }

        $information = 'Site: ' . $this->getSite()->getLabel();
        if (!empty($this->indexingConfigurationsToReIndex)) {
            $information .= ', Indexing Configurations: ' . implode(
                ', ',
                $this->indexingConfigurationsToReIndex
            );
        }

        return $information;
    }
}
