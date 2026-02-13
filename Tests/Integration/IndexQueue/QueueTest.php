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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to test the Queue
 */
class QueueTest extends IntegrationTestBase
{
    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @var SiteRepository
     */
    protected $siteRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->indexQueue->setQueueInitializationService(GeneralUtility::makeInstance(QueueInitializationService::class));
        $this->siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * Custom assertion to expect a specific amount of items in the queue.
     */
    protected function assertItemsInQueue(int $expectedAmount): void
    {
        $itemCount = $this->indexQueue->getAllItemsCount();
        self::assertSame($itemCount, $expectedAmount, 'Indexqueue contains unexpected amount of items. Expected amount: ' . $expectedAmount);
    }

    /**
     * Custom assertion to expect an empty queue.
     */
    protected function assertEmptyQueue()
    {
        $this->assertItemsInQueue(0);
    }

    #[Test]
    public function preFilledQueueContainsRootPageAfterInitialize(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_one_item.csv');
        $this->assertItemsInQueue(1);
        self::assertFalse($this->indexQueue->containsItem('pages', 1));
        self::assertTrue($this->indexQueue->containsItem('pages', 4711));

        // after initialize the prefilled queue item should be lost and the root page should be added again
        $site = $this->siteRepository->getFirstAvailableSite();
        $this->indexQueue->getQueueInitializationService()->initializeBySiteAndIndexConfiguration($site, 'pages');

        $this->assertItemsInQueue(1);
        self::assertTrue($this->indexQueue->containsItem('pages', 1));
        self::assertFalse($this->indexQueue->containsItem('pages', 4711));
    }

    #[Test]
    public function addingTheSameItemTwiceWillOnlyProduceOneQueueItem(): void
    {
        $this->assertEmptyQueue();

        $updateCount = $this->indexQueue->updateItem('pages', 1);
        self::assertSame(1, $updateCount);
        $this->assertItemsInQueue(1);

        $updateCount = $this->indexQueue->updateItem('pages', 1);
        self::assertSame(0, $updateCount);
        $this->assertItemsInQueue(1);
    }

    #[Test]
    public function canDeleteItemsByType(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items_with_multiple_types.csv');
        $this->assertItemsInQueue(2);

        $this->indexQueue->deleteItemsByType('pages');
        $this->assertItemsInQueue(1);

        $this->indexQueue->deleteItemsByType('tt_content');
        $this->assertEmptyQueue();
    }

    #[Test]
    public function unExistingRecordIsNotAddedToTheQueue(): void
    {
        $this->assertEmptyQueue();

        // record does not exist in fixture
        $this->indexQueue->updateItem('pages', 5);

        // queue should still be empty
        $this->assertEmptyQueue();
    }

    #[Test]
    public function canNotAddUnAllowedPageType(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_not_add_unallowed_pagetype.csv');
        $this->assertEmptyQueue();

        // record does not exist in fixture
        $updateCount = $this->indexQueue->updateItem('pages', 22);
        self::assertSame(0, $updateCount, 'Expected that no record was updated');

        // queue should still be empty
        $this->assertEmptyQueue();
    }

    #[Test]
    public function mountPagesAreOnlyAddedOnceAfterInitialize(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/queue_initialization_with_mount_pages.csv');
        $this->assertEmptyQueue();
        $site = $this->siteRepository->getFirstAvailableSite();

        $this->indexQueue->getQueueInitializationService()->initializeBySiteAndIndexConfiguration($site, 'pages');
        $this->assertItemsInQueue(4);

        $this->indexQueue->getQueueInitializationService()->initializeBySiteAndIndexConfiguration($site, 'pages');
        $this->assertItemsInQueue(4);
    }

