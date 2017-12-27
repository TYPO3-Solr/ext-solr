<?php
namespace ApacheSolrForTypo3\Solr\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Christoph Moeller <support@network-publishing.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
 *
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
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
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
    protected $indexingConfigurationsToReIndex = [];

    /**
     * Purges/commits all Solr indexes, initializes the Index Queue
     * and returns TRUE if the execution was successful
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     */
    public function execute()
    {
        // clean up
        $cleanUpResult = $this->cleanUpIndex();

        // initialize for re-indexing
        $indexQueue = GeneralUtility::makeInstance(Queue::class);
        $indexQueueInitializationResults = [];
        foreach ($this->indexingConfigurationsToReIndex as $indexingConfigurationName) {
            $indexQueueInitializationResults = $indexQueue->initialize($this->getSite(), $indexingConfigurationName);
        }

        return ($cleanUpResult && !in_array(false, $indexQueueInitializationResults));
    }

    /**
     * Removes documents of the selected types from the index.
     *
     * @return bool TRUE if clean up was successful, FALSE on error
     */
    protected function cleanUpIndex()
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
                # Do not commit
                continue;
            }

            $response = $solrServer->getWriteService()->commit(false, false, false);
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
    public function getIndexingConfigurationsToReIndex()
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
     */
    public function getAdditionalInformation()
    {
        $site = $this->getSite();
        if (is_null($site)) {
            return 'Invalid site configuration for scheduler please re-create the task!';
        }

        $information = 'Site: ' . $this->getSite()->getLabel();
        if (!empty($this->indexingConfigurationsToReIndex)) {
            $information .= ', Indexing Configurations: ' . implode(', ',
                    $this->indexingConfigurationsToReIndex);
        }

        return $information;
    }
}
