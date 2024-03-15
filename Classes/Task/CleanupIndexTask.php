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
use Doctrine\DBAL\ConnectionException as DBALConnectionException;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Scheduler task to empty the indexes of a site and re-initialize the
 * Solr Index Queue thus making the indexer re-index the site.
 *
 * @author Christoph Moeller <support@network-publishing.de>
 */
class CleanupIndexTask extends AbstractSolrTask
{
    protected ?int $deleteOlderThanDays = null;

    public function getDeleteOlderThanDays(): ?int
    {
        return $this->deleteOlderThanDays;
    }

    public function setDeleteOlderThanDays(?int $deleteOlderThanDays): void
    {
        $this->deleteOlderThanDays = $deleteOlderThanDays;
    }

    /**
     * Deletes old documents from index
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     *
     * @throws DBALConnectionException
     * @throws DBALException
     *
     * @noinspection PhpMissingReturnTypeInspection See {@link \TYPO3\CMS\Scheduler\Task\AbstractTask::execute()}
     */
    public function execute()
    {
        $cleanUpResult = true;
        $solrConfiguration = $this->getSite()->getSolrConfiguration();
        $solrServers = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionsBySite($this->getSite());
        $enableCommitsSetting = $solrConfiguration->getEnableCommits();

        foreach ($solrServers as $solrServer) {
            $deleteQuery = 'siteHash:' . $this->getSite()->getSiteHash() . sprintf(' AND indexed:[* TO NOW-%dDAYS]', $this->deleteOlderThanDays ?? 1);
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
     * This method is designed to return some additional information about the task,
     * that may help to set it apart from other tasks from the same class
     * This additional information is used - for example - in the Scheduler's BE module
     * This method should be implemented in most task classes
     *
     * @return string Information to display
     *
     * @throws DBALException
     *
     * @noinspection PhpMissingReturnTypeInspection See {@link \TYPO3\CMS\Scheduler\Task\AbstractTask::getAdditionalInformation()}
     */
    public function getAdditionalInformation()
    {
        $site = $this->getSite();
        if (is_null($site)) {
            return 'Invalid site configuration for scheduler please re-create the task!';
        }

        return 'Site: ' . $this->getSite()->getLabel();
    }
}
