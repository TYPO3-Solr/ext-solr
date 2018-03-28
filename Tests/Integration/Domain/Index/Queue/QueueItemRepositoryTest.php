<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Index;

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
}