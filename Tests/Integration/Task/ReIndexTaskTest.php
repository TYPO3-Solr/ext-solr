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

use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Task\ReIndexTask;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TestCase to check if the index queue can be initialized by the ReIndex Task
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
class ReIndexTaskTest extends IntegrationTest
{
    /**
     * @var ReIndexTask
     */
    protected $task;

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
        $this->task = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\Task\ReIndexTask');
        $this->indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\Queue');
    }

    /**
     * @return void
     */
    protected function assertEmptyIndexQueue()
    {
        $this->assertEquals(0, $this->indexQueue->getAllItemsCount(), 'Index queue is not empty as expected');
    }

    /**
     * @return void
     */
    protected function assertNotEmptyIndexQueue()
    {
        $this->assertGreaterThan(0, $this->indexQueue->getAllItemsCount(),
            'Index queue is empty and was expected to be not empty.');
    }

    /**
     * @param $amount
     */
    protected function assertIndexQueryContainsItemAmount($amount)
    {
        $this->assertEquals($amount, $this->indexQueue->getAllItemsCount(),
            'Index queue is empty and was expected to contain '.(int) $amount.' items.');
    }

    /**
     * @test
     */
    public function testIfTheQueueIsFilledAfterTaskWasRunning()
    {
        $this->importDataSetFromFixture('can_reindex_task_fill_queue.xml');
        $this->assertEmptyIndexQueue();

        $this->task->setSite(Site::getFirstAvailableSite());
        $this->task->setIndexingConfigurationsToReIndex(array('pages'));
        $this->task->execute();

        $this->assertIndexQueryContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function testCanGetAdditionalInformationFromTask()
    {
        $this->importDataSetFromFixture('can_reindex_task_fill_queue.xml');
        $this->assertEmptyIndexQueue();

        $this->task->setSite(Site::getFirstAvailableSite());
        $this->task->setIndexingConfigurationsToReIndex(array('pages'));
        $additionalInformation = $this->task->getAdditionalInformation();

        $this->assertContains('Indexing Configurations: pages', $additionalInformation);
        $this->assertContains('Root Page ID: 1', $additionalInformation);
    }
}
