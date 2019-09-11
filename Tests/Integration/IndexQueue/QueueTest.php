<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Timo Schmidt <timo.schmidt@dkd.de>
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
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to test the Queue
 *
 * @author Timo Schmidt
 */
class QueueTest extends IntegrationTest
{

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * Custom assertion to expect a specific amount of items in the queue.
     *
     * @param int $expectedAmount
     */
    protected function assertItemsInQueue($expectedAmount)
    {
        $itemCount = $this->indexQueue->getAllItemsCount();
        $this->assertSame($itemCount, $expectedAmount, 'Indexqueue contains unexpected amount of items. Expected amount: ' . $expectedAmount);
    }

    /**
     * Custom assertion to expect an empty queue.
     * @return void
     */
    protected function assertEmptyQueue()
    {
        $this->assertItemsInQueue(0);
    }

    /**
     * @test
     */
    public function preFilledQueueContainsRootPageAfterInitialize()
    {
        $this->importDataSetFromFixture('can_clear_queue_after_initialize.xml');
        $itemCount = $this->indexQueue->getAllItemsCount();

        $this->assertItemsInQueue(1);
        $this->assertFalse($this->indexQueue->containsItem('pages', 1));
        $this->assertTrue($this->indexQueue->containsItem('pages', 4711));

        // after initialize the prefilled queue item should be lost and the root page should be added again
        $site = $this->siteRepository->getFirstAvailableSite();
        $this->indexQueue->getInitializationService()->initializeBySiteAndIndexConfiguration($site, 'pages');

        $this->assertItemsInQueue(1);
        $this->assertTrue($this->indexQueue->containsItem('pages', 1));
        $this->assertFalse($this->indexQueue->containsItem('pages', 4711));
    }

    /**
     * @test
     */
    public function addingTheSameItemTwiceWillOnlyProduceOneQueueItem()
    {
        $this->importDataSetFromFixture('adding_the_same_item_twice_will_only_produce_one_queue_item.xml');
        $this->assertEmptyQueue();

        $updateCount = $this->indexQueue->updateItem('pages', 1);
        $this->assertSame(1, $updateCount);
        $this->assertItemsInQueue(1);

        $updateCount = $this->indexQueue->updateItem('pages', 1);
        $this->assertSame(0, $updateCount);
        $this->assertItemsInQueue(1);
    }

    /**
     * @test
     */
    public function canDeleteItemsByType()
    {
        $this->importDataSetFromFixture('can_delete_queue_items_by_type.xml');
        $this->assertItemsInQueue(2);

        $this->indexQueue->deleteItemsByType('pages');
        $this->assertItemsInQueue(1);

        $this->indexQueue->deleteItemsByType('tt_content');
        $this->assertEmptyQueue();
    }

    /**
     * @test
     */
    public function unExistingRecordIsNotAddedToTheQueue()
    {
        $this->importDataSetFromFixture('unexisting_record_can_not_be_added_to_queue.xml');
        $this->assertEmptyQueue();

        // record does not exist in fixture
        $this->expectException(\InvalidArgumentException::class);
        $this->indexQueue->updateItem('pages', 5);

        // queue should still be empty
        $this->assertEmptyQueue();
    }

    /**
     * @test
     */
    public function canNotAddUnAllowedPageType()
    {
        $this->importDataSetFromFixture('can_not_add_unallowed_pagetype.xml');
        $this->assertEmptyQueue();

            // record does not exist in fixture
        $updateCount = $this->indexQueue->updateItem('pages', 22);
        $this->assertSame(0, $updateCount, 'Expected that no record was updated');

            // queue should still be empty
        $this->assertEmptyQueue();
    }

    /**
     * @test
     */
    public function mountPagesAreOnlyAddedOnceAfterInitialize()
    {
        $this->importDataSetFromFixture('mount_pages_initialize_queue_as_expected.xml');
        $this->assertEmptyQueue();
        $site = $this->siteRepository->getFirstAvailableSite();

        $this->indexQueue->getInitializationService()->initializeBySiteAndIndexConfiguration($site, 'pages');
        $this->assertItemsInQueue(4);

        $this->indexQueue->getInitializationService()->initializeBySiteAndIndexConfiguration($site, 'pages');
        $this->assertItemsInQueue(4);

    }

