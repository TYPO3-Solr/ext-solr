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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Index\Queue;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the QueueItemRepository
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class QueueItemRepositoryTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canUpdateHasIndexingPropertiesFlagByItemUid()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/update_has_indexing_properties_flag.csv');

        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);

        $queueItem = $queueItemRepository->findItemByUid(4711);
        self::assertFalse($queueItem->hasIndexingProperties());

        $queueItemRepository->updateHasIndexingPropertiesFlagByItemUid(4711, true);

        $queueItem = $queueItemRepository->findItemByUid(4711);
        self::assertTrue($queueItem->hasIndexingProperties());

        $queueItemRepository->updateHasIndexingPropertiesFlagByItemUid(4711, false);

        $queueItem = $queueItemRepository->findItemByUid(4711);
        self::assertFalse($queueItem->hasIndexingProperties());
    }

    /**
     * @test
     */
    public function deleteItemDeletesItemForEverySite()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_and_news_queueitems.csv');
        /* @var QueueItemRepository $queueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        self::assertSame(6, $queueItemRepository->count(), 'Unexpected amount of items in the index queue');
        $queueItemRepository->deleteItem('pages', 1);

        self::assertSame(4, $queueItemRepository->count(), 'Unexpected amount of items in the index queue after deletion by type and uid');
    }

    /**
     * @test
     */
    public function canDeleteItemByPassingTypeOnly()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_and_news_queueitems.csv');
        /* @var QueueItemRepository $queueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        self::assertSame(6, $queueItemRepository->count(), 'Unexpected amount of items in the index queue');
        $queueItemRepository->deleteItem('pages');

        self::assertSame(3, $queueItemRepository->count(), 'Unexpected amount of items in the index queue after deletion by type');
    }

    /**
     * @test
     */
    public function canCountItems()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_and_news_queueitems.csv');
        /* @var QueueItemRepository $queueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        self::assertSame(6, $queueItemRepository->countItems(), 'Unexpected amount of items counted when no filter was passed');
        self::assertSame(3, $queueItemRepository->countItems([], ['pages']), 'Unexpected amount of counted pages');
        self::assertSame(3, $queueItemRepository->countItems([], ['pages'], [], [1, 2]), 'Unexpected amount of counted pages and item uids');
        self::assertSame(1, $queueItemRepository->countItems([], ['pages'], [], [], [4713]), 'Unexpected amount of counted pages and uids');
    }

    /**
     * @test
     */
    public function canFindItems()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_and_news_queueitems.csv');
        /* @var QueueItemRepository $queueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        $items = $queueItemRepository->findItems([], ['pages']);

        /** @var Item $firstItem */
        $firstItem = $items[0];
        self::assertSame(2, count($items));
        self::assertSame('pages', $firstItem->getType(), 'First item has unexpected type');
    }

    /**
     * @test
     */
    public function indexingPropertyIsKeptWhenItIsReferencedToAnotherQueueItem()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_keep_indexing_properties.csv');

        /** @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);

        $currentSite = $siteRepository->getSiteByPageId(4711);

        /* @var QueueItemRepository $queueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        $queueItemRepository->add('pages', 4711, 1, 2, 'news_pages');
        $queueItemRepository->add('pages', 4711, 1, 2, 'product_pages');

        $items = $queueItemRepository->findItemsByItemTypeAndItemUid('pages', 4711);
        self::assertCount(2, $items, 'Retrieved unexpected amount of records from queue item repository');

        foreach ($items as $item) {
            self::assertSame([], $item->getIndexingProperties(), 'New added item should have empty indexing properties');
            self::assertFalse($item->hasIndexingProperty('sense_of_live'), 'New indexing property should not exist');

            $item->setIndexingProperty('shared_property', 'hello solr');
            $item->storeIndexingProperties();
            self::assertSame('hello solr', $item->getIndexingProperty('shared_property'), 'New indexing property be retrieved');
        }

        $queueItemRepository->deleteItemsBySite($currentSite, 'news_pages');

        $items = $queueItemRepository->findAll();
        self::assertCount(1, $items, 'Queue should only contain on more item after deletion with index queue configuration');

        $items = $queueItemRepository->findItemsByItemTypeAndItemUid('pages', 4711);
        self::assertCount(1, $items, 'Retrieved unexpected amount of records from queue item repository');

        foreach ($items as $item) {
            self::assertSame('hello solr', $item->getIndexingProperty('shared_property'), 'Previous added indexing property was lost');
        }
    }

    /**
     * @test
     */
    public function canFlushErrorByItem()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_flush_error_by_item.csv');
        /* @var QueueItemRepository $queueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);

        $item = $queueItemRepository->findItemByUid(4714);
        self::assertInstanceOf(Item::class, $item);
        $queueItemRepository->flushErrorByItem($item);

        $item = $queueItemRepository->findItemByUid(4714);
        self::assertInstanceOf(Item::class, $item);
        self::assertEmpty($item->getErrors());
    }
}