    #[Test]
    public function canAddCustomPageTypeToTheQueue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/custom_page_doktype.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue {
                custom_page_type = 1
                custom_page_type {
                    initialization = ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page
                    indexer = ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer
                    type = pages
                    allowedPageTypes = 130
                    additionalWhereClause = doktype = 130 AND no_search = 0

                    fields {
                        pagetype_stringS = TEXT
                        pagetype_stringS {
                            value = Custom Page Type
                        }
                    }
                }
           }',
        );
        $site = $this->siteRepository->getFirstAvailableSite();
        $this->indexQueue->getQueueInitializationService()->initializeBySiteAndIndexConfiguration($site, 'custom_page_type');

        $this->assertItemsInQueue(1);

        $queueItem = $this->indexQueue->getItem(1);
        self::assertEquals(
            'custom_page_type',
            $queueItem->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration',
        );
    }

    #[Test]
    public function canGetStatisticsWithTotalItemCount(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items_in_multiple_sites.csv');
        $site = $this->siteRepository->getFirstAvailableSite();
        $itemCount = $this->indexQueue->getStatisticsBySite($site)->getTotalCount();

        // there are two items in the queue but only one for the site
        self::assertSame(1, $itemCount, 'Unexpected item count for the first site');
    }

    #[Test]
    public function canGetStatisticsBySiteWithPendingItems(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items_in_multiple_sites.csv');
        $site = $this->siteRepository->getFirstAvailableSite();

        $itemCount = $this->indexQueue->getStatisticsBySite($site)->getPendingCount();
        self::assertSame(1, $itemCount, 'Unexpected remaining item count for the first site');

        // after updating the item it should still be pending (changed > indexed)
        $this->indexQueue->updateItem('pages', 1);
        $itemCount = $this->indexQueue->getStatisticsBySite($site)->getPendingCount();
        self::assertSame(1, $itemCount, 'After updating a remaining item it should still be pending');

        // after marking the item as indexed, no remaining item should be left
        $items = $this->indexQueue->getItems('pages', 1);
        $this->indexQueue->updateIndexTimeByItem($items[0]);
        $itemCount = $this->indexQueue->getStatisticsBySite($site)->getPendingCount();
        self::assertSame(0, $itemCount, 'After indexing the item no remaining item should be left');
    }

    #[Test]
    public function canInitializeMultipleSites(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_in_multiple_sites.csv');
        $this->assertEmptyQueue();

        $availableSites = $this->siteRepository->getAvailableSites();
        $this->indexQueue->deleteAllItems();

        if (is_array($availableSites)) {
            foreach ($availableSites as $site) {
                if ($site instanceof Site) {
                    $this->indexQueue->getQueueInitializationService()->initializeBySiteAndIndexConfiguration($site);
                }
            }
        }

        $firstRootPage = $this->indexQueue->getItems('pages', 1);
        $secondRootPage = $this->indexQueue->getItems('pages', 111);

        self::assertCount(1, $firstRootPage);
        self::assertCount(1, $secondRootPage);

        $firstSubPage = $this->indexQueue->getItems('pages', 10);
        $secondSubPage = $this->indexQueue->getItems('pages', 1111);

        self::assertCount(1, $firstSubPage);
        self::assertCount(1, $secondSubPage);

        $this->assertItemsInQueue(4);
    }

    #[Test]
    public function canGetStatistics(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/subpages_with_filled_indexqueue.csv');
        $this->assertItemsInQueue(4);

        $site = $this->siteRepository->getSiteByPageId(111);
        $statistics = $this->indexQueue->getStatisticsBySite($site);
        self::assertSame(1, $statistics->getSuccessCount(), 'Can not get successful processed items from queue');
        self::assertSame(1, $statistics->getFailedCount(), 'Can not get failed processed items from queue');
        self::assertSame(1, $statistics->getPendingCount(), 'Can not get pending processed items from queue');
    }

    #[Test]
    public function canGetStatisticsByCustomIndexingConfigurationName(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/subpages_with_filled_indexqueue_multiple_indexing_configurations.csv');
        $this->assertItemsInQueue(4);

        $site = $this->siteRepository->getSiteByPageId(111);
        $statistics = $this->indexQueue->getStatisticsBySite($site, 'customIndexingConfigurationName');

        self::assertSame(1, $statistics->getSuccessCount(), 'Can not get successful processed custom items from queue');
        self::assertSame(1, $statistics->getFailedCount(), 'Can not get failed processed custom items from queue');
        self::assertSame(1, $statistics->getPendingCount(), 'Can not get pending processed custom items from queue');

        $notExistingIndexingConfStatistic = $this->indexQueue->getStatisticsBySite($site, 'notExistingIndexingConfigurationName');
        self::assertSame(0, $notExistingIndexingConfStatistic->getSuccessCount(), 'Can not get successful processed items from queue for not existing indexing configuration');
        self::assertSame(0, $notExistingIndexingConfStatistic->getFailedCount(), 'Can not get failed processed items from queue for not existing indexing configuration');
        self::assertSame(0, $notExistingIndexingConfStatistic->getPendingCount(), 'Can not get pending processed items from queue for not existing indexing configuration');
    }

    #[Test]
    public function canGetLastIndexNonExistingRoot(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items.csv');
        $this->assertItemsInQueue(3);
        $lastIndexTime = $this->indexQueue->getLastIndexTime(2);
        self::assertEquals($lastIndexTime, 0);
    }

    #[Test]
    public function canGetLastIndexRootExists(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items.csv');
        $this->assertItemsInQueue(3);
        $lastIndexTime = $this->indexQueue->getLastIndexTime(1);
        self::assertEquals($lastIndexTime, 1489507800);
    }

    #[Test]
    public function canGetLastIndexedItemIdNonExistingRoot(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items.csv');
        $this->assertItemsInQueue(3);
        $lastIndexedItemIdUid = $this->indexQueue->getLastIndexedItemId(2);
        self::assertEquals($lastIndexedItemIdUid, 0);
    }

    #[Test]
    public function canGetLastIndexedItemIdRootExists(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items.csv');
        $this->assertItemsInQueue(3);
        $lastIndexedItemIdUid = $this->indexQueue->getLastIndexedItemId(1);
        self::assertEquals($lastIndexedItemIdUid, 4713);
    }

    #[Test]
    public function canMarkItemAsFailedWithItemAndEmptyMessage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items_with_one_error.csv');
        $this->assertItemsInQueue(3);
        $item = $this->indexQueue->getItem(4711);
        $this->indexQueue->markItemAsFailed($item);
        $processedItem = $this->indexQueue->getItem($item->getIndexQueueUid());
        self::assertEquals($processedItem->getErrors(), '1');
    }

    #[Test]
    public function canMarkItemAsFailedWithItemAndMessage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items_with_one_error.csv');
        $this->assertItemsInQueue(3);
        $item = $this->indexQueue->getItem(4711);
        $this->indexQueue->markItemAsFailed($item, 'Error during indexing canMarkItemAsFailedWithItemAndMessage');
        $processedItem = $this->indexQueue->getItem($item->getIndexQueueUid());
        self::assertEquals($processedItem->getErrors(), 'Error during indexing canMarkItemAsFailedWithItemAndMessage');
    }

    #[Test]
    public function canMarkItemAsFailedWithUidAndEmptyMessage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items_with_one_error.csv');
        $this->assertItemsInQueue(3);
        $this->indexQueue->markItemAsFailed(4712);
        $item = $this->indexQueue->getItem(4712);
        self::assertEquals($item->getErrors(), '1');
    }

    #[Test]
    public function canMarkItemAsFailedWithUidAndMessage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items.csv');
        $this->assertItemsInQueue(3);
        $this->indexQueue->markItemAsFailed(4712, 'Error during indexing canMarkItemAsFailedWithUidAndMessage');
        $item = $this->indexQueue->getItem(4712);
        self::assertEquals($item->getErrors(), 'Error during indexing canMarkItemAsFailedWithUidAndMessage');
    }

    #[Test]
    public function canMarkItemAsFailedNonexistingUid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items.csv');
        $this->assertItemsInQueue(3);
        $this->indexQueue->markItemAsFailed(42, 'Error during indexing canMarkItemAsFailedWithUidAndMessage');
        $item = $this->indexQueue->getItem(42);
        self::assertEquals($item, null);
    }

    #[Test]
    public function canMarkItemAsFailedNonExistingItem(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items.csv');
        $this->assertItemsInQueue(3);
        $item = $this->indexQueue->getItem(42);
        $this->indexQueue->markItemAsFailed($item, 'Error during indexing canMarkItemAsFailedWithUidAndMessage');
        $processedItem = $this->indexQueue->getItem(42);
        self::assertEquals($processedItem, null);
    }

    #[Test]
    public function canUpdateIndexTimeByItemNonExistingItem(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items.csv');
        $this->assertItemsInQueue(3);
        $item = $this->indexQueue->getItem(42);
        self::assertEquals($item, null);
    }

    #[Test]
    public function canUpdateIndexTimeByItemExistingItem(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexqueue_items.csv');
        $this->assertItemsInQueue(3);
        $lastestUpdatedItem = $this->indexQueue->getLastIndexedItemId(1);
        self::assertEquals($lastestUpdatedItem, 4713);
        $this->indexQueue->updateIndexTimeByItem($this->indexQueue->getItem(4711));
        $lastestUpdatedItem = $this->indexQueue->getLastIndexedItemId(1);
        self::assertEquals($lastestUpdatedItem, 4711);
    }

    #[Test]
    public function canFlushAllErrors(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_flush_errors.csv');
        $this->assertItemsInQueue(4);

        /** @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $firstSite = $siteRepository->getFirstAvailableSite();

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        self::assertSame(2, count($errorsForFirstSite), 'Unexpected amount of errors for the first site');

        $this->indexQueue->resetAllErrors();

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        self::assertSame(0, count($errorsForFirstSite), 'Unexpected amount of errors for the first site after reset');

        $secondSite = $siteRepository->getSiteByPageId(111);
        $errorsForSecondSite = $this->indexQueue->getErrorsBySite($secondSite);
        self::assertSame(0, count($errorsForSecondSite), 'Unexpected amount of errors for the second site after reset');
    }

    #[Test]
    public function canFlushErrorsBySite(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_flush_errors.csv');
        $this->assertItemsInQueue(4);

        /** @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $firstSite = $siteRepository->getFirstAvailableSite();

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        self::assertSame(2, count($errorsForFirstSite), 'Unexpected amount of errors for the first site');

        $this->indexQueue->resetErrorsBySite($firstSite);

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        self::assertSame(0, count($errorsForFirstSite), 'Unexpected amount of errors for the first site after reset');

        $secondSite = $siteRepository->getSiteByPageId(111);
        $errorsForSecondSite = $this->indexQueue->getErrorsBySite($secondSite);
        self::assertSame(1, count($errorsForSecondSite), 'Unexpected amount of errors for the second site after reset');
    }

    #[Test]
    public function canFlushErrorByItem(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_flush_errors.csv');
        $this->assertItemsInQueue(4);

        /** @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $firstSite = $siteRepository->getFirstAvailableSite();

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        self::assertSame(2, count($errorsForFirstSite), 'Unexpected amount of errors for the first site');

        $item = $this->indexQueue->getItem(4714);
        $this->indexQueue->resetErrorByItem($item);

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        self::assertSame(1, count($errorsForFirstSite), 'Unexpected amount of errors for the first site after resetting one item');
    }
}