    /**
     * @test
     */
    public function canAddCustomPageTypeToTheQueue()
    {
        $this->importDataSetFromFixture('can_index_custom_page_type_with_own_configuration.xml');
        $site = $this->siteRepository->getFirstAvailableSite();
        $this->indexQueue->getInitializationService()->initializeBySiteAndIndexConfiguration($site, 'custom_page_type');

        $this->assertItemsInQueue(1);

        $queueItem = $this->indexQueue->getItem(1);
        $this->assertEquals(
            'custom_page_type',
            $queueItem->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration'
        );
    }

    /**
     * @test
     */
    public function canGetStatisticsWithTotalItemCount()
    {
        $this->importDataSetFromFixture('can_get_item_count_by_site.xml');
        $site = $this->siteRepository->getFirstAvailableSite();
        $itemCount = $this->indexQueue->getStatisticsBySite($site)->getTotalCount();

            // there are two items in the queue but only one for the site
        $this->assertSame(1, $itemCount, 'Unexpected item count for the first site');
    }

    /**
     * @test
     */
    public function canGetStatisticsBySiteWithPendingItems()
    {
        $this->importDataSetFromFixture('can_get_item_count_by_site.xml');
        $site = $this->siteRepository->getFirstAvailableSite();

        $itemCount = $this->indexQueue->getStatisticsBySite($site)->getPendingCount();
        $this->assertSame(1, $itemCount, 'Unexpected remaining item count for the first site');

            // when we update the item, no remaining item should be left
        $this->indexQueue->updateItem('pages', 1);
        $itemCount = $this->indexQueue->getStatisticsBySite($site)->getPendingCount();
        $this->assertSame(0, $itemCount, 'After updating a remaining item no remaining item should be left');
    }

    /**
     * @test
     */
    public function canInitializeMultipleSites()
    {
        $this->importDataSetFromFixture('can_initialize_multiple_sites.xml');
        $this->assertEmptyQueue();

        $availableSites = $this->siteRepository->getAvailableSites();
        $this->indexQueue->deleteAllItems();

        if (is_array($availableSites)) {
            foreach ($availableSites as $site) {
                if ($site instanceof Site) {
                    $this->indexQueue->getInitializationService()->initializeBySiteAndIndexConfiguration($site);
                }
            }
        }


        $firstRootPage = $this->indexQueue->getItems('pages',1);
        $secondRootPage = $this->indexQueue->getItems('pages',111);

        $this->assertCount(1, $firstRootPage);
        $this->assertCount(1, $secondRootPage);

        $firstSubPage = $this->indexQueue->getItems('pages',10);
        $secondSubPage = $this->indexQueue->getItems('pages',1111);

        $this->assertCount(1, $firstSubPage);
        $this->assertCount(1, $secondSubPage);

        $this->assertItemsInQueue(4);
    }

    /**
     * @test
     */
    public function canGetStatistics()
    {
        $this->importDataSetFromFixture('can_get_statistics_by_site.xml');
        $this->assertItemsInQueue(4);

        $site = $this->siteRepository->getSiteByPageId(111);
        $statistics = $this->indexQueue->getStatisticsBySite($site);
        $this->assertSame(1, $statistics->getSuccessCount(), 'Can not get successful processed items from queue');
        $this->assertSame(1, $statistics->getFailedCount(), 'Can not get failed processed items from queue');
        $this->assertSame(1, $statistics->getPendingCount(), 'Can not get pending processed items from queue');
    }

    /**
     * @test
     */
    public function canGetStatisticsByCustomIndexingConfigurationName()
    {
        $this->importDataSetFromFixture('can_get_statistics_by_site_and_custom_indexing_configuration.xml');
        $this->assertItemsInQueue(4);

        $site = $this->siteRepository->getSiteByPageId(111);
        $statistics = $this->indexQueue->getStatisticsBySite($site, 'customIndexingConfigurationName');

        $this->assertSame(1, $statistics->getSuccessCount(), 'Can not get successful processed custom items from queue');
        $this->assertSame(1, $statistics->getFailedCount(), 'Can not get failed processed custom items from queue');
        $this->assertSame(1, $statistics->getPendingCount(), 'Can not get pending processed custom items from queue');

        $notExistingIndexingConfStatistic = $this->indexQueue->getStatisticsBySite($site, 'notExistingIndexingConfigurationName');
        $this->assertSame(0, $notExistingIndexingConfStatistic->getSuccessCount(), 'Can not get successful processed items from queue for not existing indexing configuration');
        $this->assertSame(0, $notExistingIndexingConfStatistic->getFailedCount(), 'Can not get failed processed items from queue for not existing indexing configuration');
        $this->assertSame(0, $notExistingIndexingConfStatistic->getPendingCount(), 'Can not get pending processed items from queue for not existing indexing configuration');
    }

