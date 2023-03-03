<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2021 Markus Friedrich <markus.friedrich@dkd.de>
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

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Scheduler;
use TYPO3\CMS\Scheduler\Execution;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTask;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\DelayedProcessingFinishedEvent;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;

/**
 * Testcase for EventQueueWorkerTask
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class EventQueueWorkerTaskTest extends UnitTest
{
    /**
     * @var EventQueueWorkerTask
     */
    protected  $task;

    protected function setUp(): void
    {
        GeneralUtility::setSingletonInstance(Scheduler::class, $this->createMock(Scheduler::class));
        GeneralUtility::addInstance(Execution::class, $this->createMock(Execution::class));

        $this->task = new EventQueueWorkerTask();
        $this->task->setLimit(99);
    }

    public function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canProcessEventQueue(): void
    {
        $eventQueueItemRepositoryMock = $this->createMock(EventQueueItemRepository::class);
        GeneralUtility::setSingletonInstance(EventQueueItemRepository::class, $eventQueueItemRepositoryMock);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        GeneralUtility::addInstance(EventDispatcherInterface::class, $eventDispatcherMock);

        /** @var RecordUpdatedEvent $event */
        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');
        $serializedEvent = serialize($event);
        /** @var RecordUpdatedEvent $unserializedEvent */
        $unserializedEvent = unserialize($serializedEvent);
        $queueItem = [
            'uid' => 10,
            'event' => $serializedEvent
        ];

        $eventQueueItemRepositoryMock
            ->expects($this->once())
            ->method('getEventQueueItems')
            ->with(99)
            ->willReturn([$queueItem]);

        $dispatchedEvents = [];
        $eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvents) {
                $dispatchedEvents[] = func_get_arg(0);
            }));

        $eventQueueItemRepositoryMock
            ->expects($this->never())
            ->method('updateEventQueueItem');

        $eventQueueItemRepositoryMock
            ->expects($this->once())
            ->method('deleteEventQueueItems')
            ->with([10]);

        $this->task->execute();

        $unserializedEvent->setForceImmediateProcessing(true);
        $this->assertCount(2, $dispatchedEvents);
        $this->assertEquals($unserializedEvent, $dispatchedEvents[0]);
        $this->assertTrue($dispatchedEvents[1] instanceof DelayedProcessingFinishedEvent);
    }

    /**
     * @test
     */
    public function canHandleErrors(): void
    {
        $eventQueueItemRepositoryMock = $this->createMock(EventQueueItemRepository::class);
        GeneralUtility::setSingletonInstance(EventQueueItemRepository::class, $eventQueueItemRepositoryMock);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        GeneralUtility::addInstance(EventDispatcherInterface::class, $eventDispatcherMock);
        $solrLogManagerMock = $this->createMock(SolrLogManager::class);
        GeneralUtility::addInstance(SolrLogManager::class, $solrLogManagerMock);

        /** @var RecordUpdatedEvent $event */
        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');
        $serializedEvent = serialize($event);
        /** @var RecordUpdatedEvent $unserializedEvent */
        $unserializedEvent = unserialize($serializedEvent);
        $queueItem = [
            'uid' => 10,
            'event' => $serializedEvent
        ];

        $eventQueueItemRepositoryMock
            ->expects($this->once())
            ->method('getEventQueueItems')
            ->with(99)
            ->willReturn([$queueItem]);

        $eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \Exception('', 1641889238));

        $solrLogManagerMock
            ->expects($this->once())
            ->method('log')
            ->with(SolrLogManager::ERROR, $this->anything(), $this->anything());

        $eventQueueItemRepositoryMock
            ->expects($this->once())
            ->method('updateEventQueueItem')
            ->with(10, ['error' => 1, 'error_message' => '[1641889238]']);

        $eventQueueItemRepositoryMock
            ->expects($this->once())
            ->method('deleteEventQueueItems')
            ->with([]);

        $this->task->execute();
    }
}
