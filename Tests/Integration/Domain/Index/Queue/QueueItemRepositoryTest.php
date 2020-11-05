<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Index\Queue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the QueueItemRepository
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class QueueItemRepositoryTest extends IntegrationTest
{

    public function setUp() {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canUpdateHasIndexingPropertiesFlagByItemUid()
    {
        $this->importDataSetFromFixture('update_has_indexing_properties_flag.xml');

        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);

        $queueItem = $queueItemRepository->findItemByUid(4711);
        $this->assertFalse($queueItem->hasIndexingProperties());

        $queueItemRepository->updateHasIndexingPropertiesFlagByItemUid(4711, true);

        $queueItem = $queueItemRepository->findItemByUid(4711);
        $this->assertTrue($queueItem->hasIndexingProperties());

        $queueItemRepository->updateHasIndexingPropertiesFlagByItemUid(4711, false);

        $queueItem = $queueItemRepository->findItemByUid(4711);
        $this->assertFalse($queueItem->hasIndexingProperties());
    }

    /**
     * @test
     */
    public function deleteItemDeletesItemForEverySite()
    {
        $this->importDataSetFromFixture('can_delete_item_by_type_and_uid.xml');
        /** @var $queueItemRepository QueueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        $this->assertSame(6, $queueItemRepository->count(), 'Unexpected amount of items in the index queue');
        $queueItemRepository->deleteItem('pages', 1);

        $this->assertSame(3, $queueItemRepository->count(), 'Unexpected amount of items in the index queue after deletion by type and uid');
    }

    /**
     * @test
     */
    public function canDeleteItemByPassingTypeOnly()
    {
        $this->importDataSetFromFixture('can_delete_item_by_type.xml');
        /** @var $queueItemRepository QueueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        $this->assertSame(6, $queueItemRepository->count(), 'Unexpected amount of items in the index queue');
        $queueItemRepository->deleteItem('pages');

        $this->assertSame(2, $queueItemRepository->count(), 'Unexpected amount of items in the index queue after deletion by type and uid');
    }

    /**
     * @test
     */
    public function canCountItems()
    {
        $this->importDataSetFromFixture('can_count_items.xml');
        /** @var $queueItemRepository QueueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        $this->assertSame(6, $queueItemRepository->countItems(), 'Unexpected amount of items counted when no filter was passed');
        $this->assertSame(4, $queueItemRepository->countItems([], ['pages']), 'Unexpected amount of counted pages');
        $this->assertSame(2, $queueItemRepository->countItems([], ['pages'], [], [3,4]), 'Unexpected amount of counted pages and item uids');
        $this->assertSame(1, $queueItemRepository->countItems([], ['pages'], [], [], [4713]), 'Unexpected amount of counted pages and uids');
    }

    /**
     * @test
     */
    public function canFindItems()
    {
        $this->importDataSetFromFixture('can_find_items.xml');
        /** @var $queueItemRepository QueueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        $items = $queueItemRepository->findItems([], ['pages']);

            /** @var Item $firstItem */
        $firstItem = $items[0];
        $this->assertSame(4, count($items));
        $this->assertSame('pages', $firstItem->getType(), 'First item has unexpected type');
    }

    /**
     * @test
     */
    public function indexingPropertyIsKeptWhenItIsReferencedToAnotherQueueItem()
    {
        $this->importDataSetFromFixture('can_keep_indexing_properties.xml');

        /** @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);

        $currentSite = $siteRepository->getSiteByPageId(4711);


        /** @var $queueItemRepository  QueueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);
        $queueItemRepository->add('pages', 4711, 1, 2, 'news_pages');
        $queueItemRepository->add('pages', 4711, 1, 2, 'product_pages');

        $items = $queueItemRepository->findItemsByItemTypeAndItemUid('pages', 4711);
        $this->assertCount(2, $items, 'Retrieved unexpected amount of records from queue item repository');

        foreach($items as $item) {
            $this->assertSame([], $item->getIndexingProperties(), 'New added item should have empty indexing properties');
            $this->assertFalse($item->hasIndexingProperty('sense_of_live'), 'New indexing property should not exist');

            $item->setIndexingProperty('shared_property', 'hello solr');
            $item->storeIndexingProperties();
            $this->assertSame('hello solr', $item->getIndexingProperty('shared_property'), 'New indexing property be retrieved');
        }

        $queueItemRepository->deleteItemsBySite($currentSite, 'news_pages');

        $items = $queueItemRepository->findAll();
        $this->assertCount(1, $items, 'Queue should only contain on more item after deletion with index queue configuration');

        $items = $queueItemRepository->findItemsByItemTypeAndItemUid('pages', 4711);
        $this->assertCount(1, $items, 'Retrieved unexpected amount of records from queue item repository');

        foreach($items as $item) {
            $this->assertSame('hello solr', $item->getIndexingProperty('shared_property'), 'Previous added indexing property was lost');
        }
    }

    /**
     * @test
     */
    public function canFlushErrorByItem() {
        $this->importDataSetFromFixture('can_flush_error_by_item.xml');
        /** @var $queueItemRepository QueueItemRepository */
        $queueItemRepository = GeneralUtility::makeInstance(QueueItemRepository::class);

        $item = $queueItemRepository->findItemByUid(4714);
        $this->assertInstanceOf(Item::class, $item);
        $queueItemRepository->flushErrorByItem($item);

        $item = $queueItemRepository->findItemByUid(4714);
        $this->assertInstanceOf(Item::class, $item);
        $this->assertEmpty($item->getErrors());
    }
}
