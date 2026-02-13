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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Task;

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TestCase to check if we can index from index queue worker task into a solr server
 */
class IndexQueueWorkerTaskTest extends IntegrationTestBase
{
    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    protected Queue $indexQueue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    #[Test]
    public function canGetAdditionalInformationFromTask(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_trigger_frontend_calls_for_page_index.csv');
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        /** @var IndexQueueWorkerTask $indexQueueQueueWorkerTask */
        $indexQueueQueueWorkerTask = GeneralUtility::makeInstance(IndexQueueWorkerTask::class);
        $indexQueueQueueWorkerTask->setDocumentsToIndexLimit(1);
        $indexQueueQueueWorkerTask->setRootPageId($site->getRootPageId());

        $additionalInformation = $indexQueueQueueWorkerTask->getAdditionalInformation();

        self::assertStringContainsString('Root Page ID: 1', $additionalInformation);
        self::assertStringContainsString('Site: page for testing', $additionalInformation);
    }
}
