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

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\ProgressProviderInterface;

/**
 * A worker indexing the items in the index queue. Needs to be set up as one
 * task per root page.
 */
class IndexQueueWorkerTask extends AbstractSolrTask implements ProgressProviderInterface
{
    protected ?int $documentsToIndexLimit = null;

    /**
     * Works through the indexing queue and indexes the queued items into Solr and returns TRUE on success,
     * FALSE if no items were indexed or none were found.
     *
     * @throws ConnectionException
     * @throws DBALException
     *
     * @noinspection PhpMissingReturnTypeInspection See {@link AbstractTask::execute()}
     */
    public function execute()
    {
        $site = $this->getSite();
        $indexService = $this->getInitializedIndexService($site);
        $indexService->indexItems($this->documentsToIndexLimit);

        return true;
    }

    /**
     * Returns some additional information about indexing progress, shown in
     * the scheduler's task overview list.
     *
     * @throws DBALException
     *
     * @noinspection PhpMissingReturnTypeInspection {@link AbstractTask::getAdditionalInformation()}
     */
    public function getAdditionalInformation()
    {
        $site = $this->getSite();

        if (is_null($site)) {
            return 'Invalid site configuration for scheduler please re-create the task!';
        }

        $message = 'Site: ' . $site->getLabel();

        $indexService = $this->getInitializedIndexService($site);
        $failedItemsCount = $indexService->getFailCount();

        if ($failedItemsCount) {
            $message .= ' Failures: ' . $failedItemsCount;
        }

        return $message;
    }

    /**
     * Gets the indexing progress as a two decimal precision float. f.e. 44.87
     *
     * @throws DBALException
     *
     * @noinspection PhpMissingReturnTypeInspection {@link ProgressProviderInterface::getProgress}
     */
    public function getProgress()
    {
        $site = $this->getSite();
        if (is_null($site)) {
            return 0.0;
        }

        $indexService = $this->getInitializedIndexService($site);
        return $indexService->getProgress();
    }

    public function getDocumentsToIndexLimit(): ?int
    {
        return $this->documentsToIndexLimit;
    }

    public function setDocumentsToIndexLimit(int|string $limit): void
    {
        $this->documentsToIndexLimit = (int)$limit;
    }

    /**
     * Returns the initialized IndexService instance.
     */
    protected function getInitializedIndexService(Site $site): IndexService
    {
        $indexService = GeneralUtility::makeInstance(IndexService::class, $site);
        $indexService->setContextTask($this);
        return $indexService;
    }
}
