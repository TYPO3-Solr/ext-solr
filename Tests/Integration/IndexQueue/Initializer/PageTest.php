<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\Initializer;

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
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to test the page queue initializer
 *
 * @author Timo Schmidt
 */
class PageTest extends IntegrationTest
{
    /**
     * @var Page
     */
    protected $pageInitializer;

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
        $this->setUpBackendUserFromFixture(1);
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->pageInitializer = GeneralUtility::makeInstance(Page::class);
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
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
     * Initialize page index queue
     *
     * @return void
     */
    protected function initializeAllPageIndexQueues()
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        /* @var $siteRepository SiteRepository */
        $sites = $siteRepository->getAvailableSites();

        foreach($sites as $site) {
            $this->pageInitializer->setIndexingConfigurationName('pages');
            $this->pageInitializer->setSite($site);
            $this->pageInitializer->setType('pages');
            $this->pageInitializer->initialize();
        }
    }



    /**
     * In this testcase we check if the pages queue will be initialized as expected
     * when we have a page with mounted pages
     *
     *
     *      1
     *      |
     *      ------- 10 (Mounted)
     *                        |
     *                         ------------ 20 (Childpage of mountpoint)
     *
     * @test
     */
    public function initializerIsFillingQueueWithMountPages()
    {
        $this->importDataSetFromFixture('can_add_mount_pages.xml');

        $this->assertEmptyQueue();
        $this->initializeAllPageIndexQueues();

        $this->assertItemsInQueue(4);

            // @todo: verify, is this really as expected? since mount_pid_ol is not set
            // in the case when mount_pid_ol is set 4 pages get added
        $this->assertTrue($this->indexQueue->containsItem('pages', 1));
        $this->assertTrue($this->indexQueue->containsItem('pages', 10));
        $this->assertTrue($this->indexQueue->containsItem('pages', 20));

        $this->assertFalse($this->indexQueue->containsItem('pages', 2));
    }

    /**
     * In this testcase we check if the pages queue will be initialized as expected
     * when we have a page with mounted page from other site tree, which is not marked as siteroot.
     *
     *     [0]
     *      |
     *      ——[20] Shared-Pages (Not root)
     *      |   |
     *      |   ——[24] FirstShared_NotRoot
     *      |
     *      ——[ 1] Page (Root)
     *          |
     *          ——[14] Mounted Page (to [24] to show contents from)
     *
     * @test
     */
    public function initializerIsFillingQueueWithMountedNonRootPages()
    {
        $this->importDataSetFromFixture('mouted_shared_non_root_page_from_different_tree_can_be_indexed.xml');
        $this->assertEmptyQueue();
        $this->initializeAllPageIndexQueues();
        $this->assertItemsInQueue(3);

        $this->assertTrue($this->indexQueue->containsItem('pages', 1));
        // we should check if the mountpoint itself should be in the queue
        $this->assertTrue($this->indexQueue->containsItem('pages', 14));
        $this->assertTrue($this->indexQueue->containsItem('pages', 24));

        $items = $this->indexQueue->getItems('pages', 24);
        $firstItem = $items[0];

        $this->assertSame('24-14-1', $firstItem->getMountPointIdentifier());
    }

    /**
     * In this testcase we check if the pages queue will be initialized as expected
     * when we have a page with mounted page from other site tree, which is not marked as siteroot.
     *
     *     [0]
     *      |
     *      ——[20] Shared-Pages (Folder: Not root)
     *      |   |
     *      |   ——[24] FirstShared_Root
     *      |
     *      ——[ 1] Page (Root)
     *          |
     *          ——[14] Mounted Page (to [24] to show contents from)
     *
     * @test
     */
    public function initializerIsFillingQueueWithMountedRootPages()
    {
        $this->importDataSetFromFixture('mouted_shared_root_page_from_different_tree_can_be_indexed.xml');
        $this->assertEmptyQueue();
        $this->initializeAllPageIndexQueues();
        $this->assertItemsInQueue(3);

        $this->assertTrue($this->indexQueue->containsItem('pages', 1));
        // we should check if the mountpoint itself should be in the queue
        $this->assertTrue($this->indexQueue->containsItem('pages', 14));
        $this->assertTrue($this->indexQueue->containsItem('pages', 24));

        $items = $this->indexQueue->getItems('pages', 24);
        $firstItem = $items[0];

        $this->assertSame('24-14-1', $firstItem->getMountPointIdentifier());
    }

    /**
     * In this testcase we check if the pages queue will be initialized as expected
     * when we have two pages with mounted page from other site tree, which is not marked as siteroot.
     *
     *     [0]
     *      |
     *      ——[20] Shared-Pages (Folder: Not root)
     *      |   |
     *      |   ——[24] FirstShared_Root
     *      |
     *      ——[ 1] Page (Root)
     *      |   |
     *      |   ——[14] Mounted Page (to [24] to show contents from)
     *      |
     *      ——[ 2] Page2 (Root)
     *          |
     *          ——[34] Mounted Page (to [24] to show contents from)
     *
     * @test
     */
    public function initializerIsFillingQueuesWithMultipleSitesMounted()
    {
        $this->importDataSetFromFixture('mouted_shared_page_from_multiple_trees_can_be_queued.xml');
        $this->assertEmptyQueue();
        $this->initializeAllPageIndexQueues();
        $this->assertItemsInQueue(6);

        $this->assertTrue($this->indexQueue->containsItem('pages', 1));
        // we should check if the mountpoint itself should be in the queue
        $this->assertTrue($this->indexQueue->containsItem('pages', 14));
        $this->assertTrue($this->indexQueue->containsItem('pages', 24));

        $this->assertTrue($this->indexQueue->containsItem('pages', 111));
        $this->assertTrue($this->indexQueue->containsItem('pages', 34));

        $items = $this->indexQueue->getItems('pages', 24);
        $firstItem = $items[0];

        $this->assertSame('24-14-1', $firstItem->getMountPointIdentifier());

        $secondItem = $items[1];
        $this->assertSame('24-34-1', $secondItem->getMountPointIdentifier());
    }

    /**
     * Check if invalid mount page is ignored and messages were added to the flash
     * message queue
     *
     * @test
     */
    public function initializerAddsInfoMessagesAboutInvalidMountPages()
    {
        $this->importDataSetFromFixture('can_add_mount_pages.xml');

        $this->assertEmptyQueue();
        $this->initializeAllPageIndexQueues();

        $this->assertItemsInQueue(4);

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageQueue = $flashMessageService->getMessageQueueByIdentifier('solr.queue.initializer');
        $this->assertEquals(2, count($flashMessageQueue->getAllMessages()));
    }
}