    /**
     * @test
     */
    public function canGetLastIndexNonExistingRoot()
    {
        $this->importDataSetFromFixture('can_get_last_index_time.xml');
        $this->assertItemsInQueue(3);
        $lastIndexTime = $this->indexQueue->getLastIndexTime(2);
        $this->assertEquals($lastIndexTime, 0);
    }

    /**
     * @test
     */
    public function canGetLastIndexRootExists()
    {
        $this->importDataSetFromFixture('can_get_last_index_time.xml');
        $this->assertItemsInQueue(3);
        $lastIndexTime = $this->indexQueue->getLastIndexTime(1);
        $this->assertEquals($lastIndexTime, 1489383800);
    }

    /**
     * @test
     */
    public function canGetLastIndexedItemIdNonExistingRoot()
    {
        $this->importDataSetFromFixture('can_get_last_indexed_item_id.xml');
        $this->assertItemsInQueue(3);
        $lastIndexedItemIdUid = $this->indexQueue->getLastIndexedItemId(2);
        $this->assertEquals($lastIndexedItemIdUid, 0);
    }

    /**
     * @test
     */
    public function canGetLastIndexedItemIdRootExists()
    {
        $this->importDataSetFromFixture('can_get_last_indexed_item_id.xml');
        $this->assertItemsInQueue(3);
        $lastIndexedItemIdUid = $this->indexQueue->getLastIndexedItemId(1);
        $this->assertEquals($lastIndexedItemIdUid, 4713);
    }

    /**
     * @test
     */
    public function canMarkItemAsFailedWithItemAndEmptyMessage()
    {
        $this->importDataSetFromFixture('can_mark_item_as_failed.xml');
        $this->assertItemsInQueue(3);
        $item = $this->indexQueue->getItem(4711);
        $this->indexQueue->markItemAsFailed($item);
        $processedItem = $this->indexQueue->getItem($item->getIndexQueueUid());
        $this->assertEquals($processedItem->getErrors(), '1');
    }

    /**
     * @test
     */
    public function canMarkItemAsFailedWithItemAndMessage()
    {
        $this->importDataSetFromFixture('can_mark_item_as_failed.xml');
        $this->assertItemsInQueue(3);
        $item = $this->indexQueue->getItem(4711);
        $this->indexQueue->markItemAsFailed($item, 'Error during indexing canMarkItemAsFailedWithItemAndMessage');
        $processedItem = $this->indexQueue->getItem($item->getIndexQueueUid());
        $this->assertEquals($processedItem->getErrors(), 'Error during indexing canMarkItemAsFailedWithItemAndMessage');
    }

    /**
     * @test
     */
    public function canMarkItemAsFailedWithUidAndEmptyMessage()
    {
        $this->importDataSetFromFixture('can_mark_item_as_failed.xml');
        $this->assertItemsInQueue(3);
        $this->indexQueue->markItemAsFailed(4712);
        $item = $this->indexQueue->getItem(4712);
        $this->assertEquals($item->getErrors(), '1');
    }

    /**
     * @test
     */
    public function canMarkItemAsFailedWithUidAndMessage()
    {
        $this->importDataSetFromFixture('can_mark_item_as_failed.xml');
        $this->assertItemsInQueue(3);
        $this->indexQueue->markItemAsFailed(4712, 'Error during indexing canMarkItemAsFailedWithUidAndMessage');
        $item = $this->indexQueue->getItem(4712);
        $this->assertEquals($item->getErrors(), 'Error during indexing canMarkItemAsFailedWithUidAndMessage');
    }

