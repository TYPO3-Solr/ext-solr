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
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Task\ReIndexTask;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TestCase to check if the index queue can be initialized by the ReIndex Task
 */
class ReIndexTaskTest extends IntegrationTestBase
{
    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    protected ReIndexTask $task;
    protected Queue $indexQueue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->task = GeneralUtility::makeInstance(ReIndexTask::class);
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);

        /** @var BackendUserAuthentication $beUser */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;

        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    protected function assertEmptyIndexQueue()
    {
        self::assertEquals(0, $this->indexQueue->getAllItemsCount(), 'Index queue is not empty as expected');
    }

    protected function assertNotEmptyIndexQueue()
    {
        self::assertGreaterThan(
            0,
            $this->indexQueue->getAllItemsCount(),
            'Index queue is empty and was expected to be not empty.',
        );
    }

    /**
     * @param $amount
     */
    protected function assertIndexQueryContainsItemAmount($amount)
    {
        self::assertEquals(
            $amount,
            $this->indexQueue->getAllItemsCount(),
            'Index queue is empty and was expected to contain ' . (int)$amount . ' items.',
        );
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testIfTheQueueIsFilledAfterTaskWasRunning(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_reindex_task_fill_queue.csv');
        $this->assertEmptyIndexQueue();

        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $this->task->setRootPageId($site->getRootPageId());
        $this->task->setIndexingConfigurationsToReIndex(['pages']);
        $this->task->execute();

        $this->assertIndexQueryContainsItemAmount(2);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testCanGetAdditionalInformationFromTask(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_reindex_task_fill_queue.csv');
        $this->assertEmptyIndexQueue();

        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $this->task->setRootPageId($site->getRootPageId());
        $this->task->setIndexingConfigurationsToReIndex(['pages']);
        $additionalInformation = $this->task->getAdditionalInformation();

        self::assertStringContainsString('Indexing Configurations: pages', $additionalInformation);
        self::assertStringContainsString('Root Page ID: 1', $additionalInformation);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function solrIsEmptyAfterCleanup(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_reindex_task_fill_queue.csv');

        // fill the solr
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $this->indexQueue->updateItem('pages', 1);
        $items = $this->indexQueue->getItems('pages', 1);
        /** @var Indexer $indexer */
        $indexer = GeneralUtility::makeInstance(Indexer::class);
        $indexer->index($items[0]);
        $this->waitToBeVisibleInSolr();

        $this->assertSolrContainsDocumentCount(1);
        $this->task->setRootPageId($site->getRootPageId());
        $this->task->setIndexingConfigurationsToReIndex(['pages']);
        $this->task->execute();

        $this->waitToBeVisibleInSolr();

        // after the task was running the solr server should be empty
        $this->assertSolrIsEmpty();

        // if not we cleanup now
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
    }
}
