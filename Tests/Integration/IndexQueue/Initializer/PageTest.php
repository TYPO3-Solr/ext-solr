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
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to test the page queue initializer
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
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
        $this->pageInitializer = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page');
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
        $this->pageInitializer->setIndexingConfigurationName('pages');
        $this->pageInitializer->setSite(Site::getFirstAvailableSite());
        $this->pageInitializer->setType('pages');

        $this->pageInitializer->initialize();

        $this->assertItemsInQueue(3);

            // @todo: verify, is this really as expected? since mount_pid_ol is not set
            // in the case when mount_pid_ol is set 4 pages get added
        $this->assertTrue($this->indexQueue->containsItem('pages', 1));
        $this->assertTrue($this->indexQueue->containsItem('pages', 10));
        $this->assertTrue($this->indexQueue->containsItem('pages', 20));

        $this->assertFalse($this->indexQueue->containsItem('pages', 2));
    }
}
