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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\Initializer;

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
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

    protected function setUp(): void
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
        self::assertSame($itemCount, $expectedAmount, 'Indexqueue contains unexpected amount of items. Expected amount: ' . $expectedAmount);
    }

    /**
     * Custom assertion to expect an empty queue.
     */
    protected function assertEmptyQueue()
    {
        $this->assertItemsInQueue(0);
    }

    /**
     * Initialize page index queue
     */
    protected function initializeAllPageIndexQueues()
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        /* @var $siteRepository SiteRepository */
        $sites = $siteRepository->getAvailableSites();

        foreach ($sites as $site) {
            $this->pageInitializer->setIndexingConfigurationName('pages');
            $this->pageInitializer->setIndexingConfiguration(
                $site->getSolrConfiguration()->getIndexQueueConfigurationByName('pages')
            );
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

        $this->assertItemsInQueue(5);

        // @todo: verify, is this really as expected? since mount_pid_ol is not set
        // in the case when mount_pid_ol is set 4 pages get added
        self::assertTrue($this->indexQueue->containsItem('pages', 1));
        self::assertTrue($this->indexQueue->containsItem('pages', 10));
        self::assertTrue($this->indexQueue->containsItem('pages', 20));

        self::assertFalse($this->indexQueue->containsItem('pages', 2));
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
        $this->importDataSetFromFixture('mounted_shared_non_root_page_from_different_tree_can_be_indexed.xml');
        $this->assertEmptyQueue();
        $this->initializeAllPageIndexQueues();
        $this->assertItemsInQueue(3); // The root page of "testtwo.site aka integration_tree_two" is included.

        self::assertTrue($this->indexQueue->containsItem('pages', 1));
        self::assertTrue($this->indexQueue->containsItem('pages', 24));

        $items = $this->indexQueue->getItems('pages', 24);
        $firstItem = $items[0];

        self::assertSame('24-14-1', $firstItem->getMountPointIdentifier());
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
     *          ——[14] Mount Point (to [24] to show contents from)
     *
     * @test
     */
    public function initializerIsFillingQueueWithMountedRootPages()
    {
        $this->importDataSetFromFixture('mounted_shared_root_page_from_different_tree_can_be_indexed.xml');
        $this->assertEmptyQueue();
        $this->initializeAllPageIndexQueues();
        $this->assertItemsInQueue(3); // The root page of "testtwo.site aka integration_tree_two" is included.

        self::assertTrue($this->indexQueue->containsItem('pages', 1));
        // the mountpoint MUST NOT be in the queue,
        // because the page "[14] Mount Point" is set to overlay the content from mount source page.
        self::assertFalse($this->indexQueue->containsItem('pages', 14));
        self::assertTrue($this->indexQueue->containsItem('pages', 24));

        $items = $this->indexQueue->getItems('pages', 24);
        $firstItem = $items[0];

        self::assertSame('24-14-1', $firstItem->getMountPointIdentifier());
    }

    /**
     * In this testcase we check if the pages queue will be initialized as expected
     * when we have two pages with mounted page from other site tree, which is not marked as siteroot.
     *
     *     [0]
     *      |
     *      ——[20] Shared-Pages (Folder: Not root)
     *      |   |
     *      |   ——[24] FirstShared
     *      |
     *      ——[ 1] Page (Root)
     *      |   |
     *      |   ——[14] Mount Point 1 (to [24] to show contents from)
     *      |
     *      ——[ 111] Page2 (Root)
     *          |
     *          ——[34] Mount Point 2 (to [24] to show contents from)
     *
     * @test
     */
    public function initializerIsFillingQueuesWithMultipleSitesMounted()
    {
        $this->importDataSetFromFixture('mounted_shared_page_from_multiple_trees_can_be_queued.xml');
        $this->assertEmptyQueue();
        $this->initializeAllPageIndexQueues();
        $this->assertItemsInQueue(4);

        self::assertTrue($this->indexQueue->containsItem('pages', 1));
        // the mountpoint MUST NOT be in the queue,
        // because the page "[14] Mount Point" is set to overlay the content from mount source page.
        self::assertFalse($this->indexQueue->containsItem('pages', 14));
        self::assertTrue($this->indexQueue->containsItem('pages', 24));

        self::assertTrue($this->indexQueue->containsItem('pages', 111));
        // the mountpoint MUST NOT be in the queue,
        // because the page "[34] Mount Point" is set to overlay the content from mount source page.
        self::assertFalse($this->indexQueue->containsItem('pages', 34));

        $items = $this->indexQueue->getItems('pages', 24);
        $firstItem = $items[0];

        self::assertSame('24-14-1', $firstItem->getMountPointIdentifier());

        $secondItem = $items[1];
        self::assertSame('24-34-1', $secondItem->getMountPointIdentifier());
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

        $this->assertItemsInQueue(5); // The root page of "testtwo.site aka integration_tree_two" is included.

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageQueue = $flashMessageService->getMessageQueueByIdentifier('solr.queue.initializer');
        self::assertEquals(2, count($flashMessageQueue->getAllMessages()));
    }

    /**
     * The test case for `additionalWhereClause` restrictions.
     *
     * The initializer MUST ignore only the pages, which matching the `additionalWhereClause`,
     * and NOT the whole sub-tree of them, because The Record-Monitoring stack ignores the state of parents-tree
     * and adds the pages to the index queue anyway.
     *     [0]
     *      |
     *      ——[ 1] Root of Testpage testone.site aka integration_tree_one      (included in index)
     *      |    |
     *      |    ——[2] No Search                                               (not included in index)
     *      |       |
     *      |       ——[3] 2-nd level Subpage                                   (included in index)
     *      |
     *      ——[ 111] Root of Testpage testtwo.site aka integration_tree_two    (included in index)
     * @test
     */
    public function initializerDoesNotIgnoreSubPagesOfRestrictedByAdditionalWhereClauseParents()
    {
        $this->importDataSetFromFixture('initializer_does_not_ignore_sub_pages_of_restricted_by_additionalWhereClause_parents.xml');
        $this->assertEmptyQueue();
        $this->initializeAllPageIndexQueues();

        $this->assertItemsInQueue(3); // The root page of "testtwo.site aka integration_tree_two" is included.

        self::assertTrue(
            $this->indexQueue->containsItem('pages', 3),
            'The index queue does not contain the sub pages of restricted by additionalWhereClause page.' . PHP_EOL
            . 'The initializer MUST NOT ignore the sub pages of restricted pages.'
        );
    }
}
