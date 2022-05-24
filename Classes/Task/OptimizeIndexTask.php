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
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Scheduler task to empty the indexes of a site and re-initialize the
 * Solr Index Queue thus making the indexer re-index the site.
 *
 * @author Jens Jacobsen <typo3@jens-jacobsen.de>
 */
class OptimizeIndexTask extends AbstractSolrTask
{
    /**
     * Cores to optimize.
     *
     * @var array
     */
    protected array $coresToOptimizeIndex = [];

    /**
     * Optimizes all Solr indexes for selected cores and returns TRUE if the execution was successful
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     * @throws DBALDriverException
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpUnused
     *
     * @noinspection PhpMissingReturnTypeInspection See {@link \TYPO3\CMS\Scheduler\Task\AbstractTask::execute()}
     */
    public function execute()
    {
        $optimizeResult = true;
        $solrServers = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionsBySite($this->getSite());
        foreach ($solrServers as $solrServer) {
            $writeService = $solrServer->getWriteService();
            $corePath = $writeService->getCorePath();
            if (!in_array($corePath, $this->coresToOptimizeIndex)) {
                continue;
            }
            $result = $writeService->optimizeIndex();
            if ($result->getResponse()->getStatusCode() != 200) {
                $optimizeResult = false;
            }
        }
        return $optimizeResult;
    }

    /**
     * Gets the cores to optimize.
     *
     * @return array
     */
    public function getCoresToOptimizeIndex(): array
    {
        return $this->coresToOptimizeIndex;
    }

    /**
     * Sets the cores to optimize.
     *
     * @param array $coresToOptimizeIndex
     */
    public function setCoresToOptimizeIndex(array $coresToOptimizeIndex): void
    {
        $this->coresToOptimizeIndex = $coresToOptimizeIndex;
    }

    /**
     * This method is designed to return some additional information about the task,
     * that may help to set it apart from other tasks from the same class
     * This additional information is used - for example - in the Scheduler's BE module
     * This method should be implemented in most task classes
     *
     * @return string Information to display
     * @throws DBALDriverException
     * @noinspection PhpMissingReturnTypeInspection {@link \TYPO3\CMS\Scheduler\Task\AbstractTask::getAdditionalInformation()}
     */
    public function getAdditionalInformation()
    {
        $site = $this->getSite();
        if (is_null($site)) {
            return 'Invalid site configuration for scheduler please re-create the task!';
        }

        $information = 'Site: ' . $this->getSite()->getLabel();
        if (!empty($this->coresToOptimizeIndex)) {
            $information .= PHP_EOL . 'Corepaths: ' . implode(', ', $this->coresToOptimizeIndex);
        }
        return $information;
    }
}
