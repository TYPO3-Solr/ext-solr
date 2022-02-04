<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022 Markus Friedrich <markus.friedrich@dkd.de>
 *
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTask;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Scheduler;

/**
 * Test case to check if the scheduler task EventQueueWorkerTask can process
 * event queue entries
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class EventQueueWorkerTaskTest extends IntegrationTest
{
    /**
     * @var array
     */
    protected $coreExtensionsToLoad = [
        'extensionmanager',
        'scheduler',
    ];

    /**
     * @var EventQueueItemRepository
     */
    protected $eventQueue;

    /**
     * @var Queue
     */
    protected $indexQueue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->eventQueue = GeneralUtility::makeInstance(EventQueueItemRepository::class);

        /** @var ExtensionConfiguration $task */
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $extConf->set('solr', ['monitoringType' => 1]);
    }

    protected function tearDown(): void
    {
        unset($this->indexQueue);
        unset($this->eventQueue);
        unset($GLOBALS['TYPO3_CONF_VARS']);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canProcessEventQueueItems(): void
    {
        $this->importDataSetFromFixture('can_process_event_queue.xml');
        $this->eventQueue->addEventToQueue(new RecordUpdatedEvent(1, 'tt_content'));

        /** @var EventQueueWorkerTask $task */
        $task = GeneralUtility::makeInstance(EventQueueWorkerTask::class);

        /** @var Scheduler $scheduler */
        $scheduler = GeneralUtility::makeInstance(Scheduler::class);
        $scheduler->executeTask($task);

        self::assertEquals(1, $this->indexQueue->getAllItemsCount());
        self::assertEmpty($this->eventQueue->getEventQueueItems(null, false));
    }

    /**
     * @test
     */
    public function canHandleErroneousEventQueueItems(): void
    {
        $this->importDataSetFromFixture('can_handle_erroneous_event_queue_items.xml');

        /** @var EventQueueWorkerTask $task */
        $task = GeneralUtility::makeInstance(EventQueueWorkerTask::class);

        /** @var Scheduler $scheduler */
        $scheduler = GeneralUtility::makeInstance(Scheduler::class);
        $scheduler->executeTask($task);

        self::assertEquals(0, $this->indexQueue->getAllItemsCount());
        self::assertEmpty($this->eventQueue->getEventQueueItems());
        $queueItems = $this->eventQueue->getEventQueueItems(null, false);
        self::assertEquals(1, count($queueItems));
        self::assertEquals(1, $queueItems[0]['error']);
        self::assertNotEmpty($queueItems[0]['error_message']);
    }
}