    /**
     * @test
     */
    public function canMarkItemAsFailedNonexistingUid()
    {
        $this->importDataSetFromFixture('can_mark_item_as_failed.xml');
        $this->assertItemsInQueue(3);
        $this->indexQueue->markItemAsFailed(42, 'Error during indexing canMarkItemAsFailedWithUidAndMessage');
        $item = $this->indexQueue->getItem(42);
        $this->assertEquals($item, null);
    }

    /**
     * @test
     */
    public function canMarkItemAsFailedNonexistingItem()
    {
        $this->importDataSetFromFixture('can_mark_item_as_failed.xml');
        $this->assertItemsInQueue(3);
        $item = $this->indexQueue->getItem(42);
        $this->indexQueue->markItemAsFailed($item, 'Error during indexing canMarkItemAsFailedWithUidAndMessage');
        $processedItem = $this->indexQueue->getItem(42);
        $this->assertEquals($processedItem, null);
    }

    /**
     * @test
     */
    public function canUpdateIndexTimeByItemNonExistingItem()
    {
        $this->importDataSetFromFixture('can_update_index_time_by_item.xml');
        $this->assertItemsInQueue(3);
        $item = $this->indexQueue->getItem(42);
        $this->assertEquals($item, null);
    }

    /**
     * @test
     */
    public function canUpdateIndexTimeByItemExistingItem()
    {
        $this->importDataSetFromFixture('can_update_index_time_by_item.xml');
        $this->assertItemsInQueue(3);
        $lastestUpdatedItem = $this->indexQueue->getLastIndexedItemId(1);
        $this->assertEquals($lastestUpdatedItem, 4713);
        $this->indexQueue->updateIndexTimeByItem($this->indexQueue->getItem(4711));
        $lastestUpdatedItem = $this->indexQueue->getLastIndexedItemId(1);
        $this->assertEquals($lastestUpdatedItem, 4711);
    }

    /**
     * @test
     */
    public function canFlushAllErrors()
    {
        $this->importDataSetFromFixture('can_flush_errors.xml');
        $this->assertItemsInQueue(4);

        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $firstSite = $siteRepository->getFirstAvailableSite();

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        $this->assertSame(2, count($errorsForFirstSite), 'Unexpected amount of errors for the first site');

        $this->indexQueue->resetAllErrors();

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        $this->assertSame(0, count($errorsForFirstSite), 'Unexpected amount of errors for the first site after reset');

        $secondSite = $siteRepository->getSiteByPageId(111);
        $errorsForSecondSite = $this->indexQueue->getErrorsBySite($secondSite);
        $this->assertSame(0, count($errorsForSecondSite), 'Unexpected amount of errors for the second site after reset');
    }

    /**
     * @test
     */
    public function canFlushErrorsBySite()
    {
        $this->importDataSetFromFixture('can_flush_errors.xml');
        $this->assertItemsInQueue(4);

        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $firstSite = $siteRepository->getFirstAvailableSite();

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        $this->assertSame(2, count($errorsForFirstSite), 'Unexpected amount of errors for the first site');

        $this->indexQueue->resetErrorsBySite($firstSite);

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        $this->assertSame(0, count($errorsForFirstSite), 'Unexpected amount of errors for the first site after reset');

        $secondSite = $siteRepository->getSiteByPageId(111);
        $errorsForSecondSite = $this->indexQueue->getErrorsBySite($secondSite);
        $this->assertSame(1, count($errorsForSecondSite), 'Unexpected amount of errors for the second site after reset');
    }

    /**
     * @test
     */
    public function canFlushErrorByItem() {
        $this->importDataSetFromFixture('can_flush_error_by_item.xml');
        $this->assertItemsInQueue(4);

        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $firstSite = $siteRepository->getFirstAvailableSite();

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        $this->assertSame(2, count($errorsForFirstSite), 'Unexpected amount of errors for the first site');

        $item = $this->indexQueue->getItem(4714);
        $this->indexQueue->resetErrorByItem($item);

        $errorsForFirstSite = $this->indexQueue->getErrorsBySite($firstSite);
        $this->assertSame(1, count($errorsForFirstSite), 'Unexpected amount of errors for the first site after resetting one item');
    }
}
