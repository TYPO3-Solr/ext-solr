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
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to test the Queue
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
class QueueTest extends IntegrationTest
{

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\Queue');
    }

    /**
     * Custom assertion to expect a specific amount of items in the queue.
     *
     * @param integer $expectedAmount
     */
    protected function assertItemsInQueue($expectedAmount)
    {
        $itemCount = $this->indexQueue->getAllItemsCount();
        $this->assertSame($itemCount, $expectedAmount, 'Indexqueue contains unexpected amount of items. Expected amount: '.$expectedAmount);
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
        $site = Site::getFirstAvailableSite();
        $this->indexQueue->initialize($site, 'pages');

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

        $this->indexQueue->updateItem('pages', 1);
        $this->assertItemsInQueue(1);

        $this->indexQueue->updateItem('pages', 1);
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
        $this->indexQueue->updateItem('pages', 22);

            // queue should still be empty
        $this->assertEmptyQueue();
    }
}
