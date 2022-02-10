<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Task\ReIndexTask;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use Exception;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TestCase to check if the index queue can be initialized by the ReIndex Task
 *
 * @author Timo Schmidt
 */
class ReIndexTaskTest extends IntegrationTest
{

    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    /**
     * @var ReIndexTask
     */
    protected $task;

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @var array
     */
    protected $coreExtensionsToLoad = [
        'scheduler',
    ];

    /**
     * @throws NoSuchCacheException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->task = GeneralUtility::makeInstance(ReIndexTask::class);
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);

        /** @var BackendUserAuthentication $beUser */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;

        /* @var LanguageService $languageService */
        $languageService = GeneralUtility::makeInstance(LanguageService::class);
        $GLOBALS['LANG'] = $languageService;
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
            'Index queue is empty and was expected to be not empty.'
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
            'Index queue is empty and was expected to contain ' . (int)$amount . ' items.'
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function testIfTheQueueIsFilledAfterTaskWasRunning()
    {
        $this->importDataSetFromFixture('can_reindex_task_fill_queue.xml');
        $this->assertEmptyIndexQueue();

        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $this->task->setRootPageId($site->getRootPageId());
        $this->task->setIndexingConfigurationsToReIndex(['pages']);
        $this->task->execute();

        $this->assertIndexQueryContainsItemAmount(2);
    }

    /**
     * @test
     * @throws Exception
     */
    public function testCanGetAdditionalInformationFromTask()
    {
        $this->importDataSetFromFixture('can_reindex_task_fill_queue.xml');
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
     * @test
     * @throws Exception
     */
    public function solrIsEmptyAfterCleanup()
    {
        $this->importDataSetFromFixture('can_reindex_task_fill_queue.xml');

        // fill the solr
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $this->indexQueue->updateItem('pages', 1);
        $items = $this->indexQueue->getItems('pages', 1);
        /* @var Indexer $indexer */
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
        $this->cleanUpSolrServerAndAssertEmpty();
    }
}
