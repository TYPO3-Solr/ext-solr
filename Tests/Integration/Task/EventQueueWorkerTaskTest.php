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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Task;

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
